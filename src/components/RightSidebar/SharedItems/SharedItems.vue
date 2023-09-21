<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
			<div v-if="type === 'location'"
				:key="item.id"
				class="shared-items__location"
				:class="{ 'shared-items__location--nolimit': limit === 0 }">
				<Location :wide="true"
					v-bind="item.messageParameters.object" />
			</div>
			<div v-else-if="type === 'deckcard'"
				:key="item.id"
				class="shared-items__deckcard"
				:class="{ 'shared-items__deckcard--nolimit': limit === 0 }">
				<DeckCard :wide="true"
					v-bind="item.messageParameters.object" />
			</div>
			<div v-else-if="type === 'poll'"
				:key="item.id"
				class="shared-items__poll"
				:class="{ 'shared-items__poll--nolimit': limit === 0 }">
				<Poll v-bind="item.messageParameters.object"
					:token="$store.getters.getToken()"
					:poll-name="item.messageParameters.object.name" />
			</div>
			<template v-else-if="type === 'other'">
				<div :key="item.id"
					class="shared-items__other">
					<a v-if="item.messageParameters.object.link"
						:href="item.messageParameters.object.link"
						target="_blank">
						{{ item.messageParameters.object.name }}
					</a>
					<p v-else>
						{{ item.messageParameters.object.name }}
					</p>
				</div>
			</template>
			<FilePreview v-else
				:key="item.id"
				:small-preview="isList"
				:row-layout="isList"
				:shared-items-type="type"
				:is-shared-items-tab="true"
				v-bind="item.messageParameters.file" />
		</template>
	</div>
</template>

<script>
import DeckCard from '../../MessagesList/MessagesGroup/Message/MessagePart/DeckCard.vue'
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import Location from '../../MessagesList/MessagesGroup/Message/MessagePart/Location.vue'
import Poll from '../../MessagesList/MessagesGroup/Message/MessagePart/Poll.vue'

import { SHARED_ITEM } from '../../../constants.js'

export default {
	name: 'SharedItems',

	components: {
		DeckCard,
		FilePreview,
		Location,
		Poll,
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
			type: Number,
			default: 0,
		},
	},

	computed: {
		itemsToDisplay() {
			if (this.limit === 0) {
				return Object.values(this.items).reverse()
			} else {
				return Object.values(this.items).reverse().slice(0, this.limit)
			}
		},

		isList() {
			if (this.type === SHARED_ITEM.TYPES.MEDIA) {
				return false
			} else {
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
	grid-gap: 4px;
	margin: auto;

	&__list {
		display: flex;
		flex-wrap: wrap;
	}

	&__location {
		width: 100%;
		height: 150px;
		margin: 4px 0;

		&--nolimit {
			width: 33%;
		}
	}

	&__poll,
	&__deckcard {
		width: 100%;

		&--nolimit {
			width: 33%;
		}
	}

	&__other {
		width: 100%;
		margin-left: 8px;

		a {
			text-decoration: underline;
			&:after {
				content: ' ↗';
			}
		}
	}
}
</style>
