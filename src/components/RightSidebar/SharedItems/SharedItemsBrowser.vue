<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal size="large"
		:label-id="dialogHeaderId"
		@close="$emit('close')">
		<div class="shared-items-browser">
			<h2 :id="dialogHeaderId" class="hidden-visually">
				{{ sharedItemTitle[activeTab] || sharedItemTitle.default }}
			</h2>
			<div class="shared-items-browser__navigation">
				<template v-for="type in sharedItemsOrder">
					<NcButton v-if="sharedItems[type]"
						:key="type"
						:class="{ active: activeTab === type }"
						variant="tertiary"
						@click="handleTabClick(type)">
						{{ sharedItemTitle[type] || sharedItemTitle.default }}
					</NcButton>
				</template>
			</div>

			<div ref="scroller" class="shared-items-browser__content" @scroll="debounceHandleScroll">
				<SharedItems :type="activeTab"
					:token="token"
					:items="sharedItems[activeTab]" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import debounce from 'debounce'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import SharedItems from './SharedItems.vue'
import { useId } from '../../../composables/useId.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.js'
import { sharedItemsOrder, sharedItemTitle } from './sharedItemsConstants.js'

export default {
	name: 'SharedItemsBrowser',

	components: {
		NcButton,
		NcModal,
		SharedItems,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		sharedItems: {
			type: Object,
			required: true,
		},

		activeTab: {
			type: String,
			required: true,
		},
	},

	emits: ['close', 'update:activeTab'],

	setup() {
		const dialogHeaderId = `shared-items-browser-${useId()}`

		return {
			sharedItemsStore: useSharedItemsStore(),
			sharedItemTitle,
			sharedItemsOrder,
			dialogHeaderId,
		}
	},

	data() {
		return {
			firstItemsLoaded: {},
			isRequestingMoreItems: {},
			hasFetchedAllItems: {},
			debounceHandleScroll: () => {},
		}
	},

	computed: {
		scroller() {
			return this.$refs.scroller
		},
	},

	watch: {
		activeTab(newType) {
			this.firstFetchItems(newType)
		},
	},

	mounted() {
		this.debounceHandleScroll = debounce(this.handleScroll, 50)
		this.firstFetchItems(this.activeTab)
	},

	beforeUnmount() {
		this.debounceHandleScroll.clear?.()
	},

	methods: {
		handleTabClick(type) {
			this.$emit('update:activeTab', type)
		},

		firstFetchItems(type) {
			if (!this.firstItemsLoaded?.[type]) {
				this.fetchItems(type)
				this.firstItemsLoaded[type] = true
			}
		},

		async fetchItems(type) {
			this.isRequestingMoreItems[this.activeTab] = true
			const { hasMoreItems } = await this.sharedItemsStore.getSharedItems(this.token, type)
			if (hasMoreItems === false) {
				this.hasFetchedAllItems[this.activeTab] = true
			}
			this.isRequestingMoreItems[this.activeTab] = false
		},

		async handleScroll() {
			const scrollHeight = this.scroller.scrollHeight
			const scrollTop = this.scroller.scrollTop
			const containerHeight = this.scroller.clientHeight
			if ((scrollHeight - scrollTop - containerHeight < 300)
				&& !this.isRequestingMoreItems?.[this.activeTab]
				&& !this.hasFetchedAllItems?.[this.activeTab]) {
				await this.fetchItems(this.activeTab)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items-browser {
	width: 100%;
	height: 100%;
	position: relative;
	display: flex;
	flex-direction: column;

	&__navigation {
		display: flex;
		gap: 8px;
		padding: 16px 16px 4px;
		flex-wrap: wrap;
		justify-content: center;
	}

	&__content {
		overflow-y: auto;
		overflow-x: hidden;
		padding: 12px;
	}
}

:deep(.modal-container) {
	height: 700px;
}

:deep(.button-vue) {
	border-radius: var(--border-radius-large);

	&.active {
		background-color: var(--color-primary-element-light);
	}
}
</style>
