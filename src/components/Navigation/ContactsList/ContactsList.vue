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
	<ul class="contacts-list">
		<AppContentListItem
			v-for="item of contacts"
			:key="item.id"
			:title="item.label"
			@click="createAndJoinConversation(item.id)">
			<Avatar
				slot="icon"
				:size="44"
				:user="item.id"
				:display-name="item.label" />
		</AppContentListItem>
	</ul>
</template>

<script>
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import AppContentListItem from '../ConversationsList/AppContentListItem/AppContentListItem'
import { createOneToOneConversation } from '../../../services/conversationsService'

export default {
	name: 'ContactsList',
	components: {
		Avatar,
		AppContentListItem
	},
	props: {
		contacts: {
			type: Object,
			required: true
		},
		isLoading: {
			type: Boolean,
			default: false
		}
	},
	methods: {
		/**
		 * Create a new conversation with the selected user.
		 * @param {string} userId the ID of the clicked user.
		 */
		async createAndJoinConversation(userId) {
			console.debug(userId)
			const response = await createOneToOneConversation(userId)
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			console.debug(response)
		}
	}
}
</script>

<style lang="scss" scoped>
.ellipsis {
	text-overflow: ellipsis;
}
.contacts-list {
	overflow: visible;
	display: block;
}

</style>
