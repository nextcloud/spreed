<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="talkChatTab">
		<div v-if="isTalkSidebarSupportedForFile === undefined" class="emptycontent ui-not-ready-placeholder">
			<div class="icon icon-loading" />
		</div>
		<div v-else-if="!isTalkSidebarSupportedForFile" class="emptycontent file-not-shared">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<p>{{ t('spreed', 'Share this file with others to discuss it') }}</p>
			<NcButton variant="primary" @click="openSharingTab">
				{{ t('spreed', 'Share this file') }}
			</NcButton>
		</div>
		<div v-else-if="isTalkSidebarSupportedForFile && !token" class="emptycontent room-not-joined">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<NcButton variant="primary" @click="joinConversation">
				{{ t('spreed', 'Join conversation') }}
			</NcButton>
		</div>
		<FilesSidebarChatView v-else />
	</div>
</template>

<script>

import { getCurrentUser } from '@nextcloud/auth'
import Axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import LoadingComponent from './components/LoadingComponent.vue'
import { useGetToken } from './composables/useGetToken.ts'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import { EventBus } from './services/EventBus.ts'
import { getFileConversation } from './services/filesIntegrationServices.ts'
import {
	leaveConversationSync,
} from './services/participantsService.js'
import { useActorStore } from './stores/actor.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'
import CancelableRequest from './utils/cancelableRequest.js'
import { signalingKill } from './utils/webrtc/index.js'

export default {

	name: 'FilesSidebarTabApp',

	components: {
		FilesSidebarChatView: () => ({
			component: import(/* webpackChunkName: "files-sidebar-tab-chunk" */'./views/FilesSidebarChatView.vue'),
			loading: {
				render: (h) => h(LoadingComponent, { class: 'tab-loading' }),
			},
		}),

		NcButton,
	},

	setup() {
		return {
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			actorStore: useActorStore(),
			token: useGetToken(),
			tokenStore: useTokenStore(),
		}
	},

	data() {
		return {
			// needed for reactivity
			Talk: OCA.Talk,
			sidebarState: OCA.Files.Sidebar.state,
			/**
			 * Stores the cancel function returned by `cancelablePollNewMessages`,
			 */
			cancelGetFileConversation: () => {},
			isTalkSidebarSupportedForFile: undefined,
		}
	},

	computed: {
		fileInfo() {
			return this.Talk.fileInfo || {}
		},

		fileId() {
			return this.fileInfo.id
		},

		fileIdForToken() {
			return this.tokenStore.fileIdForToken
		},

		isChatTheActiveTab() {
			// FIXME check for empty active tab is currently needed because the
			// activeTab is not set when opening the sidebar from the "Details"
			// action (which opens the first tab, which is the Chat tab).
			return !this.sidebarState.activeTab || this.sidebarState.activeTab === 'chat'
		},
	},

	watch: {
		fileInfo: {
			immediate: true,
			handler(fileInfo) {
				if (this.token && (!fileInfo || fileInfo.id !== this.fileIdForToken)) {
					this.leaveConversation()
				}

				this.setTalkSidebarSupportedForFile(fileInfo)
			},
		},

		isChatTheActiveTab: {
			immediate: true,
			handler(isChatTheActiveTab) {
				this.forceTabsContentStyleWhenChatTabIsActive(isChatTheActiveTab)
				// recheck the file info in case the sharing info was changed
				this.setTalkSidebarSupportedForFile(this.fileInfo)
			},
		},
	},

	created() {
		// The fetchCurrentConversation event handler/callback is started and
		// stopped from different FilesSidebarTabApp instances, so it needs to
		// be stored in a common place. Moreover, as the bound method would be
		// overriden when a new instance is created the one used as handler is
		// a wrapper that calls the latest bound method. This makes possible to
		// register and unregister it from different instances.
		if (!OCA.Talk.fetchCurrentConversationWrapper) {
			OCA.Talk.fetchCurrentConversationWrapper = function() {
				OCA.Talk.fetchCurrentConversationBound()
			}
		}

		OCA.Talk.fetchCurrentConversationBound = this.fetchCurrentConversation.bind(this)
	},

	beforeMount() {
		this.actorStore.setCurrentUser(getCurrentUser())

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
	},

	methods: {
		t,
		async joinConversation() {
			checkBrowser()

			try {
				await this.getFileConversation()
			} catch (error) {
				console.debug('Could not get file conversation. Is it a file and shared?')
				return
			}

			// TODO: move to store under a special action ?

			// Remove the conversation to ensure that the old data is not used
			// before fetching it again if this conversation is joined again.
			await this.$store.dispatch('deleteConversation', this.token)
			// Remove the participant to ensure that it will be set again fresh
			// if this conversation is joined again.
			await this.$store.dispatch('purgeParticipantsStore', this.token)

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
				EventBus.on('should-refresh-conversations', OCA.Talk.fetchCurrentConversationWrapper)
				EventBus.on('signaling-participant-list-changed', OCA.Talk.fetchCurrentConversationWrapper)
			} else {
				// The "should-refresh-conversations" event is triggered only when
				// the external signaling server is used; when the internal
				// signaling server is used periodic polling has to be used
				// instead.
				OCA.Talk.fetchCurrentConversationIntervalId = window.setInterval(OCA.Talk.fetchCurrentConversationWrapper, 30000)
			}
		},

		leaveConversation() {
			EventBus.off('should-refresh-conversations', OCA.Talk.fetchCurrentConversationWrapper)
			EventBus.off('signaling-participant-list-changed', OCA.Talk.fetchCurrentConversationWrapper)
			window.clearInterval(OCA.Talk.fetchCurrentConversationIntervalId)

			this.$store.dispatch('leaveConversation', { token: this.token })

			this.tokenStore.updateTokenAndFileIdForToken('', null)
		},

		async getFileConversation() {
			// Clear previous requests if there's one pending
			this.cancelGetFileConversation('canceled')
			// Get a new cancelable request function and cancel function pair
			const { request, cancel } = CancelableRequest(getFileConversation)
			// Assign the new cancel function to our data value
			this.cancelGetFileConversation = cancel
			// Make the request
			try {
				const response = await request({ fileId: this.fileId })
				this.tokenStore.updateTokenAndFileIdForToken(response.data.ocs.data.token, this.fileId)
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				} else {
					throw exception
				}
			}
		},

		async fetchCurrentConversation() {
			if (!this.token) {
				return
			}

			await this.$store.dispatch('fetchConversation', { token: this.token })
		},

		/**
		 * Sets whether the Talk sidebar is supported for the file or not.
		 *
		 * In some cases it is not possible to know if the Talk sidebar is
		 * supported for the file or not just from the data in the FileInfo (for
		 * example, for files in a folder shared by the current user). Due to
		 * that this function is asynchronous; isTalkSidebarSupportedForFile
		 * will be set as soon as possible (in some cases, immediately) with
		 * either true or false, depending on whether the Talk sidebar is
		 * supported for the file or not.
		 *
		 * The Talk sidebar is supported for a file if the file is shared with
		 * the current user or by the current user to another user (as a user,
		 * group...), or if the file is a descendant of a folder that meets
		 * those conditions.
		 *
		 * @param {OCA.Files.FileInfo} fileInfo the FileInfo to check
		 */
		async setTalkSidebarSupportedForFile(fileInfo) {
			this.isTalkSidebarSupportedForFile = undefined

			if (!fileInfo) {
				this.isTalkSidebarSupportedForFile = false

				return
			}

			if (fileInfo.get('type') === 'dir') {
				this.isTalkSidebarSupportedForFile = false

				return
			}

			if (fileInfo.get('shareOwnerId')) {
				// Shared with me
				// TODO How to check that it is not a remote share? At least for
				// local shares "shareTypes" is not defined when shared with me.
				this.isTalkSidebarSupportedForFile = true

				return
			}

			if (!fileInfo.get('shareTypes')) {
				// When it is not possible to know whether the Talk sidebar is
				// supported for a file or not only from the data in the
				// FileInfo it is necessary to query the server.
				// FIXME If the file is shared this will create the conversation
				// if it does not exist yet.
				try {
					this.isTalkSidebarSupportedForFile = (await getFileConversation({ fileId: fileInfo.id })) || false
				} catch (error) {
					this.isTalkSidebarSupportedForFile = false
				}

				return
			}

			const shareTypes = fileInfo.get('shareTypes').filter(function(shareType) {
				// Ensure that shareType is an integer (as in the past shareType
				// could be an integer or a string depending on whether the
				// Sharing tab was opened or not).
				shareType = parseInt(shareType)
				return shareType === OC.Share.SHARE_TYPE_USER
					|| shareType === OC.Share.SHARE_TYPE_GROUP
					|| shareType === OC.Share.SHARE_TYPE_CIRCLE
					|| shareType === OC.Share.SHARE_TYPE_ROOM
					|| shareType === OC.Share.SHARE_TYPE_LINK
					|| shareType === OC.Share.SHARE_TYPE_EMAIL
			})

			if (shareTypes.length === 0) {
				// When it is not possible to know whether the Talk sidebar is
				// supported for a file or not only from the data in the
				// FileInfo it is necessary to query the server.
				// FIXME If the file is shared this will create the conversation
				// if it does not exist yet.
				try {
					this.isTalkSidebarSupportedForFile = (await getFileConversation({ fileId: fileInfo.id })) || false
				} catch (error) {
					this.isTalkSidebarSupportedForFile = false
				}

				return
			}

			this.isTalkSidebarSupportedForFile = true
		},

		openSharingTab() {
			OCA.Files.Sidebar.setActiveTab('sharing')
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
		 *
		 * @param {boolean} isChatTheActiveTab whether the active tab is the
		 *        chat tab or not.
		 */
		forceTabsContentStyleWhenChatTabIsActive(isChatTheActiveTab) {
			const tabs = document.querySelector('.app-sidebar-tabs')
			const tabsContent = document.querySelector('.app-sidebar-tabs__content')

			if (isChatTheActiveTab) {
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
/* FIXME: remove after https://github.com/nextcloud-libraries/nextcloud-vue/pull/4959 is released */
body .modal-wrapper * {
	box-sizing: border-box;
}

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

<style scoped>
.talkChatTab {
	height: 100%;

	display: flex;
	flex-grow: 1;
	flex-direction: column;
}

.emptycontent {
	/* Override default top margin set in server and center vertically
	 * instead. */
	margin-top: unset;

	height: 100%;

	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}

.tab-loading {
	height: 100%;
}
</style>
