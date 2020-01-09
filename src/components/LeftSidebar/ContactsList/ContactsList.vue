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
	<ul class="contacts-list">
		<AppContentListItem
			v-for="item of contacts"
			:key="item.id"
			:title="item.label"
			@click="onClick(item)">
			<Avatar
				slot="icon"
				:size="44"
				:user="item.id"
				:display-name="item.label" />
		</AppContentListItem>
	</ul>
</template>

<script>
import AppContentListItem from '../ConversationsList/AppContentListItem/AppContentListItem'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'

export default {
	name: 'ContactsList',
	components: {
		Avatar,
		AppContentListItem,
	},
	props: {
		contacts: {
			type: Array,
			required: true,
		},
		isLoading: {
			type: Boolean,
			default: false,
		},
	},
	methods: {
		// forward click event
		onClick(item) {
			this.$emit('click', item)
		},
	},
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
