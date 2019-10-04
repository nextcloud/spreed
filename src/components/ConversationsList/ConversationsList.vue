<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me
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
	<ul class="app-navigation">
		<Multiselect
			:options="conversationsList"
			label="displayName"
			track-by="user"
			:user-select="true"
			@input="handleInput"
			style="width: 250px" />
		<AppContentListItem
			v-for="item of conversationsList"
			:key="item.id"
			:to="{ name: 'conversation', params: { token: item.token }}"
			:title="item.displayName"
			@click="joinConversation(item.token)">
			<Avatar
				slot="icon"
				:size="44"
				:user="item.displayName"
				:display-name="item.displayName" />
			<template slot="subtitle">
				{{ item.lastMessage.message }}
			</template>
			<AppNavigationCounter
				slot="counter"
				:highlighted="true">
				3
			</AppNavigationCounter>
			<template slot="actions">
				<ActionButton
					icon="icon-edit"
					@click="alert('Edit')">
					Edit
				</ActionButton>
				<ActionButton
					icon="icon-delete"
					@click="alert('Delete')">
					Delete
				</ActionButton>
				<ActionLink
					icon="icon-external"
					title="Link"
					href="https://nextcloud.com" />
			</template>
		</AppContentListItem>
	</ul>
</template>

<script>
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import AppNavigationCounter from 'nextcloud-vue/dist/Components/AppNavigationCounter'
import AppContentListItem from 'nextcloud-vue/dist/Components/AppContentListItem'
import Multiselect from 'nextcloud-vue/dist/Components/Multiselect'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import { fetchConversations } from '../../services/conversationsService'
import { joinConversation } from '../../services/participantsService'

export default {
	name: 'ConversationsList',
	components: {
		Avatar,
		AppNavigationCounter,
		ActionButton,
		AppContentListItem,
		Multiselect
	},
	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},
		conversations() {
			return this.$store.getters.conversations
		}
	},
	async beforeMount() {
		/** Fetches the conversations from the server and then
		 * adds them one by one to the store.
		 */
		const conversations = await fetchConversations()
		conversations.data.ocs.data.forEach(conversation => {
			this.$store.dispatch('addConversation', conversation)
		})
	},
	methods: {
		async joinConversation(token) {
			await joinConversation(token)
		},
		handleInput(payload) {
			const selectedConversationToken = payload.token
			this.joinConversation(selectedConversationToken)
			this.$router.push({ path: `/call/${selectedConversationToken}` })
		}
	}
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}

.ellipsis {
	text-overflow: ellipsis;
}
</style>
