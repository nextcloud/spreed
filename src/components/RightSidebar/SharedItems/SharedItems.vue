<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="shared-items"
		:class="{
			'shared-items__media': isMedia,
			'shared-items__list': hasListLayout,
		}">
		<template v-for="item in itemsToDisplay" :key="item.id">
			<div v-if="isLocation" class="shared-items__location">
				<Location wide v-bind="item.messageParameters.object" />
			</div>

			<DeckCard v-else-if="isDeckCard"
				wide
				v-bind="item.messageParameters.object" />

			<Poll v-else-if="isPoll"
				:token="token"
				v-bind="item.messageParameters.object" />

			<div v-else-if="isOther"
				class="shared-items__other">
				<a v-if="item.messageParameters.object?.link"
					:href="item.messageParameters.object.link"
					target="_blank">
					{{ item.messageParameters.object.name }}
				</a>
				<p v-else>
					{{ item.messageParameters.object.name }}
				</p>
			</div>

			<FilePreview v-else
				:token="token"
				:small-preview="!isMedia"
				:row-layout="!isMedia"
				:item-type="type"
				is-shared-items
				:file="item.messageParameters.file" />
		</template>
	</div>
</template>

<script>
import DeckCard from '../../MessagesList/MessagesGroup/Message/MessagePart/DeckCard.vue'
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import Location from '../../MessagesList/MessagesGroup/Message/MessagePart/Location.vue'
import Poll from '../../MessagesList/MessagesGroup/Message/MessagePart/Poll.vue'
import { SHARED_ITEM } from '../../../constants.ts'

export default {
	name: 'SharedItems',

	components: {
		DeckCard,
		FilePreview,
		Location,
		Poll,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

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
			default: undefined,
		},

		// Whether items are shown directly in SharedItemsTab
		tabView: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		itemsToDisplay() {
			return Object.values(this.items).reverse().slice(0, this.limit)
		},

		isLocation() {
			return this.type === SHARED_ITEM.TYPES.LOCATION
		},

		isDeckCard() {
			return this.type === SHARED_ITEM.TYPES.DECK_CARD
		},

		isPoll() {
			return this.type === SHARED_ITEM.TYPES.POLL
		},

		isOther() {
			return this.type === SHARED_ITEM.TYPES.OTHER
		},

		isMedia() {
			return this.type === SHARED_ITEM.TYPES.MEDIA
		},

		hasListLayout() {
			return !this.isMedia && (this.tabView || (!this.isLocation && !this.isDeckCard && !this.isPoll))
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	grid-gap: var(--default-grid-baseline);
	margin: auto;

	&__media {
		grid-template-columns: repeat(5, 1fr);
	}

	&__list {
		grid-template-columns: 1fr;
	}

	&__location {
		height: 150px;
		margin-block: var(--default-grid-baseline);
	}

	&__other {
		padding-inline-start: calc(var(--default-grid-baseline) * 2);

		a {
			text-decoration: underline;

			&:after {
				content: ' ↗';
			}
		}
	}
}
</style>
