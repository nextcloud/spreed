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
				<LocationCard wide v-bind="item.messageParameters.object" />
			</div>

			<PinnedMessageItem
				v-else-if="isPinned"
				:item />

			<DeckCard
				v-else-if="isDeckCard"
				wide
				v-bind="item.messageParameters.object" />

			<PollCard
				v-else-if="isPoll"
				:token
				v-bind="item.messageParameters.object" />

			<div
				v-else-if="isOther"
				class="shared-items__other">
				<a
					v-if="item.messageParameters.object?.link"
					:href="item.messageParameters.object.link"
					target="_blank">
					{{ item.messageParameters.object.name }}
				</a>
				<p v-else>
					{{ item.messageParameters.object.name }}
				</p>
			</div>

			<div
				v-else-if="isLink"
				class="shared-items__link">
				<template v-for="link in linksToDisplay[item.id]" :key="link">
					<NcReferenceWidget
						v-if="linkReferences[link]"
						:reference="linkReferences[link]"
						:interactive="false" />
					<a
						v-else
						:href="link"
						:title="link"
						:class="{ 'shared-items__link-internal': isInstanceLink(link) }"
						target="_blank"
						rel="noopener noreferrer">
						<span class="shared-items__link-text">{{ link }}</span>
					</a>
				</template>
			</div>

			<FilePreview
				v-else
				:token
				:rowLayout="!isMedia"
				:itemType="type"
				isSharedItems
				:file="item.messageParameters.file" />
		</template>
	</div>
</template>

<script>
import { defineAsyncComponent, ref } from 'vue'
import { NcReferenceWidget } from '@nextcloud/vue/components/NcRichText'
import DeckCard from '../../MessagesList/MessagesGroup/Message/MessagePart/DeckCard.vue'
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import PollCard from '../../MessagesList/MessagesGroup/Message/MessagePart/PollCard.vue'
import PinnedMessageItem from '../PinnedMessages/PinnedMessageItem.vue'
import { SHARED_ITEM } from '../../../constants.ts'
import { resolveReferences } from '../../../services/coreService.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { isClassifiedConversation } from '../../../utils/conversation.ts'
import { getMessageLinks, isInstanceLink } from '../../../utils/message.ts'

const LocationCard = defineAsyncComponent(() => import('../../MessagesList/MessagesGroup/Message/MessagePart/LocationCard.vue'))

export default {
	name: 'SharedItems',

	components: {
		DeckCard,
		FilePreview,
		LocationCard,
		NcReferenceWidget,
		PollCard,
		PinnedMessageItem,
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

	setup() {
		const actorStore = useActorStore()
		const references = ref({})

		return {
			actorStore,
			references,
		}
	},

	computed: {
		itemsToDisplay() {
			const items = Object.values(this.items).reverse()
			if (this.isLink) {
				return items.filter((item) => this.linksToDisplay[item.id])
			}
			return items.slice(0, this.limit)
		},

		// The limit applies to the number of links, not messages
		linksToDisplay() {
			if (!this.isLink) {
				return {}
			}

			const linksToDisplay = {}
			let remaining = this.limit ?? Infinity
			for (const item of Object.values(this.items).reverse()) {
				if (remaining <= 0) {
					break
				}
				const links = getMessageLinks(item.message).slice(0, remaining)
				if (links.length) {
					linksToDisplay[item.id] = links
					remaining -= links.length
				}
			}
			return linksToDisplay
		},

		// Overridden type renders the default OpenGraph card instead of registered custom widgets
		linkReferences() {
			const linkReferences = {}
			if (this.isClassified) {
				return linkReferences
			}
			for (const [link, reference] of Object.entries(this.references)) {
				if (reference?.accessible) {
					linkReferences[link] = { ...reference, richObjectType: 'open-graph' }
				}
			}
			return linkReferences
		},

		isClassified() {
			return isClassifiedConversation(this.$store.getters.conversation(this.token))
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

		isLink() {
			return this.type === SHARED_ITEM.TYPES.LINK
		},

		isMedia() {
			return this.type === SHARED_ITEM.TYPES.MEDIA
		},

		isPinned() {
			return this.type === SHARED_ITEM.TYPES.PINNED
		},

		hasListLayout() {
			return !this.isMedia && (this.tabView || (!this.isLocation && !this.isDeckCard && !this.isPoll))
		},
	},

	watch: {
		linksToDisplay: {
			handler: 'fetchReferences',
			immediate: true,
		},
	},

	methods: {
		isInstanceLink,

		async fetchReferences() {
			if (!this.actorStore.isLoggedIn || this.isClassified) {
				return
			}
			const links = Object.values(this.linksToDisplay).flat().filter((link) => !(link in this.references))
			if (!links.length) {
				return
			}
			try {
				const response = await resolveReferences(links)
				this.references = { ...this.references, ...response.data.ocs.data.references }
			} catch (error) {
				console.error('Error while resolving link references', error)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: var(--default-grid-baseline);
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

	&__link {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
		min-width: 0;

		:deep(.widget-default) {
			width: auto;
			margin: 0;
			font-size: var(--font-size-small);
		}

		:deep(.widget-default--image) {
			flex-shrink: 0;
			width: auto;
			height: 3lh;
			aspect-ratio: 1;
			margin: var(--default-grid-baseline);
			border-radius: var(--border-radius-element);
			object-fit: cover;
		}

		:deep(.widget-default--details) {
			box-sizing: border-box;
			height: calc(3lh + 2 * var(--default-grid-baseline));
			padding: var(--default-grid-baseline);
		}

		:deep(.widget-default--description) {
			// Overrides inline line-clamp style set by width
			display: block !important;
			white-space: nowrap;
		}

		a {
			display: flex;
			gap: var(--default-grid-baseline);
			padding-inline-start: calc(var(--default-grid-baseline) * 2);
			text-decoration: underline;

			&:not(.shared-items__link-internal)::after {
				content: ' ↗';
			}
		}

		&-text {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
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
