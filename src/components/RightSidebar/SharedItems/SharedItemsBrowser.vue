<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license AGPL-3.0-or-later
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
	<NcModal size="large" :container="container" v-on="$listeners">
		<div class="shared-items-browser">
			<div class="shared-items-browser__navigation">
				<template v-for="type in sharedItemsOrder">
					<NcButton v-if="sharedItems[type]"
						:key="type"
						:class="{'active' : activeTab === type}"
						type="tertiary"
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import SharedItems from './SharedItems.vue'

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

	emits: ['update:active-tab'],

	setup() {
		const sharedItemsStore = useSharedItemsStore()

		return {
			sharedItemsStore,
			sharedItemTitle,
			sharedItemsOrder,
		}
	},

	data() {
		return {
			firstItemsLoaded: {},
			isRequestingMoreItems: {},
			hasFetchedAllItems: {},
		}
	},

	computed: {
		scroller() {
			return this.$refs.scroller
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},
	},

	watch: {
		activeTab(newType) {
			this.firstFetchItems(newType)
		},
	},

	mounted() {
		this.firstFetchItems(this.activeTab)
	},

	methods: {
		handleTabClick(type) {
			this.$emit('update:active-tab', type)
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

		debounceHandleScroll: debounce(function() {
			this.handleScroll()
		}, 50),

		async handleScroll() {
			const scrollHeight = this.scroller.scrollHeight
			const scrollTop = this.scroller.scrollTop
			const containerHeight = this.scroller.clientHeight
			if ((scrollHeight - scrollTop - containerHeight < 300)
				&& !this.isRequestingMoreItems?.[this.activeTab]
				&& !this.hasFetchedAllItems?.[this.activeTab]) {
				this.fetchItems(this.activeTab)
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
