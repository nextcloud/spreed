<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { RouteLocationAsRelative } from 'vue-router'
import type { ChatMessage, Conversation } from '../../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import ConversationIcon from '../../ConversationIcon.vue'
import { AVATAR, CONVERSATION } from '../../../constants.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useDashboardStore } from '../../../stores/dashboard.ts'
import { formatDateTime } from '../../../utils/formattedTime.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const props = withDefaults(defineProps<{
	messageId: number
	title: string
	to: RouteLocationAsRelative
	subline: string
	actorId: string
	actorType: string
	token: string
	timestamp: number
	messageParameters?: ChatMessage['messageParameters']
	isReminder?: boolean
	compact?: boolean
}>(), {
	messageParameters: () => ({}),
	isReminder: false,
})

const router = useRouter()
const route = useRoute()
const store = useStore()
const dashboardStore = useDashboardStore()

const conversation = computed<Conversation | undefined>(() => store.getters.conversation(props.token))
const isOneToOneConversation = computed(() => conversation.value?.type === CONVERSATION.TYPE.ONE_TO_ONE)
const name = computed(() => {
	if (!props.isReminder || isOneToOneConversation.value) {
		return props.title
	}
	return t('spreed', '{actor} in {conversation}', { actor: props.title, conversation: conversation.value?.displayName ?? '' }, { escape: false, sanitize: false })
})
const richSubline = computed(() => {
	if (!props.isReminder) {
		return props.subline
	}

	return parseToSimpleMessage(props.subline, props.messageParameters)
})
const clearReminderLabel = computed(() => {
	if (!props.isReminder) {
		return ''
	}
	return t('spreed', 'Clear reminder – {timeLocale}', { timeLocale: formatDateTime(props.timestamp * 1000, 'shortWeekdayWithTime') })
})

const active = computed(() => {
	return route.fullPath === router.resolve(props.to).fullPath
})

/**
 * Focus selected message
 */
function handleResultClick() {
	if (route.hash === '#message_' + props.messageId) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', { messageId: props.messageId })
	}
}
</script>

<template>
	<NcListItem
		:data-nav-id="`message_${messageId}`"
		:name="name"
		:to="to"
		:active="active"
		:title="richSubline"
		:compact
		class="search-message"
		force-menu
		@click="handleResultClick">
		<template #icon>
			<AvatarWrapper
				v-if="!isReminder || isOneToOneConversation"
				:id="actorId"
				:name="title"
				:source="actorType"
				:size="compact ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT"
				disable-menu
				:token="token" />
			<ConversationIcon
				v-else
				:item="conversation"
				hide-user-status />
		</template>
		<template v-if="!compact" #subname>
			{{ richSubline }}
		</template>
		<template v-if="isReminder" #actions>
			<NcActionButton
				close-after-click
				@click.stop="dashboardStore.removeReminder(token, messageId)">
				<template #icon>
					<CloseCircleOutline :size="20" />
				</template>
				{{ clearReminderLabel }}
			</NcActionButton>
		</template>
		<template #details>
			<NcDateTime
				:timestamp="timestamp * 1000"
				class="search-message__date"
				relative-time="short"
				ignore-seconds />
		</template>
	</NcListItem>
</template>

<style lang="scss" scoped>
.search-message {
	&__date {
		font-size: x-small;
	}

	/* Overwrite NcListItem styles for compact view */
	:deep(.list-item--compact .list-item-content__name) {
		font-weight: 400;
	}
}
</style>
