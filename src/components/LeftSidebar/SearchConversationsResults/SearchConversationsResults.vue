<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RecycleScroller } from 'vue-virtual-scroller'

import ChatPlus from 'vue-material-design-icons/ChatPlus.vue'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcListItem from '@nextcloud/vue/components/NcListItem'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import Hint from '../../UIShared/Hint.vue'
import Conversation from '../ConversationsList/Conversation.vue'

import { useStore } from '../../../composables/useStore.js'
import { ATTENDEE, CONVERSATION, AVATAR } from '../../../constants.ts'

const store = useStore()
const isCirclesEnabled = loadState('spreed', 'circles_enabled')

const props = defineProps<{
    searchText: string,
    isCompact: boolean,
    isFocused: boolean,
    conversationsList: Array<Record<string, any>>,
    contactsLoading: boolean,
    canStartConversations: boolean,
    searchResultsListedConversations: Array<Record<string, any>>,
    searchResults: Array<Record<string, any>>,

}>()

const searchResultsConversationList = computed(() => {
	if (props.searchText !== '' || props.isFocused) {
		const lowerSearchText = props.searchText.toLowerCase()
		return props.conversationsList.filter(conversation =>
			conversation.displayName.toLowerCase().includes(lowerSearchText)
            || conversation.name.toLowerCase().includes(lowerSearchText)
		)
	} else {
		return []
	}
})

/**
 * Check if the user has an existing one-to-one conversation with the given user
 *
 * @param userId
 */
function hasOneToOneConversationWith(userId: string) {
	return props.conversationsList.length > 0 && props.conversationsList.some((conversation) => {
		return conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && conversation.name === userId
	})
}

const hasResultsUsers = ref(false)
const hasResultsGroups = ref(false)
const hasResultsCircles = ref(false)
const hasResultsFederatedUsers = ref(false)

/**
 * Prepare the search results for the virtual list
 */
function prepareSearchResultsVirtual() {
	// Initialize
	const virtualList = []
	hasResultsUsers.value = false
	hasResultsGroups.value = false
	hasResultsCircles.value = false
	hasResultsFederatedUsers.value = false

	// Add conversations section
	virtualList.push({ type: 'caption', id: 'conversations_caption', name: t('spreed', 'Conversations') })
	if (searchResultsConversationList.value.length === 0) {
		virtualList.push({ type: 'hint', id: 'hint_conversations', hint: t('spreed', 'No matches found') })
	} else {
		virtualList.push(...searchResultsConversationList.value.map((item) => ({ type: 'conversation', id: item.id, object: item })))
	}

	// Add "New Conversation" option if allowed
	if (props.canStartConversations) {
		virtualList.push({ type: 'listItem', id: 'new_conversation', name: props.searchText, subname: t('spreed', 'New private conversation') })
	}

	// Add open conversations section if any
	if (props.searchResultsListedConversations.length !== 0) {
		virtualList.push({ type: 'caption', id: 'open_conversation_caption', name: t('spreed', 'Open conversations') })
		virtualList.push(...props.searchResultsListedConversations.map((item) => ({ type: 'open_conversation', id: item.id, object: item })))
	}

	// Other sections in the output order
	const sections = [
		{
			type: 'user',
			caption: { type: 'caption', id: 'users_caption', name: t('spreed', 'Users') },
			condition: (match) => match.source === ATTENDEE.ACTOR_TYPE.USERS && match.id !== store.getters.getUserId() && !hasOneToOneConversationWith(match.id),
		},
		{
			type: 'group',
			caption: { type: 'caption', id: 'groups_caption', name: t('spreed', 'Groups') },
			condition: (match) => match.source === ATTENDEE.ACTOR_TYPE.GROUPS && props.canStartConversations,
		},
		{
			type: 'circle',
			caption: { type: 'caption', id: 'circles_caption', name: t('spreed', 'Teams') },
			condition: (match) => match.source === ATTENDEE.ACTOR_TYPE.CIRCLES && props.canStartConversations,
		},
		{
			type: 'federated',
			caption: { type: 'caption', id: 'federated_users_caption', name: t('spreed', 'Federated users') },
			condition: (match) => match.source === ATTENDEE.ACTOR_TYPE.REMOTES && props.canStartConversations,
			transform: (match) => ({ ...match, source: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS }),
		},
	]

	// Iterate over sections and build the virtualList
	sections.forEach((section) => {
		const items = props.searchResults
			.filter(section.condition)
			.map((match) => ({
				type: section.type,
				id: `${section.type}_${match.id}`,
				object: section.transform ? section.transform(match) : match,
			}))

		if (items.length > 0) {
			virtualList.push(section.caption)
			virtualList.push(...items)

			// Update hasResults flags
			hasResultsUsers.value = hasResultsUsers.value || section.type === 'user'
			hasResultsGroups.value = hasResultsGroups.value || section.type === 'group'
			hasResultsCircles.value = hasResultsCircles.value || section.type === 'circle'
			hasResultsFederatedUsers.value = hasResultsFederatedUsers.value || section.type === 'federated'
		}
	})

	return virtualList
}

const searchResultsVirtual = computed(() => prepareSearchResultsVirtual())

/**
 * Generate the props for the AvatarWrapper component
 * @param item conversation item
 */
function iconData(item : Record<string, any>) {
	if (item.source === ATTENDEE.ACTOR_TYPE.USERS
        || item.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
		return {
			id: item.id,
			name: item.label,
			source: item.source,
			disableMenu: true,
			token: 'new',
			showUserStatus: true,
			size: props.isCompact ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT,
		}
	}
	return {
		type: CONVERSATION.TYPE.GROUP,
		name: item.label,
		objectType: item.source,
		size: props.isCompact ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT,
	}
}

const emit = defineEmits<{
	(event: 'abort-search'): void,
    (event: 'create-new-conversation', searchText: string): void,
    (event: 'create-and-join-conversation', item: Record<string, any>): void,
}>()

const sourcesWithoutResults = computed(() => {
	return !hasResultsUsers.value
		|| !hasResultsGroups.value
		|| (isCirclesEnabled && !hasResultsCircles.value)
})

const hasNoCirclesResults = computed(() => {
	return isCirclesEnabled && !hasResultsCircles.value
})

const sourcesWithoutResultsList = computed(() => {
	if (!hasResultsUsers.value) {
		if (!hasResultsGroups.value) {
			return (hasNoCirclesResults.value)
				? t('spreed', 'Users, groups and teams')
				: t('spreed', 'Users and groups')
		} else {
			return (hasNoCirclesResults.value)
				? t('spreed', 'Users and teams')
				: t('spreed', 'Users')
		}
	} else {
		if (!hasResultsGroups.value) {
			return (hasNoCirclesResults.value)
				? t('spreed', 'Groups and teams')
				: t('spreed', 'Groups')
		} else {
			return (hasNoCirclesResults.value)
				? t('spreed', 'Teams')
				: t('spreed', 'Other sources')
		}
	}
})
</script>
<template>
		<RecycleScroller ref="scroller"
			item-tag="ul"
			:items="searchResultsVirtual"
			type-field="type"
			:item-size="56">
			<template #default="{ item }">
				<Conversation v-if="item.type === 'conversation'"
					:key="`conversation_${item.id}`"
					:ref="`conversation-${item.object.token}`"
					:item="item.object"
					:compact="isCompact"
					@click="emit('abort-search')" />
				<NcListItem v-else-if="item.type === 'listItem'"
					:name="searchText"
					:compact="isCompact"
					data-nav-id="conversation_create_new"
					@click="emit('create-new-conversation', searchText)">
					<template #icon>
						<ChatPlus :size="isCompact ? AVATAR.SIZE.COMPACT: AVATAR.SIZE.DEFAULT" />
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
				<NcAppNavigationCaption v-else-if="item.type === 'caption'" :name="item.name" />
				<Hint v-else-if="item.type === 'hint'" :hint="item.hint" />
				<NcListItem v-else-if="item.type === 'user'"
					:key="`user_${item.id}`"
					:data-nav-id="`user_${item.id}`"
					:name="item.object.label"
					:compact="isCompact"
					@click="emit('create-and-join-conversation', item.object)">
					<template #icon>
						<AvatarWrapper v-bind="iconData(item.object)" />
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
						<ConversationIcon :item="iconData(item.object)" :size="isCompact ? AVATAR.SIZE.COMPACT: AVATAR.SIZE.DEFAULT" />
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
						<ConversationIcon :item="iconData(item.object)" :size="isCompact ? AVATAR.SIZE.COMPACT: AVATAR.SIZE.DEFAULT" />
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
						<AvatarWrapper v-bind="iconData(item.object)" />
					</template>
					<template v-if="!isCompact" #subname>
						{{ t('spreed', 'New group conversation') }}
					</template>
				</NcListItem>
			</template>
			<template #after>
				<!-- Search results: no results (yet) -->
				<template v-if="sourcesWithoutResults">
					<NcAppNavigationCaption :name="sourcesWithoutResultsList" />
					<Hint :hint="t('spreed', 'No search results')" />
				</template>
				<Hint v-else-if="contactsLoading" :hint="t('spreed', 'Loading …')" />
			</template>
		</RecycleScroller>
</template>
<style lang="scss" scoped>

</style>