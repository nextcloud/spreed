<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="shared-items-tab">
		<LoadingComponent v-if="loading" class="shared-items-tab__loading" />

		<template v-else>
			<!-- Shared items grouped by type -->
			<template v-for="type in sharedItemsOrder">
				<div v-if="sharedItems[type]" :key="type">
					<NcAppNavigationCaption :name="sharedItemTitle[type] || sharedItemTitle.default" />
					<SharedItems :type="type"
						:token="token"
						tab-view
						:limit="limit(type)"
						:items="sharedItems[type]" />
					<NcButton v-if="hasMore(type, sharedItems[type])"
						type="tertiary-no-background"
						class="more"
						wide
						@click="showMore(type)">
						<template #icon>
							<DotsHorizontal :size="20" />
						</template>
						{{ sharedItemButtonTitle[type] || sharedItemButtonTitle.default }}
					</NcButton>
				</div>
			</template>

			<!-- Shared from "Related Resources" app -->
			<NcRelatedResourcesPanel class="related-resources"
				provider-id="talk"
				:item-id="conversation.token"
				@has-resources="value => hasRelatedResources = value" />

			<!-- Shared from "Projects" app -->
			<!-- <template v-if="projectsEnabled">
				<NcAppNavigationCaption :name="t('spreed', 'Projects')" />
				<CollectionList v-if="getUserId && token"
					:id="token"
					type="room"
					:name="conversation.displayName"
					:is-active="active" />
			</template> -->

			<template v-if="false" />

			<!-- No shared content -->
			<NcEmptyContent v-else-if="!hasSharedItems && !hasRelatedResources"
				class="shared-items-tab__empty-content"
				:name="t('spreed', 'No shared items')">
				<template #icon>
					<FolderMultipleImage :size="20" />
				</template>
			</NcEmptyContent>
		</template>

		<!-- Dialog window -->
		<SharedItemsBrowser v-if="showSharedItemsBrowser"
			:token="token"
			:shared-items="sharedItems"
			:active-tab.sync="browserActiveTab"
			@close="showSharedItemsBrowser = false" />
	</div>
</template>

<script>
// import { CollectionList } from 'nextcloud-vue-collections'

import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import FolderMultipleImage from 'vue-material-design-icons/FolderMultipleImage.vue'

import { loadState } from '@nextcloud/initial-state'

import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcRelatedResourcesPanel from '@nextcloud/vue/dist/Components/NcRelatedResourcesPanel.js'

import SharedItems from './SharedItems.vue'
import SharedItemsBrowser from './SharedItemsBrowser.vue'
import LoadingComponent from '../../LoadingComponent.vue'

import {
	sharedItemButtonTitle,
	sharedItemsOrder,
	sharedItemsWithPreviewLimit,
	sharedItemTitle,
} from './sharedItemsConstants.js'
import { useSharedItemsStore } from '../../../stores/sharedItems.js'

export default {

	name: 'SharedItemsTab',

	components: {
		// CollectionList,
		DotsHorizontal,
		FolderMultipleImage,
		LoadingComponent,
		NcAppNavigationCaption,
		NcButton,
		NcEmptyContent,
		NcRelatedResourcesPanel,
		SharedItems,
		SharedItemsBrowser,
	},

	props: {
		active: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const sharedItemsStore = useSharedItemsStore()
		return {
			sharedItemsStore,
			sharedItemButtonTitle,
			sharedItemTitle,
			sharedItemsOrder,
			sharedItemsWithPreviewLimit,
		}
	},

	data() {
		return {
			showSharedItemsBrowser: false,
			browserActiveTab: '',
			projectsEnabled: loadState('core', 'projects_enabled', false),
			hasRelatedResources: false,
		}
	},

	computed: {
		getUserId() {
			return this.$store.getters.getUserId()
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		loading() {
			return !this.sharedItemsStore.overviewLoaded[this.token]
		},

		sharedItems() {
			return this.sharedItemsStore.sharedItems(this.token)
		},

		hasSharedItems() {
			return Object.keys(this.sharedItems).length > 0
		},

		isSidebarOpen() {
			return this.$store.getters.getSidebarStatus
		},

		sharedItemsIdentifier() {
			return this.token + ':' + this.active + ':' + this.isSidebarOpen
		},
	},

	watch: {
		sharedItemsIdentifier: {
			immediate: true,
			handler() {
				if (this.token && this.active && this.isSidebarOpen) {
					this.sharedItemsStore.getSharedItemsOverview(this.token)
				}
			}
		},
	},

	methods: {
		hasMore(type, items) {
			return Object.values(items).length > this.limit(type)
		},

		showMore(type) {
			this.browserActiveTab = type
			this.showSharedItemsBrowser = true
		},

		limit(type) {
			return this.sharedItemsWithPreviewLimit.includes(type) ? 2 : 6
		},
	},
}
</script>

<style lang="scss" scoped>
.more {
	margin-top: 8px;
}

// Override default NcRelatedResourcesPanel styles
.related-resources {
	&:deep(.related-resources__header) {
		margin: 14px 0 !important;
		padding: 0 calc(var(--default-grid-baseline, 4px) * 2) 0 calc(var(--default-grid-baseline, 4px) * 3);

		h5 {
			opacity: .7 !important;
			color: var(--color-primary-element) !important;
		}
	}
}

.shared-items-tab {
	display: flex;
	flex-direction: column;
	height: 100%;

	&__loading,
	&__empty-content {
		flex: 1;
	}
}
</style>
