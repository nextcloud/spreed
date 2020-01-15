<!--
  - @copyright Copyright (c) 2020, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<aside v-if="isOpen" id="talk-sidebar">
		<div v-if="!conversation" class="emptycontent room-not-joined">
			<div class="icon icon-talk" />
			<h2>{{ t('spreed', 'Discuss this file') }}</h2>
			<button class="primary" @click="joinConversation">
				{{ t('spreed', 'Join conversation') }}
			</button>
		</div>
		<div v-else class="emptycontent">
			<div class="icon icon-talk" />
			<h2>Conversation joined</h2>
		</div>
	</aside>
</template>

<script>
import { EventBus } from './services/EventBus'
import { fetchConversation } from './services/conversationsService'
import { getPublicShareConversationToken } from './services/filesIntegrationServices'
import { joinConversation } from './services/participantsService'
import { getSignaling } from './utils/webrtc/index'

export default {

	name: 'PublicShareSidebar',

	props: {
		shareToken: {
			type: String,
			required: true,
		},

		state: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			fetchCurrentConversationIntervalId: null,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversations[this.token]
		},

		isOpen() {
			return this.state.isOpen
		},
	},

	methods: {

		async joinConversation() {
			await this.getPublicShareConversationToken()

			await joinConversation(this.token)

			// No need to wait for it, but fetching the conversation needs to be
			// done once the user has joined the conversation (otherwise only
			// limited data would be received if the user was not a participant
			// of the conversation yet).
			this.fetchCurrentConversation()

			// FIXME The participant will not be updated with the server data
			// when the conversation is got again (as "addParticipantOnce" is
			// used), although that should not be a problem given that only the
			// "inCall" flag (which is locally updated when joining and leaving
			// a call) is currently used.
			const signaling = await getSignaling()
			if (signaling.url) {
				EventBus.$on('shouldRefreshConversations', this.fetchCurrentConversation)
			} else {
				// The "shouldRefreshConversations" event is triggered only when
				// the external signaling server is used; when the internal
				// signaling server is used periodic polling has to be used
				// instead.
				this.fetchCurrentConversationIntervalId = window.setInterval(this.fetchCurrentConversation, 30000)
			}
		},

		async getPublicShareConversationToken() {
			const token = await getPublicShareConversationToken(this.shareToken)

			this.$store.dispatch('updateToken', token)
		},

		async fetchCurrentConversation() {
			if (!this.token) {
				return
			}

			try {
				const response = await fetchConversation(this.token)
				this.$store.dispatch('addConversation', response.data.ocs.data)
			} catch (exception) {
				window.clearInterval(this.fetchCurrentConversationIntervalId)

				this.$store.dispatch('deleteConversationByToken', this.token)
				this.$store.dispatch('updateToken', '')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
/* Properties based on the app-sidebar */
#talk-sidebar {
	position: relative;
	flex-shrink: 0;
	width: 27vw;
	min-width: 300px;
	max-width: 500px;

	background: var(--color-main-background);
	border-left: 1px solid var(--color-border);

	overflow-x: hidden;
	overflow-y: auto;
	z-index: 1500;
}
</style>
