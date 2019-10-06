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
	<ul>
		<AppContentListItem
			v-for="item of contacts"
			:key="item.id"
			:to="{ name: 'conversation', params: { token: item.token }}"
			:title="item.id">
			<Avatar
				slot="icon"
				:size="44"
				:user="item.id"
				:display-name="item.id" />
		</AppContentListItem>
	</ul>
</template>

<script>
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import AppContentListItem from 'nextcloud-vue/dist/Components/AppContentListItem'

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
		 * here I will have to create a new conversation
		 */
		// async joinConversation(token) {
		// 	await joinConversation(token)
		// },
		// handleInput(payload) {
		// 	const selectedConversationToken = payload.token
		// 	this.joinConversation(selectedConversationToken)
		// 	this.$router.push({ path: `/call/${selectedConversationToken}` })
		// }
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
