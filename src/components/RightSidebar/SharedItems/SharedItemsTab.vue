<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="shared-items-tab">
		<LoadingComponent v-if="loading" class="shared-items-tab__loading" />

		<template v-else>
			<NcButton v-if="canCreatePollDrafts"
				wide
				@click="openPollDraftHandler">
				<template #icon>
					<IconPoll :size="20" />
				</template>
				{{ t('spreed', 'Browse poll drafts') }}
			</NcButton>
			<!-- Shared items grouped by type -->
			<template v-for="type in sharedItemsOrder" :key="type">
				<div v-if="sharedItems[type]">
					<NcAppNavigationCaption :name="sharedItemTitle[type] || sharedItemTitle.default" />
					<SharedItems :type="type"
						:token="token"
						tab-view
						:limit="limit(type)"
						:items="sharedItems[type]" />
					<NcButton v-if="hasMore(type, sharedItems[type])"
						variant="tertiary-no-background"
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
			<template v-if="projectsEnabled">
				<NcAppNavigationCaption :name="t('spreed', 'Projects')" />
				<NcCollectionList v-if="getUserId && token"
					:id="token"
					type="room"
					:name="conversation.displayName"
					:is-active="active" />
			</template>

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
			v-model:active-tab="browserActiveTab"
			:token="token"
			:shared-items="sharedItems"
			@close="showSharedItemsBrowser = false" />
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCollectionList from '@nextcloud/vue/components/NcCollectionList'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcRelatedResourcesPanel from '@nextcloud/vue/components/NcRelatedResourcesPanel'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import FolderMultipleImage from 'vue-material-design-icons/FolderMultipleImage.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import LoadingComponent from '../../LoadingComponent.vue'
import SharedItems from './SharedItems.vue'
import SharedItemsBrowser from './SharedItemsBrowser.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { CONVERSATION } from '../../../constants.ts'
import { hasTalkFeature } from '../../../services/CapabilitiesManager.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.js'
import { useSidebarStore } from '../../../stores/sidebar.ts'
import {
	sharedItemButtonTitle,
	sharedItemsOrder,
	sharedItemsWithPreviewLimit,
	sharedItemTitle,
} from './sharedItemsConstants.js'

export default {

	name: 'SharedItemsTab',

	components: {
		DotsHorizontal,
		FolderMultipleImage,
		IconPoll,
		LoadingComponent,
		NcAppNavigationCaption,
		NcButton,
		NcCollectionList,
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
		return {
			actorStore: useActorStore(),
			sharedItemsStore: useSharedItemsStore(),
			sidebarStore: useSidebarStore(),
			sharedItemButtonTitle,
			sharedItemTitle,
			sharedItemsOrder,
			sharedItemsWithPreviewLimit,
			token: useGetToken(),
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
			return this.actorStore.userId
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		canCreatePollDrafts() {
			return hasTalkFeature(this.token, 'talk-polls-drafts') && this.$store.getters.isModerator
				&& [CONVERSATION.TYPE.GROUP, CONVERSATION.TYPE.PUBLIC].includes(this.conversation.type)
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
			return this.sidebarStore.show
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
			},
		},
	},

	methods: {
		t,
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

		openPollDraftHandler() {
			EventBus.emit('poll-drafts-open', { token: this.token })
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
