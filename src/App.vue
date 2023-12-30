<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<NcContent v-shortkey.once="disableKeyboardShortcuts ? null : ['ctrl', 'f']"
		:class="{ 'icon-loading': loading, 'in-call': isInCall }"
		app-name="talk"
		@shortkey.native="handleAppSearch">
		<LeftSidebar v-if="getUserId && !isFullscreen" ref="leftSidebar" />
		<NcAppContent>
			<router-view />
		</NcAppContent>
		<RightSidebar :is-in-call="isInCall" />
		<PreventUnload :when="warnLeaving || isSendingMessages" />
		<MediaSettings :initialize-on-mounted="false" :recording-consent-given.sync="recordingConsentGiven" />
		<SettingsDialog />
		<ConversationSettingsDialog />
	</NcContent>
</template>

<script>
import debounce from 'debounce'
import PreventUnload from 'vue-prevent-unload'

import { getCurrentUser } from '@nextcloud/auth'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'

import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'

import ConversationSettingsDialog from './components/ConversationSettings/ConversationSettingsDialog.vue'
import LeftSidebar from './components/LeftSidebar/LeftSidebar.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import RightSidebar from './components/RightSidebar/RightSidebar.vue'
import SettingsDialog from './components/SettingsDialog/SettingsDialog.vue'

import { useActiveSession } from './composables/useActiveSession.js'
import { useHashCheck } from './composables/useHashCheck.js'
import { useIsInCall } from './composables/useIsInCall.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.js'
import { CONVERSATION, PARTICIPANT } from './constants.js'
import Router from './router/router.js'
import BrowserStorage from './services/BrowserStorage.js'
import { EventBus } from './services/EventBus.js'
import { leaveConversationSync } from './services/participantsService.js'
import { checkBrowser } from './utils/browserCheck.js'
import { signalingKill } from './utils/webrtc/index.js'

export default {
	name: 'App',
	components: {
		NcAppContent,
		NcContent,
		LeftSidebar,
		PreventUnload,
		RightSidebar,
		SettingsDialog,
		ConversationSettingsDialog,
		MediaSettings,
	},

	setup() {
		return {
			isInCall: useIsInCall(),
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			isMobile: useIsMobile(),
			isNextcloudTalkHashDirty: useHashCheck(),
			supportSessionState: useActiveSession(),
		}
	},

	data() {
		return {
			savedLastMessageMap: {},
			defaultPageTitle: false,
			loading: false,
			isRefreshingCurrentConversation: false,
			recordingConsentGiven: false,
		}
	},

	computed: {
		windowIsVisible() {
			return this.$store.getters.windowIsVisible()
		},
		isFullscreen() {
			return this.$store.getters.isFullscreen()
		},

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
		 * Keeps a list for all last message ids
		 *
		 * @return {object} Map with token => lastMessageId
		 */
		lastMessageMap() {
			const conversationList = this.$store.getters.conversationsList
			if (conversationList.length === 0) {
				return {}
			}

			const lastMessage = {}
			conversationList.forEach(conversation => {
				lastMessage[conversation.token] = 0
				if (conversation.lastMessage) {
					const currentActorIsAuthor = conversation.lastMessage.actorType === this.$store.getters.getActorType()
						&& conversation.lastMessage.actorId === this.$store.getters.getActorId()
					if (currentActorIsAuthor) {
						// Set a special value when the actor is the author so we can skip it.
						// Can't use 0 though because hidden commands result in 0
						// and they would hide other previously posted new messages
						lastMessage[conversation.token] = -1
					} else {
						lastMessage[conversation.token] = Math.max(
							conversation.lastMessage && conversation.lastMessage.id ? conversation.lastMessage.id : 0,
							this.$store.getters.getLastKnownMessageId(conversation.token) ? this.$store.getters.getLastKnownMessageId(conversation.token) : 0,
						)
					}
				}
			})
			return lastMessage
		},

		/**
		 * @return {boolean} Returns true, if
		 * - a conversation is newly added to lastMessageMap
		 * - a conversation has a different last message id then previously
		 */
		atLeastOneLastMessageIdChanged() {
			let modified = false
			Object.keys(this.lastMessageMap).forEach(token => {
				if (!this.savedLastMessageMap[token] // Conversation is new
					|| (this.savedLastMessageMap[token] !== this.lastMessageMap[token] // Last message changed
						&& this.lastMessageMap[token] !== -1)) { // But is not from the current user
					modified = true
				}
			})

			return modified
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

		disableKeyboardShortcuts() {
			return OCP.Accessibility.disableKeyboardShortcuts()
		},
	},

	watch: {
		atLeastOneLastMessageIdChanged() {
			if (this.windowIsVisible) {
				return
			}

			this.setPageTitle(this.getConversationName(this.token), this.atLeastOneLastMessageIdChanged)
		},

		token(newValue, oldValue) {
			// Collapse the sidebar if it's a one to one conversation
			if (this.isOneToOne || BrowserStorage.getItem('sidebarOpen') === 'false' || this.isMobile) {
				this.$store.dispatch('hideSidebar')
			} else if (BrowserStorage.getItem('sidebarOpen') === 'true') {
				this.$store.dispatch('showSidebar')
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
		}
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

	beforeDestroy() {
		if (!getCurrentUser()) {
			EventBus.$off('should-refresh-conversations', this.debounceRefreshCurrentConversation)
		}
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)

		unsubscribe('notifications:action:execute', this.interceptNotificationActions)
	},

	beforeMount() {
		if (!getCurrentUser()) {
			EventBus.$once('joined-conversation', () => {
				this.fixmeDelayedSetupOfGuestUsers()
			})
			EventBus.$on('should-refresh-conversations', this.debounceRefreshCurrentConversation)
		}

		if (this.$route.name === 'conversation') {
			// Update current token in the token store
			this.$store.dispatch('updateToken', this.$route.params.token)
			// Automatically join the conversation as well
			this.$store.dispatch('joinConversation', { token: this.$route.params.token })
		}

		window.addEventListener('resize', this.onResize)
		document.addEventListener('visibilitychange', this.changeWindowVisibility)

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

		EventBus.$on('switch-to-conversation', (params) => {
			if (this.isInCall) {
				this.$store.dispatch('setForceCallView', true)

				const enableAudio = !BrowserStorage.getItem('audioDisabled_' + this.token)
				const enableVideo = !BrowserStorage.getItem('videoDisabled_' + this.token)
				const enableVirtualBackground = !!BrowserStorage.getItem('virtualBackgroundEnabled_' + this.token)
				const virtualBackgroundType = BrowserStorage.getItem('virtualBackgroundType_' + this.token)
				const virtualBackgroundBlurStrength = BrowserStorage.getItem('virtualBackgroundBlurStrength_' + this.token)
				const virtualBackgroundUrl = BrowserStorage.getItem('virtualBackgroundUrl_' + this.token)

				EventBus.$once('joined-conversation', async ({ token }) => {
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

					this.$store.dispatch('setForceCallView', false)
				})
			}

			this.$router.push({ name: 'conversation', params: { token: params.token, skipLeaveWarning: true } })
		})

		EventBus.$on('conversations-received', (params) => {
			if (this.$route.name === 'conversation'
				&& !this.$store.getters.conversation(this.token)) {
				if (!params.singleConversation) {
					console.info('Conversations received, but the current conversation is not in the list, trying to get potential public conversation manually')
					this.refreshCurrentConversation()
				} else {
					console.info('Conversation received, but the current conversation is not in the list. Redirecting to not found page')
					this.$router.push({ name: 'notfound', params: { skipLeaveWarning: true } })
					this.$store.dispatch('updateToken', '')
				}
			}
		})

		/**
		 * Listens to the conversationsReceived globalevent, emitted by the conversationsList
		 * component each time a new batch of conversations is received and processed in
		 * the store.
		 */
		EventBus.$once('conversations-received', () => {
			if (this.$route.name === 'conversation') {
				// Adjust the page title once the conversation list is loaded
				this.setPageTitle(this.getConversationName(this.token), false)
			}

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

			/**
			 * This runs whenever the new route is a conversation.
			 */
			if (to.name === 'conversation') {
				// Update current token in the token store
				this.$store.dispatch('updateToken', to.params.token)
			}

			/**
			 * Fires a global event that tells the whole app that the route has changed. The event
			 * carries the from and to objects as payload
			 */
			EventBus.$emit('route-change', { from, to })

			next()
		}

		/**
		 * Global before guard, this is called whenever a navigation is triggered.
		 */
		Router.beforeEach((to, from, next) => {
			if (this.warnLeaving && !to.params?.skipLeaveWarning) {
				OC.dialogs.confirmDestructive(
					t('spreed', 'Navigating away from the page will leave the call in {conversation}', {
						conversation: this.getConversationName(this.token),
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
			} else {
				beforeRouteChangeListener(to, from, next)
			}
		})

		Router.afterEach((to) => {
			/**
			 * Change the page title only after the route was changed
			 */
			if (to.name === 'conversation') {
				// Page title
				const nextConversationName = this.getConversationName(to.params.token)
				this.setPageTitle(nextConversationName)
			} else if (to.name === 'notfound') {
				this.setPageTitle('')
			}

			if (to.hash === '#direct-call') {
				emit('talk:media-settings:show')
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
		if (!IS_DESKTOP) {
			checkBrowser()
		}
		// Check sidebar status in previous sessions
		if (BrowserStorage.getItem('sidebarOpen') === 'false') {
			this.$store.dispatch('hideSidebar')
		} else if (BrowserStorage.getItem('sidebarOpen') === 'true') {
			this.$store.dispatch('showSidebar')
		}
		if (this.$route.name === 'root' && this.isMobile) {
			await this.$nextTick()
			emit('toggle-navigation', {
				open: true,
			})
		}

		if (this.$route.hash === '#direct-call') {
			emit('talk:media-settings:show')
		}

		subscribe('notifications:action:execute', this.interceptNotificationActions)
		subscribe('notifications:notification:received', this.interceptNotificationReceived)
	},

	methods: {
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
		interceptNotificationActions(event) {
			if (event.notification.app !== 'spreed' || event.action.type !== 'WEB') {
				return
			}

			const [, load] = event.action.url.split('/call/')
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

			if (event.notification.objectType === 'chat') {
				if (event.notification.subjectRichParameters?.reaction) {
					// Ignore reaction notifications in case of one-to-one and always-notify
					return
				}

				this.$store.dispatch('updateConversationLastMessageFromNotification', {
					notification: event.notification,
				})
			}

			if (event.notification.objectType === 'call') {
				this.$store.dispatch('updateCallStateFromNotification', {
					notification: event.notification,
				})
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

		debounceRefreshCurrentConversation: debounce(function() {
			if (!this.isRefreshingCurrentConversation) {
				this.refreshCurrentConversation()
			}
		}, 3000),

		changeWindowVisibility() {
			this.$store.dispatch('setWindowVisibility', !document.hidden)
			if (this.windowIsVisible) {
				// Remove the potential "*" marker for unread chat messages
				let title = this.getConversationName(this.token)
				if (window.document.title.indexOf(t('spreed', 'Duplicate session')) === 0) {
					title = t('spreed', 'Duplicate session')
				}
				this.setPageTitle(title, false)
			} else {
				// Copy the last message map to the saved version,
				// this will be our reference to check if any chat got a new
				// message since the last visit
				this.savedLastMessageMap = this.lastMessageMap
			}
		},

		/**
		 * Set the page title to the conversation name
		 *
		 * @param {string} title Prefix for the page title e.g. conversation name
		 * @param {boolean} showAsterix Prefix for the page title e.g. conversation name
		 */
		setPageTitle(title, showAsterix) {
			if (this.defaultPageTitle === false) {
				// On the first load we store the current page title "Talk - Nextcloud",
				// so we can append it every time again
				this.defaultPageTitle = window.document.title
				// Coming from a "Duplicate session - Talk - …" page?
				if (this.defaultPageTitle.indexOf(' - ' + t('spreed', 'Talk') + ' - ') !== -1) {
					this.defaultPageTitle = this.defaultPageTitle.substring(this.defaultPageTitle.indexOf(' - ' + t('spreed', 'Talk') + ' - ') + 3)
				}
				// When a conversation is opened directly, the "Talk - " part is
				// missing from the title
				if (!IS_DESKTOP && this.defaultPageTitle.indexOf(t('spreed', 'Talk') + ' - ') !== 0) {
					this.defaultPageTitle = t('spreed', 'Talk') + ' - ' + this.defaultPageTitle
				}
			}

			let newTitle = this.defaultPageTitle
			if (title !== '') {
				newTitle = `${title} - ${newTitle}`
			}
			if (showAsterix && !newTitle.startsWith('* ')) {
				newTitle = '* ' + newTitle
			}
			window.document.title = newTitle
		},

		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},

		/**
		 * Get a conversation's name.
		 *
		 * @param {string} token The conversation's token
		 * @return {string} The conversation's name
		 */
		getConversationName(token) {
			if (!this.$store.getters.conversation(token)) {
				return ''
			}

			return this.$store.getters.conversation(token).displayName
		},

		async fetchSingleConversation(token) {
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
				EventBus.$emit('conversations-received', {
					singleConversation: true,
				})
			} catch (exception) {
				console.info('Conversation received, but the current conversation is not in the list. Redirecting to /apps/spreed')
				this.$router.push({ name: 'notfound', params: { skipLeaveWarning: true } })
				this.$store.dispatch('updateToken', '')
				this.$store.dispatch('hideSidebar')
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
