<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">

import { t } from '@nextcloud/l10n'
import escapeHtml from 'escape-html'
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconPinOff from 'vue-material-design-icons/PinOffOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import IconPin from '../../../../img/material-icons/pin-outline.svg?raw'
import { AVATAR } from '../../..//constants.ts'
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
		pinnedMessage.value?.message,
		pinnedMessage.value?.messageParameters,
	)
})

// Array of all pinned messages
const pinnedMessages = computed(() => {
	if (!sharedItemsStore.sharedItems(token.value).pinned) {
		return []
	}
	return Object.values(sharedItemsStore.sharedItems(token.value).pinned) || []
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
const isPinnerSameAsAuthor = computed(() => pinnedMessage.value?.metaData?.pinnedActorId === pinnedMessage.value?.actorId
	&& pinnedMessage.value?.metaData?.pinnedActorType === pinnedMessage.value?.actorType)

const isPinnerSameAsCurrentUser = computed(() => pinnedMessage.value?.metaData?.pinnedActorId === actorStore.actorId
	&& pinnedMessage.value?.metaData?.pinnedActorType === actorStore.actorType)

const displayNameDetails = computed(() => {
	if (isPinnerSameAsAuthor.value) {
		return pinnedMessage.value?.actorDisplayName || ''
	}
	if (isPinnerSameAsCurrentUser.value) {
		return t('spreed', '{author} ({icon} by you)', {
			author: escapeHtml(pinnedMessage.value?.actorDisplayName || ''),
			icon: IconPin,
		}, { escape: false })
	}
	return t('spreed', '{author} ({icon} by {pinner})', {
		author: escapeHtml(pinnedMessage.value?.actorDisplayName || ''),
		icon: IconPin,
		pinner: escapeHtml(pinnedMessage.value?.metaData?.pinnedActorDisplayName || ''),
	}, { escape: false })
})

const isModerator = computed(() => store.getters.isModerator)
const isInThread = computed(() => pinnedMessage.value?.threadId !== pinnedMessage.value?.id)
const to = computed(() => ({
	name: 'conversation',
	hash: `#message_${pinnedMessage.value?.id}`,
	params: { token: conversation.value.token },
	query: { threadId: isInThread.value ? pinnedMessage.value?.threadId : undefined },
}))

/**
 * Handle click on pinned message
 */
function handlePinClick() {
	if (pinnedMessage.value?.id && route.hash === '#message_' + pinnedMessage.value?.id) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', { messageId: pinnedMessage.value?.id })
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
		// This is only needed on relaod for each conversation
		// Afterwards, pinned messages are added/removed via system messages
		sharedItemsStore.fetchPinnedMessages(token.value)
	}
})
</script>

<template>
	<div v-if="pinnedMessage">
		<NcListItem
			:title="richSubline"
			:active="false"
			:to="to"
			@click="handlePinClick">
			<template #icon>
				<AvatarWrapper
					:id="pinnedMessage.actorId"
					:name="pinnedMessage.actorDisplayName"
					:source="pinnedMessage.actorType"
					disable-menu
					:token="token"
					:size="AVATAR.SIZE.SMALL" />
			</template>
			<template #name>
				<!-- eslint-disable-next-line vue/no-v-html -->
				<span class="display-name" v-html="displayNameDetails" />
			</template>
			<template #subname>
				{{ richSubline }}
			</template>
			<template #actions>
				<NcActionButton
					close-after-click
					:title="t('spreed', 'Discard pin')"
					@click.stop="handleHidePinnedMessage">
					<template #icon>
						<IconClose :size="20" />
					</template>
					{{ t('spreed', 'Discard pin') }}
				</NcActionButton>
				<NcActionButton
					v-if="isModerator"
					close-after-click
					:title="t('spreed', 'Unpin message')"
					@click.stop="sharedItemsStore.handleUnpinMessage(token, pinnedMessage.id)">
					<template #icon>
						<IconPinOff :size="20" />
					</template>
					{{ t('spreed', 'Unpin message') }}
				</NcActionButton>
			</template>
		</NcListItem>
	</div>
</template>

<style scoped lang="scss">

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
</style>
