<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'
import { RecycleScroller } from 'vue-virtual-scroller'

import ChatPlus from 'vue-material-design-icons/ChatPlus.vue'

import { t } from '@nextcloud/l10n'

import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcListItem from '@nextcloud/vue/components/NcListItem'

import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import Hint from '../../UIShared/Hint.vue'
import Conversation from '../ConversationsList/Conversation.vue'

import { ATTENDEE, CONVERSATION, AVATAR } from '../../../constants.ts'

const props = defineProps<{
    searchText: string,
    isCompact: boolean,
    isFocused: boolean,
    conversationsList: Array<Record<string, any>>,
    canStartConversations: boolean,
    searchResultsListedConversations: Array<Record<string, any>>,
    searchResultsUsers: Array<Record<string, any>>,
    searchResultsGroups: Array<Record<string, any>>,
    searchResultsCircles: Array<Record<string, any>>,
    searchResultsFederated: Array<Record<string, any>>,

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
const searchResultsVirtual = computed(() => {
	const virtualList = []
	virtualList.push({ type: 'caption', id: 'conversations_caption', name: t('spreed', 'Conversations') })
	if (searchResultsConversationList.value.length === 0) {
		virtualList.push({ type: 'hint', id: 'hint_conversations', hint: t('spreed', 'No matches found') })
	} else {
		virtualList.push(...searchResultsConversationList.value.map((item) => ({ type: 'conversation', id: item.id, object: item })))
	}
	if (props.canStartConversations) {
		virtualList.push({ type: 'listItem', id: 'new_conversation', name: props.searchText, subname: t('spreed', 'New private conversation') })
	}
	if (props.searchResultsListedConversations.length !== 0) {
		virtualList.push({ type: 'caption', id: 'open_conversation_caption', name: t('spreed', 'Open conversations') })
		virtualList.push(...props.searchResultsListedConversations.map((item) => ({ type: 'open_conversation', id: item.id, object: item })))
	}
	if (props.searchResultsUsers.length !== 0) {
		virtualList.push({ type: 'caption', id: 'users_caption', name: t('spreed', 'Users') })
		virtualList.push(...props.searchResultsUsers.map((item) => ({ type: 'user', id: item.id, object: item })))
	}
	if (props.canStartConversations) {
		if (props.searchResultsGroups.length !== 0) {
			virtualList.push({ type: 'caption', id: 'groups_caption', name: t('spreed', 'Groups') })
			virtualList.push(...props.searchResultsGroups.map((item) => ({ type: 'group', id: item.id, object: item })))
		}
		if (props.searchResultsCircles.length !== 0) {
			virtualList.push({ type: 'caption', id: 'circles_caption', name: t('spreed', 'Teams') })
			virtualList.push(...props.searchResultsCircles.map((item) => ({ type: 'circle', id: item.id, object: item })))
		}
		if (props.searchResultsFederated.length !== 0) {
			virtualList.push({ type: 'caption', id: 'federated_users_caption', name: t('spreed', 'Federated users') })
			virtualList.push(...props.searchResultsFederated.map((item) => ({ type: 'federated', id: item.id, object: item })))
		}
	}
	return virtualList
})

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
	</RecycleScroller>
</template>
<style lang="scss" scoped>

</style>