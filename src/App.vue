<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent :class="{ 'icon-loading': loading, 'in-call': isInCall }"
		app-name="talk">
		<LeftSidebar v-if="getUserId" ref="leftSidebar" />
		<NcAppContent>
			<router-view />
		</NcAppContent>
		<RightSidebar :is-in-call="isInCall" />
		<MediaSettings :recording-consent-given.sync="recordingConsentGiven" />
		<SettingsDialog />
		<ConversationSettingsDialog />
	</NcContent>
</template>

<script>
import debounce from 'debounce'
import { provide } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import { useHotKey } from '@nextcloud/vue/dist/Composables/useHotKey.js'
import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'

import ConversationSettingsDialog from './components/ConversationSettings/ConversationSettingsDialog.vue'
import LeftSidebar from './components/LeftSidebar/LeftSidebar.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import RightSidebar from './components/RightSidebar/RightSidebar.vue'
import SettingsDialog from './components/SettingsDialog/SettingsDialog.vue'

import { useActiveSession } from './composables/useActiveSession.js'
import { useDocumentTitle } from './composables/useDocumentTitle.ts'
import { useHashCheck } from './composables/useHashCheck.js'
import { useIsInCall } from './composables/useIsInCall.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.js'
import { CONVERSATION, PARTICIPANT } from './constants.js'
import Router from './router/router.js'
import BrowserStorage from './services/BrowserStorage.js'
import { EventBus } from './services/EventBus.ts'
import { leaveConversationSync } from './services/participantsService.js'
import { useCallViewStore } from './stores/callView.js'
import { useFederationStore } from './stores/federation.ts'
import { useSidebarStore } from './stores/sidebar.js'
import { checkBrowser } from './utils/browserCheck.ts'
import { signalingKill } from './utils/webrtc/index.js'

export default {
	name: 'App',
	components: {
		NcAppContent,
		NcContent,
		LeftSidebar,
		RightSidebar,
		SettingsDialog,
		ConversationSettingsDialog,
		MediaSettings,
	},

	setup() {
		useDocumentTitle()
		// Add provided value to check if we're in the main app or plugin
		provide('Talk:isMainApp', true)

		return {
			isInCall: useIsInCall(),
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			isMobile: useIsMobile(),
			isNextcloudTalkHashDirty: useHashCheck(),
			supportSessionState: useActiveSession(),
			federationStore: useFederationStore(),
			callViewStore: useCallViewStore(),
			sidebarStore: useSidebarStore(),
		}
	},

	data() {
		return {
			loading: false,
			isRefreshingCurrentConversation: false,
			recordingConsentGiven: false,
			debounceRefreshCurrentConversation: () => {},
		}
	},

	computed: {
		getUserId() {
			return this.$store.getters.getUserId()
		},

		isSendingMessages() {
			return this.$store.getters.isSendingMessages
		},

		warnLeaving() {
			return !this.isLeavingAfterSessionIssue && this.isInCall
		},

		/**
		 * The current conversation token
		 *
		 * @return {string} The token.
		 */
		token() {
			return this.$store.getters.getToken()
		},

		/**
		 * The current conversation
		 *
		 * @return {object} The conversation object.
		 */
		currentConversation() {
			return this.$store.getters.conversation(this.token)
		},

		/**
		 * Computes whether the current conversation is one to one
		 *
		 * @return {boolean} The result
		 */
		isOneToOne() {
			return this.currentConversation?.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.currentConversation?.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},
	},

	watch: {
		token(newValue, oldValue) {
			const shouldShowSidebar = BrowserStorage.getItem('sidebarOpen') !== 'false'
			// Collapse the sidebar if it's a one to one conversation
			if (this.isOneToOne || !shouldShowSidebar || this.isMobile) {
				this.sidebarStore.hideSidebar({ cache: false })
			} else if (shouldShowSidebar) {
				this.sidebarStore.showSidebar({ cache: false })
			}

			// Reset recording consent if switch doesn't happen within breakout rooms or main room
			if (!this.isBreakoutRoomsNavigation(oldValue, newValue)) {
				this.recordingConsentGiven = false
			}
		},

		isInCall: {
			immediate: true,
			handler(value) {
				const toggle = this.$refs.leftSidebar?.$refs.leftSidebar?.$el.querySelector('button.app-navigation-toggle')
				if (value) {
					toggle?.setAttribute('data-theme-dark', true)
				} else {
					toggle?.removeAttribute('data-theme-dark')
				}
			}
		},
	},

	beforeCreate() {
		const authorizedUser = getCurrentUser()?.uid || null
		const lastLoggedInUser = BrowserStorage.getItem('last_logged_in_user')

		if (authorizedUser !== lastLoggedInUser) {
			// TODO introduce helper/util to list and clear all sensitive data
			// or create BrowserSensitiveStorage for this purposes,
			// if we have more than one source
			BrowserStorage.removeItem('cachedConversations')
		}

		if (authorizedUser) {
			BrowserStorage.setItem('last_logged_in_user', authorizedUser)
		}
	},

	created() {
		window.addEventListener('beforeunload', this.preventUnload)
		useHotKey('f', this.handleAppSearch, { ctrl: true, stop: true, prevent: true })
	},

	beforeDestroy() {
		this.debounceRefreshCurrentConversation.clear?.()
		if (!getCurrentUser()) {
			EventBus.off('should-refresh-conversations', this.debounceRefreshCurrentConversation)
		}

		unsubscribe('notifications:action:execute', this.interceptNotificationActions)

		window.removeEventListener('beforeunload', this.preventUnload)

		EventBus.off('joined-conversation')
		EventBus.off('switch-to-conversation')
		EventBus.off('conversations-received')
		EventBus.off('forbidden-route')
	},

	beforeMount() {
		if (!getCurrentUser()) {
			EventBus.once('joined-conversation', () => {
				this.fixmeDelayedSetupOfGuestUsers()
			})
			EventBus.on('should-refresh-conversations', this.debounceRefreshCurrentConversation)
		}

		if (this.$route.name === 'conversation') {
			// Update current token in the token store
			this.$store.dispatch('updateToken', this.$route.params.token)
			// Automatically join the conversation as well
			this.$store.dispatch('joinConversation', { token: this.$route.params.token })
		}

		window.addEventListener('resize', this.onResize)

		this.onResize()

		window.addEventListener('unload', () => {
			console.info('Navigating away, leaving conversation')
			if (this.token) {
				// We have to do this synchronously, because in unload and beforeunload
				// Promises, async and await are prohibited.
				signalingKill()
				if (!this.isLeavingAfterSessionIssue) {
					leaveConversationSync(this.token)
				}
			}
		})

		EventBus.on('switch-to-conversation', (params) => {
			if (this.isInCall) {
				this.callViewStore.setForceCallView(true)

				const enableAudio = !BrowserStorage.getItem('audioDisabled_' + this.token)
				const enableVideo = !BrowserStorage.getItem('videoDisabled_' + this.token)
				const enableVirtualBackground = !!BrowserStorage.getItem('virtualBackgroundEnabled_' + this.token)
				const virtualBackgroundType = BrowserStorage.getItem('virtualBackgroundType_' + this.token)
				const virtualBackgroundBlurStrength = BrowserStorage.getItem('virtualBackgroundBlurStrength_' + this.token)
				const virtualBackgroundUrl = BrowserStorage.getItem('virtualBackgroundUrl_' + this.token)

				EventBus.once('joined-conversation', async ({ token }) => {
					if (params.token !== token) {
						return
					}

					if (enableAudio) {
						BrowserStorage.removeItem('audioDisabled_' + token)
					} else {
						BrowserStorage.setItem('audioDisabled_' + token, 'true')
					}
					if (enableVideo) {
						BrowserStorage.removeItem('videoDisabled_' + token)
					} else {
						BrowserStorage.setItem('videoDisabled_' + token, 'true')
					}
					if (enableVirtualBackground) {
						BrowserStorage.setItem('virtualBackgroundEnabled_' + token, 'true')
					} else {
						BrowserStorage.removeItem('virtualBackgroundEnabled_' + token)
					}
					if (virtualBackgroundType) {
						BrowserStorage.setItem('virtualBackgroundType_' + token, virtualBackgroundType)
					} else {
						BrowserStorage.removeItem('virtualBackgroundType_' + token)
					}
					if (virtualBackgroundBlurStrength) {
						BrowserStorage.setItem('virtualBackgroundBlurStrength' + token, virtualBackgroundBlurStrength)
					} else {
						BrowserStorage.removeItem('virtualBackgroundBlurStrength' + token)
					}
					if (virtualBackgroundUrl) {
						BrowserStorage.setItem('virtualBackgroundUrl_' + token, virtualBackgroundUrl)
					} else {
						BrowserStorage.removeItem('virtualBackgroundUrl_' + token)
					}

					const conversation = this.$store.getters.conversation(token)

					let flags = PARTICIPANT.CALL_FLAG.IN_CALL
					if (conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) {
						flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
					}
					if (conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) {
						flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
					}

					await this.$store.dispatch('joinCall', {
						token: params.token,
						participantIdentifier: this.$store.getters.getParticipantIdentifier(),
						flags,
						silent: true,
						recordingConsent: this.recordingConsentGiven,
					})

					this.callViewStore.setForceCallView(false)
				})
			}

			this.$router.push({ name: 'conversation', params: { token: params.token, skipLeaveWarning: true } })
		})

		EventBus.on('conversations-received', (params) => {
			if (this.$route.name === 'conversation'
				&& !this.$store.getters.conversation(this.token)) {
				if (!params.singleConversation) {
					console.info('Conversations received, but the current conversation is not in the list, trying to get potential public conversation manually')
					this.refreshCurrentConversation()
				} else {
					console.info('Conversation received, but the current conversation is not in the list. Redirecting to not found page')
					this.$router.push({ name: 'notfound', params: { skipLeaveWarning: true } })
				}
			}
		})

		EventBus.on('forbidden-route', (params) => {
			this.$router.push({ name: 'forbidden' })
		})

		/**
		 * Listens to the conversationsReceived globalevent, emitted by the conversationsList
		 * component each time a new batch of conversations is received and processed in
		 * the store.
		 */
		EventBus.once('conversations-received', () => {
			if (!getCurrentUser()) {
				// Set the current actor/participant for guests
				const conversation = this.$store.getters.conversation(this.token)
				this.$store.dispatch('setCurrentParticipant', conversation)
			}
		})

		const beforeRouteChangeListener = (to, from, next) => {
			if (this.isNextcloudTalkHashDirty) {
				// Nextcloud Talk configuration changed, reload the page when changing configuration
				window.location = generateUrl('call/' + to.params.token)
				return
			}

			if (from.name === 'conversation' && from.params.token !== to.params.token) {
				this.$store.dispatch('leaveConversation', { token: from.params.token })
			}

			/**
			 * This runs whenever the new route is a conversation.
			 */
			if (to.name === 'conversation' && from.params.token !== to.params.token) {
				this.$store.dispatch('joinConversation', { token: to.params.token })
			}

			next()
		}

		this.$router.afterEach((to, from) => {
			/**
			 * Update current token in the token store
			 */
			if (from.params.token !== to.params.token) {
				this.$store.dispatch('updateToken', to.params.token ?? '')
			}

			/**
			 * Fires a global event that tells the whole app that the route has changed. The event
			 * carries the from and to objects as payload
			 */
			EventBus.emit('route-change', { from, to })
		})

		/**
		 * Global before guard, this is called whenever a navigation is triggered.
		 */
		Router.beforeEach((to, from, next) => {
			if (from.name === 'conversation' && to.name === 'conversation' && from.params.token === to.params.token) {
				// Navigating within the same conversation
				beforeRouteChangeListener(to, from, next)
			} else if (!this.warnLeaving || to.params?.skipLeaveWarning) {
				// Safe to navigate
				beforeRouteChangeListener(to, from, next)
			} else {
				OC.dialogs.confirmDestructive(
					t('spreed', 'Navigating away from the page will leave the call in {conversation}', {
						conversation: this.currentConversation?.displayName ?? '',
					}),
					t('spreed', 'Leave call'),
					{
						type: OC.dialogs.YES_NO_BUTTONS,
						confirm: t('spreed', 'Leave call'),
						confirmClasses: 'error',
						cancel: t('spreed', 'Stay in call'),
					},
					(decision) => {
						if (!decision) {
							return
						}

						beforeRouteChangeListener(to, from, next)
					}
				)
			}
		})

		if (getCurrentUser()) {
			console.debug('Setting current user')
			this.$store.dispatch('setCurrentUser', getCurrentUser())
		} else {
			console.debug('Can not set current user because it\'s a guest')
		}
	},

	async mounted() {
		this.debounceRefreshCurrentConversation = debounce(this.refreshCurrentConversation, 3000)

		if (!IS_DESKTOP) {
			checkBrowser()
		}

		if (this.$route.name === 'root' && this.isMobile) {
			await this.$nextTick()
			emit('toggle-navigation', {
				open: true,
			})
		}

		subscribe('notifications:action:execute', this.interceptNotificationActions)
		subscribe('notifications:notification:received', this.interceptNotificationReceived)
	},

	methods: {
		t,
		/**
		 * Intercept clicking actions on notifications and open the conversation without a page reload instead
		 *
		 * @param {object} event The event object provided by the notifications app
		 * @param {object} event.notification The notification object
		 * @param {string} event.notification.app The app ID of the app providing the notification
		 * @param {object} event.action The action that was clicked
		 * @param {string} event.action.url The URL the action is aiming at
		 * @param {string} event.action.type The request type used for the action
		 * @param {boolean} event.cancelAction Option to cancel the action so no page reload is happening
		 */
		async interceptNotificationActions(event) {
			if (event.notification.app !== 'spreed') {
				return
			}

			switch (event.action.type) {
			case 'WEB': {
				const load = event.action.url.split('/call/').pop()
				if (!load) {
					return
				}

				const [token, hash] = load.split('#')
				this.$router.push({
					name: 'conversation',
					hash: hash ? `#${hash}` : '',
					params: {
						token,
					},
				})

				event.cancelAction = true
				break
			}
			case 'POST': {
				// Federation invitation handling
				if (event.notification.objectType === 'remote_talk_share') {
					try {
						event.cancelAction = true
						this.federationStore.addInvitationFromNotification(event.notification)
						const conversation = await this.federationStore.acceptShare(event.notification.objectId)
						if (conversation.token) {
							this.$store.dispatch('addConversation', conversation)
							this.$router.push({ name: 'conversation', params: { token: conversation.token } })
						}
					} catch (error) {
						console.error(error)
					}
				}
				break
			}
			case 'DELETE': {
				// Federation invitation handling
				if (event.notification.objectType === 'remote_talk_share') {
					try {
						event.cancelAction = true
						this.federationStore.addInvitationFromNotification(event.notification)
						await this.federationStore.rejectShare(event.notification.objectId)
					} catch (error) {
						console.error(error)
					}
				}
				break
			}
			default: break
			}
		},

		/**
		 * Intercept …
		 *
		 * @param {object} event The event object provided by the notifications app
		 * @param {object} event.notification The notification object
		 * @param {string} event.notification.app The app ID of the app providing the notification
		 */
		interceptNotificationReceived(event) {
			if (event.notification.app !== 'spreed') {
				return
			}

			switch (event.notification.objectType) {
			case 'chat': {
				if (event.notification.subjectRichParameters?.reaction) {
					// Ignore reaction notifications in case of one-to-one and always-notify
					return
				}

				this.$store.dispatch('updateConversationLastMessageFromNotification', {
					notification: event.notification,
				})
				break
			}
			case 'call': {
				this.$store.dispatch('updateCallStateFromNotification', {
					notification: event.notification,
				})
				break
			}
			// Federation invitation handling
			case 'remote_talk_share': {
				this.federationStore.addInvitationFromNotification(event.notification)
				break
			}
			default: break
			}
		},

		fixmeDelayedSetupOfGuestUsers() {
			// FIXME Refresh the data now that the user joined the conversation
			// The join request returns this data already, but it's lost in the signaling code
			this.refreshCurrentConversation()

			window.setInterval(() => {
				this.refreshCurrentConversation()
			}, 30000)
		},

		refreshCurrentConversation() {
			this.fetchSingleConversation(this.token)
		},

		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},

		preventUnload(event) {
			if (!this.warnLeaving && !this.isSendingMessages) {
				return
			}

			event.preventDefault()
		},

		async fetchSingleConversation(token) {
			if (this.isRefreshingCurrentConversation) {
				return
			}
			this.isRefreshingCurrentConversation = true

			try {
				/**
				 * Fetches a single conversation
				 */
				await this.$store.dispatch('fetchConversation', { token })

				/**
				 * Emits a global event that is used in App.vue to update the page title once the
				 * ( if the current route is a conversation and once the conversations are received)
				 */
				EventBus.emit('conversations-received', {
					singleConversation: true,
				})
			} catch (exception) {
				console.info('Conversation received, but the current conversation is not in the list. Redirecting to /apps/spreed')
				this.$router.push({ name: 'notfound', params: { skipLeaveWarning: true } })
			} finally {
				this.isRefreshingCurrentConversation = false
			}
		},
		// Upon pressing Ctrl+F, focus SearchBox native input in the LeftSidebar
		handleAppSearch() {
			emit('toggle-navigation', {
				open: true,
			})
			this.$nextTick(() => {
				this.$refs.leftSidebar.$refs.searchBox.focus()
			})
		},

		/**
		 * Check if conversation was switched within breakout rooms and parent room.
		 *
		 * @param {string} oldToken The old conversation's token
		 * @param {string} newToken The new conversation's token
		 * @return {boolean}
		 */
		isBreakoutRoomsNavigation(oldToken, newToken) {
			const oldConversation = this.$store.getters.conversation(oldToken)
			const newConversation = this.$store.getters.conversation(newToken)

			// One of rooms is undefined
			if (!oldConversation || !newConversation) {
				return false
			}

			// Parent to breakout
			if (oldConversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
				&& newConversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM) {
				return true
			}

			// Breakout to parent
			if (oldConversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
				&& newConversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED) {
				return true
			}

			// Breakout to breakout
			return oldConversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM && newConversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
		}
	},
}
</script>

<style lang="scss">
/* FIXME: remove after https://github.com/nextcloud/nextcloud-vue/issues/2097 is solved */
.mx-datepicker-main.mx-datepicker-popup {
	z-index: 10001 !important;
}

/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4959 is released */
body .modal-wrapper * {
	box-sizing: border-box;
}

/* FIXME: Align styles of NcModal header with NcDialog header. Remove if all are migrated */
.modal-wrapper h2.nc-dialog-alike-header {
	font-size: 21px;
	text-align: center;
	height: fit-content;
	min-height: var(--default-clickable-area);
	line-height: var(--default-clickable-area);
	overflow-wrap: break-word;
	margin-block: 0 12px;
}

// Styles for the app content at fullscreen mode
body.talk-in-fullscreen {
	#header {
		display: none !important;
	}
	#content-vue {
		margin: 0;
		height: 100%;
		width: 100%;
		border-radius: 0;
	}
}

// Overwrites styles from public.scss in public conversations
body#body-public {
	--footer-height: 0;
}
</style>

<style lang="scss" scoped>

.content {
	&.in-call {
		:deep(.app-content) {
			background-color: transparent;
		}
	}

	// Fix fullscreen black bar on top
	&:fullscreen {
		padding-top: 0;

		:deep(.app-sidebar) {
			height: 100vh !important;
		}
	}
}

</style>
