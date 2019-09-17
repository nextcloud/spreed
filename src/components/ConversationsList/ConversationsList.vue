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
	<ul class="app-navigation">
		<AppNavigationItem
			v-for="item of conversationsList"
			:key="item.id"
			:title="item.displayName">
			<Avatar slot="icon" :user="item.displayName" :display-name="item.displayName" />
			<AppNavigationCounter slot="counter" :highlighted="true">
				3
			</AppNavigationCounter>
			<template slot="actions">
				<ActionButton icon="icon-edit" @click="alert('Edit')">
					Edit
				</ActionButton>
				<ActionButton icon="icon-delete" @click="alert('Delete')">
					Delete
				</ActionButton>
				<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
			</template>
		</AppNavigationItem>
	</ul>
</template>

<script>
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import AppNavigationItem from 'nextcloud-vue/dist/Components/AppNavigationItem'
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import AppNavigationCounter from 'nextcloud-vue/dist/Components/AppNavigationCounter'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'

export default {
	name: 'ConversationsList',
	components: {
		AppNavigationItem,
		Avatar,
		AppNavigationCounter,
		ActionButton
	},
	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},
		conversations() {
			return this.$store.getters.conversations
		}
	}
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}
</style>
