<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">

import { t } from '@nextcloud/l10n'
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconPin from 'vue-material-design-icons/Pin.vue'
import IconPinOff from 'vue-material-design-icons/PinOffOutline.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const store = useStore()
const actorStore = useActorStore()
const route = useRoute()
const token = useGetToken()
const sharedItemsStore = useSharedItemsStore()
const conversation = computed(() => store.getters.conversation(token.value))
const richSubline = computed(() => {
	if (!pinnedMessage.value) {
		return ''
	}
	return parseToSimpleMessage(
		pinnedMessage.value.message,
		pinnedMessage.value.messageParameters,
	)
})

// Array of all pinned messages
const pinnedMessages = computed(() => {
	if (!sharedItemsStore.sharedItems(token.value).pinned) {
		return []
	}
	return Object.values(sharedItemsStore.sharedItems(token.value).pinned ?? {})
})

// The pinned message to be displayed (the latest one that is not hidden)
const pinnedMessage = computed(() => {
	if (!pinnedMessages.value.length) {
		return null
	}
	const item = pinnedMessages.value.find((item) => +item.id === conversation.value.lastPinnedId)

	if (!item) {
		// Id not found (e.g: deleted, expired), return most recent pinned message
		const fallbackId = sharedItemsStore.findMostRecentPinnedMessageId(token.value)
		return pinnedMessages.value.find((item) => +item.id === fallbackId)
	}
	return item.id !== conversation.value.hiddenPinnedId ? item : null
})

// Display name for the pinned message
const displayNameDetails = computed(() => {
	if (!pinnedMessage.value?.metaData) {
		return ''
	}
	const metaData = pinnedMessage.value.metaData
	const isPinnerSameAsAuthor = metaData.pinnedActorId === pinnedMessage.value.actorId
		&& metaData.pinnedActorType === pinnedMessage.value.actorType

	if (isPinnerSameAsAuthor) {
		return pinnedMessage.value.actorDisplayName || ''
	}
	const isPinnerSameAsCurrentUser = metaData.pinnedActorId === actorStore.actorId
		&& metaData.pinnedActorType === actorStore.actorType

	if (isPinnerSameAsCurrentUser) {
		return t('spreed', '{author} (pinned by you)', {
			author: pinnedMessage.value.actorDisplayName || '',
		})
	}
	return t('spreed', '{author} (pinned by {actor})', {
		author: pinnedMessage.value.actorDisplayName || '',
		actor: metaData.pinnedActorDisplayName || '',
	})
})

const isModerator = computed(() => store.getters.isModerator)
const isInThread = computed(() => pinnedMessage.value?.isThread && pinnedMessage.value.threadId !== pinnedMessage.value.id)
const to = computed(() => ({
	name: 'conversation',
	hash: `#message_${pinnedMessage.value!.id}`,
	params: { token: conversation.value.token },
	query: { threadId: isInThread.value ? pinnedMessage.value!.threadId : undefined },
}))

/**
 * Handle click on pinned message
 */
function handlePinClick() {
	if (pinnedMessage.value?.id && route.hash === '#message_' + pinnedMessage.value.id) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', { messageId: pinnedMessage.value.id })
	}
}

/**
 * Handle hiding the pinned message
 */
function handleHidePinnedMessage() {
	sharedItemsStore.handleHidePinnedMessage(token.value, pinnedMessage.value!.id)
}

onMounted(() => {
	if (!sharedItemsStore.sharedItems(token.value).pinned) {
		// This is only needed on reload for each conversation
		// Afterwards, pinned messages are added/removed via system messages
		sharedItemsStore.fetchPinnedMessages(token.value)
	}
})
</script>

<template>
	<div v-if="pinnedMessage" class="pin_wrapper">
		<NcListItem
			:title="richSubline"
			:active="false"
			:to="to"
			@click="handlePinClick">
			<template #icon>
				<IconPin class="pin-icon" :size="20" />
			</template>
			<template #name>
				<span class="display-name">
					{{ displayNameDetails }}
				</span>
			</template>
			<template #subname>
				{{ richSubline }}
			</template>
			<template #actions>
				<NcActionButton
					closeAfterClick
					:title="t('spreed', 'Dismiss')"
					@click.stop="handleHidePinnedMessage">
					<template #icon>
						<IconClose :size="20" />
					</template>
					{{ t('spreed', 'Dismiss') }}
				</NcActionButton>
				<NcActionButton
					v-if="isModerator"
					closeAfterClick
					:title="t('spreed', 'Unpin')"
					@click.stop="sharedItemsStore.handleUnpinMessage(token, pinnedMessage.id)">
					<template #icon>
						<IconPinOff :size="20" />
					</template>
					{{ t('spreed', 'Unpin') }}
				</NcActionButton>
			</template>
		</NcListItem>
	</div>
</template>

<style scoped lang="scss">

.pin_wrapper:before {
	content: '';
    top: 0;
	height: 100%;
	width: 8px;
	background-color: var(--color-primary-element);
	position: absolute;
	inset-inline-start: 0;
	border-radius: var(--border-radius-container) 0 0 var(--border-radius-container);
	z-index: 1;
}

.display-name {
	display: flex;
	align-items: center;
	gap: 1px;

	:deep(svg) {
		fill: currentColor;
		width: 14px;
		height: 14px;
		vertical-align: middle;
	}
}

.pin-icon {
	width: 40px; // AVATAR.SIZE.DEFAULT
	height: 40px;
	color: var(--color-primary-element);
}

:deep(.list-item__wrapper) {
	padding: 0 !important;
}

:deep(.list-item-content__name) {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small);
	display: flex;
	align-items: center;
	flex-direction: row;
	gap: 1px;
}

:deep(.list-item-content__subname) {
	color: var(--color-main-text) !important;
}
</style>
