<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="talkChatTab">
		<div v-if="fileInfo && fileInfo.isDirectory()" class="emptycontent">
			<div class="icon icon-talk" />
			<h2>Conversations are not available for folders</h2>
		</div>
		<div v-else-if="isTalkSidebarSupportedForFile === undefined" class="emptycontent ui-not-ready-placeholder">
			<div class="icon icon-loading" />
		</div>
		<div v-else-if="!isTalkSidebarSupportedForFile" class="emptycontent file-not-shared">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<p>{{ t('spreed', 'Share this file with others to discuss it') }}</p>
			<button class="primary" @click="openSharingTab">
				{{ t('spreed', 'Share this file') }}
			</button>
		</div>
		<div v-else-if="isTalkSidebarSupportedForFile && !token" class="emptycontent room-not-joined">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<button class="primary" @click="joinConversation">
				{{ t('spreed', 'Join conversation') }}
			</button>
		</div>
		<ChatView v-else :token="token" />
	</div>
</template>

<script>

import { getFileConversation } from './services/filesIntegrationServices'
import { joinConversation, leaveConversation } from './services/participantsService'
import CancelableRequest from './utils/cancelableRequest'
import { getCurrentUser } from '@nextcloud/auth'
import Axios from '@nextcloud/axios'
import ChatView from './components/ChatView'

export default {

	name: 'FilesSidebarTabApp',

	components: {
		ChatView,
	},

	data() {
		return {
			// needed for reactivity
			Talk: OCA.Talk,
			/**
			 * Stores the cancel function returned by `cancelableLookForNewMessages`,
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
		token() {
			return this.$store.getters.getToken()
		},
		fileIdForToken() {
			return this.$store.getters.getFileIdForToken()
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
	},

	beforeMount() {
		this.$store.dispatch('setCurrentUser', getCurrentUser())
	},

	methods: {
		async joinConversation() {
			await this.getFileConversation()

			joinConversation(this.token)
		},

		leaveConversation() {
			leaveConversation(this.token)

			this.$store.dispatch('updateTokenAndFileIdForToken', {
				newToken: null,
				newFileId: null,
			})
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
				this.$store.dispatch('updateTokenAndFileIdForToken', {
					newToken: response.data.ocs.data.token,
					newFileId: this.fileId,
				})
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				} else {
					console.debug(exception)
				}
			}
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
				this.isTalkSidebarSupportedForFile = (await getFileConversation({ fileId: fileInfo.id })) || false

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
				this.isTalkSidebarSupportedForFile = (await getFileConversation({ fileId: fileInfo.id })) || false

				return
			}

			this.isTalkSidebarSupportedForFile = true
		},

		openSharingTab() {
			OCA.Files.Sidebar.setActiveTab('sharing')
		},
	},
}
</script>

<style scoped>

</style>
