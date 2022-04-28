<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="shared-items" :class="{'shared-items__list' : isList}">
		<template v-for="item in itemsToDisplay">
			<Location v-if="type === 'location'"
				:key="item.id"
				:latitude="item.messageParameters.object.latitude"
				:longitude="item.messageParameters.object.longitude"
				:title="item.messageParameters.object.title" />
			<FilePreview v-else
				:key="item.id"
				:small-preview="isList"
				:row-layout="isList"
				:is-shared-items-tab="true"
				v-bind="item.messageParameters.file" />
		</template>
	</div>
</template>

<script>
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import { SHARED_ITEM } from '../../../constants'
import Location from '../../MessagesList/MessagesGroup/Message/MessagePart/Location'
import DeckCard from '../../MessagesList/MessagesGroup/Message/MessagePart/DeckCard'

export default {
	name: 'SharedItems',

	components: {
		FilePreview,
		Location,
		DeckCard,
	},

	props: {
		type: {
			type: String,
			required: true,
		},

		items: {
			type: Object,
			required: true,
		},

		// Limits the amount of items displayed
		limit: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		itemsToDisplay() {
			return this.limit ? Object.values(this.items).reverse().slice(0, 6) : Object.values(this.items).reverse()
		},

		isList() {
			switch (this.type) {
			case SHARED_ITEM.TYPES.MEDIA:
				return false
			case SHARED_ITEM.TYPES.LOCATION:
				return false
			default:
				return true
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	grid-template-rows: 1fr 1fr;
	margin-bottom: 16px;
	grid-gap: 4px;
	&__list {
		display: flex;
		flex-direction: column;
	}

}
</style>
