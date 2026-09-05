<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<a
		v-if="accessible"
		class="talk-reference-call"
		:href="richObject.link"
		target="_blank"
		rel="noopener noreferrer">
		<NcLoadingIcon v-if="loading" :size="32" />

		<template v-else>
			<ConversationIcon
				v-if="conversation"
				:item="conversation"
				:size="AVATAR.SIZE.DEFAULT"
				hideUserStatus
				:hideCall="isMessageReference" />
			<img
				v-else-if="fallbackAvatarUrl"
				:src="fallbackAvatarUrl"
				:alt="displayName"
				class="talk-reference-call__fallback-avatar">

			<span class="talk-reference-call__body">
				<span class="talk-reference-call__title">{{ title }}</span>
				<span class="talk-reference-call__type">{{ referenceType }}</span>
				<span v-if="subtitle" class="talk-reference-call__subtitle">{{ subtitle }}</span>
				<span v-if="!isMessageReference && lastMessagePreview" class="talk-reference-call__preview">{{ lastMessagePreview }}</span>
				<span v-if="roomMetadata" class="talk-reference-call__metadata">{{ roomMetadata }}</span>
			</span>
		</template>
	</a>
</template>

<script setup lang="ts">
import type { Conversation, TalkReferenceRichObject } from '../../types/index.ts'

import { n, t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ConversationIcon from '../ConversationIcon.vue'
import { AVATAR, MESSAGE } from '../../constants.ts'
import { fetchConversation } from '../../services/conversationsService.ts'
import { formatDateTime } from '../../utils/formattedTime.ts'
import { getDisplayNameWithFallback } from '../../utils/getDisplayName.ts'
import { parseToSimpleMessage } from '../../utils/textParse.ts'

const props = defineProps<{
	richObject: TalkReferenceRichObject
	accessible: boolean
	referenceTitle?: string | null
	referenceDescription?: string | null
	/** Server-rendered fallback avatar (open graph thumbnail), used only until/if the live conversation loads */
	fallbackAvatarUrl?: string | null
}>()

const loading = ref(true)
const conversation = ref<Conversation | null>(null)
let cancelled = false

onMounted(async () => {
	if (!props.accessible) {
		loading.value = false
		return
	}

	try {
		const response = await fetchConversation(props.richObject.id)
		if (!cancelled) {
			conversation.value = response.data.ocs.data
		}
	} catch (error) {
		// Show less rather than showing something wrong: fall back to the reference metadata
		console.debug('Could not load live Talk conversation data for reference widget', error)
	} finally {
		if (!cancelled) {
			loading.value = false
		}
	}
})

onBeforeUnmount(() => {
	cancelled = true
})

const displayName = computed(() => conversation.value?.displayName || props.richObject.name)

const isMessageReference = computed(() => Boolean(props.richObject['message-id']))

const title = computed(() => {
	if (isMessageReference.value) {
		return props.referenceTitle || props.richObject.name
	}

	return displayName.value
})

const subtitle = computed(() => {
	if (isMessageReference.value) {
		return props.referenceDescription || ''
	}

	return conversation.value?.description || props.referenceDescription || ''
})

const referenceType = computed(() => {
	if (isMessageReference.value) {
		return t('spreed', 'Message')
	}

	switch (conversation.value?.type ?? props.richObject['call-type']) {
		case 'one2one':
		case 1:
			return t('spreed', 'One-to-one conversation')
		case 'public':
		case 3:
			return t('spreed', 'Public conversation')
		case 'group':
		case 2:
			return t('spreed', 'Group conversation')
		default:
			return t('spreed', 'Conversation')
	}
})

const roomMetadata = computed(() => {
	if (isMessageReference.value || !conversation.value) {
		return ''
	}

	const metadata = []
	const timestamp = conversation.value.lastMessage?.timestamp
	if (timestamp) {
		metadata.push(formatDateTime(timestamp * 1000, 'shortDateWithTime'))
	}
	if (conversation.value.unreadMessages > 0) {
		metadata.push(n('spreed', '{count} unread message', '{count} unread messages', conversation.value.unreadMessages, { count: conversation.value.unreadMessages }))
	}
	if (conversation.value.hasCall) {
		metadata.push(t('spreed', 'Call in progress'))
	}

	return metadata.join(' · ')
})

const fallbackAvatarUrl = computed(() => props.fallbackAvatarUrl ?? null)

/**
 * A short "actor: message" preview of the conversation's last message.
 * Only shown when the live conversation response demonstrably includes a non-expired,
 * non-deleted, non-system message; otherwise omitted entirely.
 */
const lastMessagePreview = computed(() => {
	const lastMessage = conversation.value?.lastMessage
	if (!lastMessage) {
		return ''
	}

	if (lastMessage.messageType === MESSAGE.TYPE.COMMENT_DELETED || lastMessage.systemMessage) {
		return ''
	}

	if (lastMessage.expirationTimestamp !== 0 && lastMessage.expirationTimestamp * 1000 <= Date.now()) {
		return ''
	}

	const text = parseToSimpleMessage(lastMessage.message, lastMessage.messageParameters)
	if (!text) {
		return ''
	}

	const actor = getDisplayNameWithFallback(lastMessage.actorDisplayName, lastMessage.actorType)
	return actor ? `${actor}: ${text}` : text
})
</script>

<style lang="scss" scoped>
.talk-reference-call {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 2);
	color: var(--color-main-text);
	text-decoration: none;

	&:hover,
	&:focus {
		background-color: var(--color-background-hover);
		border-radius: var(--border-radius-large);
	}

	&__fallback-avatar {
		width: v-bind('`${AVATAR.SIZE.DEFAULT}px`');
		height: v-bind('`${AVATAR.SIZE.DEFAULT}px`');
		border-radius: 50%;
		object-fit: cover;
	}

	&__body {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	&__title {
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: normal;
	}

	&__type {
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small);
	}

	&__subtitle {
		color: var(--color-text-maxcontrast);
		white-space: normal;
	}

	&__preview {
		margin-top: var(--default-grid-baseline);
		white-space: normal;
	}

	&__metadata {
		margin-top: var(--default-grid-baseline);
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small);
		white-space: normal;
	}
}
</style>
