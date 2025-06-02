<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { ParticipantSearchResult, Conversation as TypeConversation } from '../../../types/index.ts'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import { RecycleScroller } from 'vue-virtual-scroller'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconChatPlus from 'vue-material-design-icons/ChatPlus.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import Hint from '../../UIShared/Hint.vue'
import Conversation from '../ConversationsList/Conversation.vue'
import { ATTENDEE, AVATAR, CONVERSATION } from '../../../constants.ts'
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'
import { useSettingsStore } from '../../../stores/settings.js'
import { getPreloadedUserStatus } from '../../../utils/userStatus.ts'

const props = defineProps<{
	searchText: string
	conversationsList: TypeConversation[]
	contactsLoading: boolean
	searchResultsListedConversations: TypeConversation[]
	searchResults: ParticipantSearchResult[]
}>()

const emit = defineEmits<{
	(event: 'abort-search'): void
	(event: 'create-new-conversation', searchText: string): void
	(event: 'create-and-join-conversation', item: TypeConversation): void
}>()

const isCirclesEnabled = loadState('spreed', 'circles_enabled')
const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')
const settingsStore = useSettingsStore()
const isCompact = computed(() => settingsStore.conversationsListStyle === CONVERSATION.LIST_STYLE.COMPACT)

// Item's content (avatar) + internal_padding * 2 + external_padding * 2
const itemSize = computed(() => isCompact.value ? 28 + 2 * 2 + 0 * 2 : AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2)

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

const searchResultsVirtual = computed(() => {
	// Initialize
	const virtualList = []

	const lowerSearchText = props.searchText.toLowerCase()
	const searchResultsConversationList = props.conversationsList.filter((conversation) => conversation.displayName.toLowerCase().includes(lowerSearchText)
		|| conversation.name.toLowerCase().includes(lowerSearchText))

	// Add conversations section
	virtualList.push({ type: 'caption', id: 'conversations_caption', name: t('spreed', 'Conversations') })
	if (searchResultsConversationList.length === 0) {
		virtualList.push({ type: 'hint', id: 'hint_conversations', hint: t('spreed', 'No matches found') })
	} else {
		virtualList.push(...searchResultsConversationList.map((item) => ({ type: 'conversation', id: item.id, object: item })))
	}

	// Add "New Conversation" option if allowed
	if (canStartConversations) {
		virtualList.push({ type: 'action', id: 'new_conversation', name: props.searchText, subname: t('spreed', 'New private conversation') })
	}

	// Add open conversations section if any
	if (props.searchResultsListedConversations.length !== 0) {
		virtualList.push({ type: 'caption', id: 'open_conversation_caption', name: t('spreed', 'Open conversations') })
		virtualList.push(...props.searchResultsListedConversations.map((item) => ({ type: 'open_conversation', id: item.id, object: item })))
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

/**
 * Generate the props for the AvatarWrapper component
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
	<RecycleScroller ref="scroller"
		item-tag="ul"
		:items="searchResultsVirtual"
		type-field="type"
		:item-size="itemSize">
		<template #default="{ item }">
			<Conversation v-if="item.type === 'conversation'"
				:key="`conversation_${item.id}`"
				:ref="`conversation-${item.object.token}`"
				:item="item.object"
				:compact="isCompact"
				@click="emit('abort-search')" />
			<NcListItem v-else-if="item.type === 'action'"
				:name="searchText"
				:compact="isCompact"
				data-nav-id="conversation_create_new"
				@click="emit('create-new-conversation', searchText)">
				<template #icon>
					<IconChatPlus :size="iconSize" />
				</template>
				<template v-if="!isCompact" #subname>
					{{ t('spreed', 'New group conversation') }}
				</template>
			</NcListItem>
			<Conversation v-else-if="item.type === 'open_conversation'"
				:key="`open-conversation_${item.id}`"
				:item="item.object"
				is-search-result
				:compact="isCompact"
				@click="emit('abort-search')" />
			<NcAppNavigationCaption v-else-if="item.type === 'caption'"
				:name="item.name"
				tabindex="-1"
				:style="{
					height: itemSize + 'px',
					alignItems: isCompact ? 'unset' : 'self-end',
				}" />
			<Hint v-else-if="item.type === 'hint'"
				tabindex="-1"
				:hint="item.hint" />
			<NcListItem v-else-if="item.type === 'user'"
				:key="`user_${item.id}`"
				:data-nav-id="`user_${item.id}`"
				:name="item.object.label"
				:compact="isCompact"
				@click="emit('create-and-join-conversation', item.object)">
				<template #icon>
					<AvatarWrapper v-bind="item.icon" />
				</template>
				<template v-if="!isCompact" #subname>
					{{ t('spreed', 'New private conversation') }}
				</template>
			</NcListItem>
			<NcListItem v-else-if="item.type === 'group'"
				:key="`group_${item.id}`"
				:data-nav-id="`group_${item.id}`"
				:name="item.object.label"
				:compact="isCompact"
				@click="emit('create-and-join-conversation', item.object)">
				<template #icon>
					<ConversationIcon :item="item.icon" :size="iconSize" />
				</template>
				<template v-if="!isCompact" #subname>
					{{ t('spreed', 'New group conversation') }}
				</template>
			</NcListItem>
			<NcListItem v-else-if="item.type === 'circle'"
				:key="`circle_${item.id}`"
				:data-nav-id="`circle_${item.id}`"
				:name="item.object.label"
				:compact="isCompact"
				@click="emit('create-and-join-conversation', item.object)">
				<template #icon>
					<ConversationIcon :item="item.icon" :size="iconSize" />
				</template>
				<template v-if="!isCompact" #subname>
					{{ t('spreed', 'New group conversation') }}
				</template>
			</NcListItem>
			<NcListItem v-else-if="item.type === 'federated'"
				:key="`federated_${item.id}`"
				:data-nav-id="`federated_${item.id}`"
				:name="item.object.label"
				:compact="isCompact"
				@click="emit('create-and-join-conversation', item.object)">
				<template #icon>
					<AvatarWrapper v-bind="item.icon" />
				</template>
				<template v-if="!isCompact" #subname>
					{{ t('spreed', 'New group conversation') }}
				</template>
			</NcListItem>
		</template>
		<template #after>
			<!-- Search results: no results (yet) -->
			<Hint v-if="contactsLoading && !hasSourcesWithoutResults"
				:style="{ marginBlockStart: footerMargin }"
				tabindex="-1"
				:hint="t('spreed', 'Loading …')" />
		</template>
	</RecycleScroller>
</template>
