<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<FilesSidebarCallView v-if="isInFile && isInCall" />
	<FilesSidebarChatView />
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import FilesSidebarCallView from './views/FilesSidebarCallView.vue'
import FilesSidebarChatView from './views/FilesSidebarChatView.vue'
import { useIsInCall } from './composables/useIsInCall.js'
import { useRecordingStatusSync } from './composables/useRecordingStatusSync.ts'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import { EventBus } from './services/EventBus.ts'
import { leaveConversationSync } from './services/participantsService.js'
import SessionStorage from './services/SessionStorage.js'
import { useActorStore } from './stores/actor.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'
import { signalingKill, signalingWebRtcKill } from './utils/webrtc/index.js'

let fetchCurrentConversationIntervalId

export default {
	name: 'FilesSidebarTabApp',

	components: {
		FilesSidebarCallView,
		FilesSidebarChatView,
	},

	props: {
		node: {
			type: Object, /* @nextcloud/files/INode */
			required: true,
		},

		folder: {
			type: Object, /* @nextcloud/files/IFolder */
			required: true,
		},

		view: {
			type: Object, /* @nextcloud/files/IView */
			required: true,
		},

		active: {
			type: Boolean,
			required: true,
		},

		token: {
			type: String,
			required: true,
		},
	},

	setup() {
		useRecordingStatusSync()

		return {
			isInCall: useIsInCall(),
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			actorStore: useActorStore(),
			tokenStore: useTokenStore(),
		}
	},

	computed: {
		fileId() {
			return this.node.fileid
		},

		fileIdForToken() {
			return this.tokenStore.fileIdForToken
		},

		/**
		 * Returns whether the sidebar is opened in the file of the current
		 * conversation or not.
		 *
		 * Note that false is returned too when the sidebar is closed, even if
		 * the conversation is active in the current file.
		 *
		 * @return {boolean} true if the sidebar is opened in the file, false
		 *          otherwise.
		 */
		isInFile() {
			return this.fileId === this.fileIdForToken
		},

		warnLeaving() {
			return !this.isLeavingAfterSessionIssue && this.isInCall
		},
	},

	watch: {
		active: {
			immediate: true,
			handler(active) {
				this.forceTabsContentStyleWhenChatTabIsActive(active)
			},
		},
	},

	beforeMount() {
		this.actorStore.setCurrentUser(getCurrentUser())
		this.tokenStore.updateTokenAndFileIdForToken(this.token, this.node.fileid)
		this.joinConversation()

		window.addEventListener('beforeunload', this.preventUnload)
		window.addEventListener('unload', this.syncLeaveConversation)
	},

	async beforeUnmount() {
		EventBus.off('should-refresh-conversations', this.fetchCurrentConversation)
		EventBus.off('signaling-participant-list-changed', this.fetchCurrentConversation)

		window.clearInterval(fetchCurrentConversationIntervalId)
		fetchCurrentConversationIntervalId = null

		try {
			await this.$store.dispatch('leaveConversation', { token: this.token })
		} catch (error) {
			console.error(error)
		}

		window.removeEventListener('beforeunload', this.preventUnload)
		window.removeEventListener('unload', this.syncLeaveConversation)
		this.syncLeaveConversation()
	},

	unmounted() {
	},

	methods: {
		t,
		async joinConversation() {
			checkBrowser()

			// TODO: move to store under a special action ?

			// Remove the conversation to ensure that the old data is not used
			// before fetching it again if this conversation is joined again.
			await this.$store.dispatch('deleteConversation', this.token)
			// Remove the participant to ensure that it will be set again fresh
			// if this conversation is joined again.
			await this.$store.dispatch('purgeParticipantsStore', this.token)

			await this.$router.push({ name: 'conversation', params: { token: this.token } })
			await this.$store.dispatch('joinConversation', { token: this.token })

			// The current participant (which is automatically set when fetching
			// the current conversation) is needed for the MessagesList to start
			// getting the messages, and both the current conversation and the
			// current participant are needed for CallButton. No need to wait
			// for it, but fetching the conversation needs to be done once the
			// user has joined the conversation (otherwise only limited data
			// would be received if the user was not a participant of the
			// conversation yet).
			this.fetchCurrentConversation()

			// FIXME The participant will not be updated with the server data
			// when the conversation is got again (as "addParticipantOnce" is
			// used), although that should not be a problem given that only the
			// "inCall" flag (which is locally updated when joining and leaving
			// a call) is currently used.
			if (loadState('spreed', 'signaling_mode') !== 'internal') {
				EventBus.on('should-refresh-conversations', this.fetchCurrentConversation)
				EventBus.on('signaling-participant-list-changed', this.fetchCurrentConversation)
			} else {
				// The "should-refresh-conversations" event is triggered only when
				// the external signaling server is used; when the internal
				// signaling server is used periodic polling has to be used
				// instead.
				fetchCurrentConversationIntervalId = window.setInterval(this.fetchCurrentConversation, 30000)
			}
		},

		async fetchCurrentConversation() {
			if (!this.token) {
				return
			}

			await this.$store.dispatch('fetchConversation', { token: this.token })
		},

		syncLeaveConversation() {
			console.info('Navigating away, leaving conversation')
			if (this.token) {
				this.tokenStore.updateTokenAndFileIdForToken('', null)
				SessionStorage.removeItem('joined_conversation')
				// We have to do this synchronously, because in unload and beforeunload
				// Promises, async and await are prohibited.
				signalingKill()
				signalingWebRtcKill()
				if (!this.isLeavingAfterSessionIssue) {
					leaveConversationSync(this.token)
				}
			}
		},

		preventUnload(event) {
			if (!this.warnLeaving) {
				return
			}

			event.preventDefault()
		},

		/**
		 * Dirty hack to set the style in the tabs container.
		 *
		 * This is needed to force the scroll bars on the tabs container instead
		 * of on the whole sidebar.
		 *
		 * Additionally a minimum height is forced to ensure that the height of
		 * the chat view will be at least 300px, even if the info view is large
		 * and the screen short; in that case a scroll bar will be shown for the
		 * sidebar, but even if that looks really bad it is better than an
		 * unusable chat view.
		 */
		forceTabsContentStyleWhenChatTabIsActive() {
			const tabs = document.querySelector('.app-sidebar-tabs')
			const tabsContent = document.querySelector('.app-sidebar-tabs__content')

			if (this.active) {
				this.savedTabsMinHeight = tabs.style.minHeight
				this.savedTabsOverflow = tabs.style.overflow
				this.savedTabsContentOverflow = tabsContent.style.overflow
				this.savedTabsContentStyle = true

				tabs.style.minHeight = '300px'
				tabs.style.overflow = 'hidden'
				tabsContent.style.overflow = 'hidden'
			} else if (this.savedTabsContentStyle) {
				tabs.style.minHeight = this.savedTabsMinHeight
				tabs.style.overflow = this.savedTabsOverflow
				tabsContent.style.overflow = this.savedTabsContentOverflow

				delete this.savedTabsMinHeight
				delete this.savedTabsOverflow
				delete this.savedTabsContentOverflow
				this.savedTabsContentStyle = false
			}
		},
	},
}
</script>

<style>
/* FIXME: Align styles of NcModal header with NcDialog header. Remove if all are migrated */
body .modal-wrapper h2.nc-dialog-alike-header {
	font-size: 21px;
	text-align: center;
	height: fit-content;
	min-height: var(--default-clickable-area);
	line-height: var(--default-clickable-area);
	overflow-wrap: break-word;
	margin-block: 0 12px;
}
</style>
