<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>
import type {
	Conversation,
	SharedItems as ShareItemsType,
} from '../../../types/index.ts'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import { useStore } from 'vuex'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCollectionList from '@nextcloud/vue/components/NcCollectionList'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcRelatedResourcesPanel from '@nextcloud/vue/components/NcRelatedResourcesPanel'
import IconDotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import LoadingComponent from '../../LoadingComponent.vue'
import ThreadItem from '../Threads/ThreadItem.vue'
import SharedItems from './SharedItems.vue'
import SharedItemsBrowser from './SharedItemsBrowser.vue'
import IconPermMediaOutline from '../../../../img/material-icons/perm-media-outline.svg?raw'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { CONVERSATION, SHARED_ITEM } from '../../../constants.ts'
import { hasTalkFeature } from '../../../services/CapabilitiesManager.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useChatExtrasStore } from '../../../stores/chatExtras.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.ts'
import { useSidebarStore } from '../../../stores/sidebar.ts'
import {
	sharedItemButtonTitle,
	sharedItemsOrder,
	sharedItemsWithPreviewLimit,
	sharedItemTitle,
} from './sharedItemsConstants.ts'

const props = defineProps<{
	active: boolean
}>()

const emit = defineEmits<{
	(event: 'update:state', value: 'threads'): void
}>()

const token = useGetToken()
const showSharedItemsBrowser = ref(false)
const browserActiveTab = ref('')
const projectsEnabled = loadState('core', 'projects_enabled', false)
const hasRelatedResources = ref(false)

const store = useStore()
const chatExtrasStore = useChatExtrasStore()
const sharedItemsStore = useSharedItemsStore()
const sidebarStore = useSidebarStore()
const actorStore = useActorStore()

const conversation = computed<Conversation>(() => store.getters.conversation(token.value))
const canCreatePollDrafts = computed(() => {
	return hasTalkFeature(token.value, 'talk-polls-drafts') && store.getters.isModerator
		&& [CONVERSATION.TYPE.GROUP, CONVERSATION.TYPE.PUBLIC].includes(conversation.value.type)
})
const sharedItems = computed(() => sharedItemsStore.sharedItems(token.value))
const hasSharedItems = computed(() => Object.keys(sharedItems.value).length > 0)

const supportThreads = computed(() => hasTalkFeature(token.value, 'threads'))
const threadsInformation = computed(() => supportThreads.value ? chatExtrasStore.getThreadsList(token.value).slice(0, 3) : [])
const hasMoreThreads = computed(() => chatExtrasStore.getThreadsList(token.value).length > 3)

watch([token, () => props.active, () => sidebarStore.show], ([token, isActive, isOpen]) => {
	if (token && isActive && isOpen) {
		sharedItemsStore.getSharedItemsOverview(token)
		supportThreads.value && chatExtrasStore.fetchRecentThreadsList(token)
	}
}, { immediate: true })

/**
 * Check if there are more items of a specific type than the limit allows.
 *
 * @param type
 * @param items
 */
function hasMore(type: string, items: ShareItemsType) {
	return Object.values(items).length > limit(type)
}

/**
 * Open the SharedItemsBrowser dialog for a specific type of shared items.
 *
 * @param type
 */
function showMore(type: string) {
	browserActiveTab.value = type
	showSharedItemsBrowser.value = true
}

/**
 * Get the limit for the number of items displayed based on the type.
 *
 * @param type
 */
function limit(type: string) {
	return sharedItemsWithPreviewLimit.includes(type) ? 2 : 5
}

/**
 * Open the Poll Drafts browser dialog.
 */
function openPollDraftHandler() {
	EventBus.emit('poll-drafts-open', { token: token.value })
}

</script>

<template>
	<div class="shared-items-tab">
		<LoadingComponent v-if="!sharedItemsStore.overviewLoaded[token]" class="shared-items-tab__loading" />

		<template v-else>
			<!-- Threads overview -->
			<template v-if="supportThreads && threadsInformation.length">
				<NcAppNavigationCaption :name="t('spreed', 'Recent threads')" />
				<ul class="threads-list">
					<ThreadItem v-for="thread of threadsInformation"
						:key="`thread_${thread.thread.id}`"
						:thread="thread" />
					<NcButton v-if="hasMoreThreads"
						variant="tertiary"
						class="shared-items-tab__more-button shared-items-tab__more-button--threads"
						@click="emit('update:state', 'threads')">
						<template #icon>
							<IconDotsHorizontal :size="24" />
						</template>
						{{ t('spreed', 'Show more threads') }}
					</NcButton>
				</ul>
			</template>
			<!-- Shared items grouped by type -->
			<template v-for="type in sharedItemsOrder" :key="type">
				<div v-if="sharedItems[type]" class="shared-items-tab__section">
					<NcAppNavigationCaption :name="sharedItemTitle[type] || sharedItemTitle.default" />
					<NcButton v-if="type === SHARED_ITEM.TYPES.POLL && canCreatePollDrafts"
						class="shared-items-tab__poll-button"
						wide
						@click="openPollDraftHandler">
						<template #icon>
							<IconPoll :size="20" />
						</template>
						{{ t('spreed', 'Browse poll drafts') }}
					</NcButton>
					<SharedItems :type="type"
						:token="token"
						tab-view
						:limit="limit(type)"
						:items="sharedItems[type]" />
					<NcButton v-if="hasMore(type, sharedItems[type])"
						variant="tertiary"
						class="shared-items-tab__more-button"
						@click="showMore(type)">
						<template #icon>
							<IconDotsHorizontal :size="20" />
						</template>
						{{ sharedItemButtonTitle[type] || sharedItemButtonTitle.default }}
					</NcButton>
				</div>
			</template>

			<!-- Shared from "Related Resources" app -->
			<NcRelatedResourcesPanel class="related-resources"
				provider-id="talk"
				:item-id="conversation.token"
				@has-resources="(value: boolean) => hasRelatedResources = value" />

			<!-- Shared from "Projects" app -->
			<template v-if="projectsEnabled">
				<NcAppNavigationCaption :name="t('spreed', 'Projects')" />
				<NcCollectionList v-if="actorStore.userId && token"
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
					<NcIconSvgWrapper :svg="IconPermMediaOutline" />
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

<style lang="scss" scoped>
// Override default NcRelatedResourcesPanel styles
.related-resources {
	&:deep(.related-resources__header) {
		margin: 14px 0 !important;
		padding: 0 calc(var(--default-grid-baseline) * 2) 0 calc(var(--default-grid-baseline) * 3);

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

	&__section {
		margin-top: calc(var(--default-grid-baseline) * 6);
	}

	&__loading,
	&__empty-content {
		flex: 1;
	}

	&__more-button {
		margin-top: var(--default-grid-baseline);

		// Override NcButton styles to align mdi icons and mimetype icons
		&.button-vue--tertiary {
			border-width: 0;
		}

		:deep(.button-vue__icon) {
			margin-inline-end: var(--default-grid-baseline);
			width: 24px;
			min-width: 24px;
		}

		&--threads {
			margin-inline-start: var(--default-grid-baseline);

			:deep(.button-vue__icon) {
				margin-inline-end: calc(var(--default-grid-baseline) * 2);
				width: 40px;
			}
		}
	}

	&__poll-button {
		max-width: 300px;
		margin-bottom: var(--default-grid-baseline);
	}
}

.threads {
	&-list {
		line-height: 20px;
	}
}
</style>
