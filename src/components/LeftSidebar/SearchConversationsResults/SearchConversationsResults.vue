<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { ParticipantSearchResult, Conversation as TypeConversation } from '../../../types/index.ts'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { useVirtualList } from '@vueuse/core'
import { computed } from 'vue'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconChatPlusOutline from 'vue-material-design-icons/ChatPlusOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import NavigationHint from '../../UIShared/NavigationHint.vue'
import ConversationItem from '../ConversationsList/ConversationItem.vue'
import { ATTENDEE, AVATAR, CONVERSATION } from '../../../constants.ts'
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'
import { useSettingsStore } from '../../../stores/settings.ts'
import { getPreloadedUserStatus } from '../../../utils/userStatus.ts'

const props = defineProps<{
	searchText: string
	conversationsList: TypeConversation[]
	contactsLoading: boolean
	searchResultsListedConversations: TypeConversation[]
	searchResults: ParticipantSearchResult[]
}>()

const emit = defineEmits<{
	abortSearch: []
	createNewConversation: [searchText: string]
	createAndJoinConversation: [item: TypeConversation | ParticipantSearchResult]
}>()

const isCirclesEnabled = loadState('spreed', 'circles_enabled')
const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')
const settingsStore = useSettingsStore()
const isCompact = computed(() => settingsStore.conversationsListStyle === CONVERSATION.LIST_STYLE.COMPACT)

// Item's content (avatar) + internal_padding * 2 + external_padding * 2
const itemHeight = computed(() => isCompact.value ? 28 + 2 * 2 : AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2)

// Other sections than joined conversations in the output order
const sections = [
	{
		type: 'user',
		caption: { type: 'caption', id: 'users_caption', name: t('spreed', 'Users') },
	},
	{
		type: 'group',
		caption: { type: 'caption', id: 'groups_caption', name: t('spreed', 'Groups') },
	},
	{
		type: 'circle',
		caption: { type: 'caption', id: 'circles_caption', name: t('spreed', 'Teams') },
	},
	{
		type: 'federated',
		caption: { type: 'caption', id: 'federated_users_caption', name: t('spreed', 'Federated users') },
	},
] as const

type SubListType = {
	user: ParticipantSearchResult[]
	group: ParticipantSearchResult[]
	circle: ParticipantSearchResult[]
	federated: ParticipantSearchResult[]
}

type VirtualListItem
	= | { type: 'caption', id: string, name: string }
		| { type: 'hint', id: string, hint: string }
		| { type: 'conversation', id: number, object: TypeConversation }
		| { type: 'open_conversation', id: number, object: TypeConversation }
		| { type: 'action', id: string, name: string, subname: string }
		| { type: 'user' | 'group' | 'circle' | 'federated', id: string, object: ParticipantSearchResult, icon: Record<string, unknown> }

const searchResultsVirtual = computed<VirtualListItem[]>(() => {
	// Initialize
	const virtualList: VirtualListItem[] = []

	// Normalize strings for search (remove diacritics and case, e.g. 'Jérôme' -> 'jerome')
	const normalizer = (rawString: string) => rawString.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')

	const lowerSearchText = normalizer(props.searchText)
	const searchResultsConversationList = props.conversationsList.filter((conversation) => normalizer(conversation.displayName).includes(lowerSearchText)
		|| normalizer(conversation.name).includes(lowerSearchText))

	// Add conversations section
	virtualList.push({ type: 'caption', id: 'conversations_caption', name: t('spreed', 'Conversations') })
	if (searchResultsConversationList.length === 0) {
		virtualList.push({ type: 'hint', id: 'hint_conversations', hint: t('spreed', 'No matches found') })
	} else {
		searchResultsConversationList.forEach((item: TypeConversation) => {
			virtualList.push({ type: 'conversation', id: item.id, object: item })
		})
	}

	// Add "New Conversation" option if allowed
	if (canStartConversations) {
		virtualList.push({ type: 'action', id: 'new_conversation', name: props.searchText, subname: t('spreed', 'New private conversation') })
	}

	// Add open conversations section if any
	if (props.searchResultsListedConversations.length !== 0) {
		virtualList.push({ type: 'caption', id: 'open_conversation_caption', name: t('spreed', 'Open conversations') })
		props.searchResultsListedConversations.forEach((item: TypeConversation) => {
			virtualList.push({ type: 'open_conversation', id: item.id, object: item })
		})
	}

	// Categorize search results into different sections
	const subList = props.searchResults.reduce<SubListType>((acc, result) => {
		if (result.source === ATTENDEE.ACTOR_TYPE.USERS) {
			acc.user.push(result)
		} else if (result.source === ATTENDEE.ACTOR_TYPE.GROUPS && canStartConversations) {
			acc.group.push(result)
		} else if (result.source === ATTENDEE.ACTOR_TYPE.CIRCLES && canStartConversations) {
			acc.circle.push(result)
		} else if (result.source === ATTENDEE.ACTOR_TYPE.REMOTES && canStartConversations) {
			acc.federated.push({ ...result, source: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS })
		}
		return acc
	}, {
		user: [],
		group: [],
		circle: [],
		federated: [],
	})

	// Iterate over sections and build the virtualList
	sections.forEach((section) => {
		if (subList[section.type].length > 0) {
			virtualList.push(section.caption)
			virtualList.push(...subList[section.type].map((match) => ({
				type: section.type,
				id: `${section.type}_${match.id}`,
				object: match,
				icon: iconData(match),
			})))
		}
	})

	// Add "No results" message if there are no results in any section
	if (!subList.user.length || !subList.group.length || (!subList.circle.length && isCirclesEnabled) || !subList.federated.length) {
		virtualList.push({ type: 'caption', id: 'no_results_caption', name: sourcesWithoutResults(subList) })
		virtualList.push({ type: 'hint', id: 'no_results_hint', hint: t('spreed', 'No search results') })
	}

	return virtualList
})

const { list, containerProps, wrapperProps } = useVirtualList<VirtualListItem>(searchResultsVirtual, {
	itemHeight: () => itemHeight.value,
	overscan: 10,
})

/**
 * Generate the props for the AvatarWrapper component
 *
 * @param item conversation item
 */
function iconData(item: ParticipantSearchResult) {
	if (item.source === ATTENDEE.ACTOR_TYPE.USERS
		|| item.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
		return {
			id: item.id,
			name: item.label,
			source: item.source,
			preloadedUserStatus: getPreloadedUserStatus(item),
			disableMenu: true,
			token: 'new',
			showUserStatus: true,
			size: isCompact.value ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT,
		}
	}
	return {
		type: CONVERSATION.TYPE.GROUP,
		name: item.label,
		objectType: item.source,
		size: isCompact.value ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT,
	}
}

const hasSourcesWithoutResults = computed(() => {
	return !searchResultsVirtual.value.some((item) => item.type === 'user' || item.type === 'group'
		|| (item.type === 'circle' && isCirclesEnabled))
})

/**
 * Generate the message for the "No results" section
 *
 * @param list search results
 */
function sourcesWithoutResults(list: SubListType): string {
	const hasNoCirclesResults = isCirclesEnabled && !list.circle.length
	if (!list.user.length) {
		if (!list.group.length) {
			return (hasNoCirclesResults)
				? t('spreed', 'Users, groups and teams')
				: t('spreed', 'Users and groups')
		} else {
			return (hasNoCirclesResults)
				? t('spreed', 'Users and teams')
				: t('spreed', 'Users')
		}
	} else {
		if (!list.group.length) {
			return (hasNoCirclesResults)
				? t('spreed', 'Groups and teams')
				: t('spreed', 'Groups')
		} else {
			return (hasNoCirclesResults)
				? t('spreed', 'Teams')
				: t('spreed', 'Other sources')
		}
	}
}

const footerMargin = computed(() => {
	return isCompact.value ? '0' : '18px' // 54px (item height) - 36px (current height)
})

const iconSize = computed(() => isCompact.value ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT)
</script>

<template>
	<li
		:ref="containerProps.ref"
		:style="containerProps.style"
		@scroll="containerProps.onScroll">
		<NavigationHint
			v-if="contactsLoading && !hasSourcesWithoutResults"
			:style="{ marginBlockStart: footerMargin }"
			tabindex="-1"
			:hint="t('spreed', 'Loading …')" />
		<ul
			v-else
			:style="wrapperProps.style">
			<template
				v-for="item in list"
				:key="item.data.id">
				<ConversationItem
					v-if="item.data.type === 'conversation'"
					:ref="`conversation-${item.data.object.token}`"
					:item="item.data.object"
					:compact="isCompact"
					@click="emit('abortSearch')" />
				<NcListItem
					v-else-if="item.data.type === 'action'"
					:name="searchText"
					:compact="isCompact"
					data-nav-id="conversation_create_new"
					@click="emit('createNewConversation', searchText)">
					<template #icon>
						<IconChatPlusOutline :size="iconSize" />
					</template>
					<template v-if="!isCompact" #subname>
						{{ t('spreed', 'New group conversation') }}
					</template>
				</NcListItem>
				<ConversationItem
					v-else-if="item.data.type === 'open_conversation'"
					:item="item.data.object"
					isSearchResult
					:compact="isCompact"
					@click="emit('abortSearch')" />
				<NcAppNavigationCaption
					v-else-if="item.data.type === 'caption'"
					:name="item.data.name"
					tabindex="-1"
					:style="{
						height: itemHeight + 'px',
						alignItems: isCompact ? 'unset' : 'self-end',
					}" />
				<NavigationHint
					v-else-if="item.data.type === 'hint'"
					tabindex="-1"
					:hint="item.data.hint" />
				<NcListItem
					v-else-if="item.data.type === 'user'"
					:data-nav-id="`user_${item.data.id}`"
					:name="item.data.object.label"
					:compact="isCompact"
					@click="emit('createAndJoinConversation', item.data.object)">
					<template #icon>
						<!-- @vue-expect-error: incomplete props from v-bind -->
						<AvatarWrapper
							:key="`user_${item.data.id}`"
							v-bind="item.data.icon" />
					</template>
					<template v-if="!isCompact" #subname>
						{{ t('spreed', 'New private conversation') }}
					</template>
				</NcListItem>
				<NcListItem
					v-else-if="item.data.type === 'group'"
					:data-nav-id="`group_${item.data.id}`"
					:name="item.data.object.label"
					:compact="isCompact"
					@click="emit('createAndJoinConversation', item.data.object)">
					<template #icon>
						<ConversationIcon
							:key="`group_${item.data.id}`"
							:item="item.data.icon"
							:size="iconSize" />
					</template>
					<template v-if="!isCompact" #subname>
						{{ t('spreed', 'New group conversation') }}
					</template>
				</NcListItem>
				<NcListItem
					v-else-if="item.data.type === 'circle'"
					:data-nav-id="`circle_${item.data.id}`"
					:name="item.data.object.label"
					:compact="isCompact"
					@click="emit('createAndJoinConversation', item.data.object)">
					<template #icon>
						<ConversationIcon
							:key="`circle_${item.data.id}`"
							:item="item.data.icon"
							:size="iconSize" />
					</template>
					<template v-if="!isCompact" #subname>
						{{ t('spreed', 'New group conversation') }}
					</template>
				</NcListItem>
				<NcListItem
					v-else-if="item.data.type === 'federated'"
					:data-nav-id="`federated_${item.data.id}`"
					:name="item.data.object.label"
					:compact="isCompact"
					@click="emit('createAndJoinConversation', item.data.object)">
					<template #icon>
						<!-- @vue-expect-error: incomplete props from v-bind -->
						<AvatarWrapper
							:key="`federated_${item.data.id}`"
							v-bind="item.data.icon" />
					</template>
					<template v-if="!isCompact" #subname>
						{{ t('spreed', 'New group conversation') }}
					</template>
				</NcListItem>
			</template>
		</ul>
	</li>
</template>

<style lang="scss" scoped>
// Overwrite NcListItem styles
// TOREMOVE: get rid of it or find better approach
:deep(.list-item) {
	outline-offset: -2px;
}

/* Overwrite NcListItem styles for compact view */
:deep(.list-item--compact) {
	padding-block: 0 !important;
}

:deep(.list-item--compact:not(:has(.list-item-content__subname))) {
	--list-item-height: calc(var(--clickable-area-small, 24px) + 4px) !important;
}

:deep(.list-item--compact .button-vue--size-normal) {
	--button-size: var(--clickable-area-small, 24px);
	--button-radius: var(--border-radius);
}

:deep(.list-item--compact .list-item-content__actions) {
	height: var(--clickable-area-small, 24px);
}
</style>
