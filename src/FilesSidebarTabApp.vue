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
		<p v-else>
			Talk tab coming soon
		</p>
	</div>
</template>

<script>

import { getFileConversation } from './services/filesIntegrationServices'
import CancelableRequest from './utils/cancelableRequest'
import Axios from '@nextcloud/axios'

export default {

	name: 'FilesSidebarTabApp',

	data() {
		return {
			// needed for reactivity
			Talk: OCA.Talk,
			/**
			 * Stores the cancel function returned by `cancelableLookForNewMessages`,
			 */
			cancelGetFileConversation: () => {},
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
	},

	methods: {
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
				this.$store.dispatch('updateToken', response.data.ocs.data.token)
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				} else {
					console.debug(exception)
				}
			}
		},
	},
}
</script>

<style scoped>

</style>
