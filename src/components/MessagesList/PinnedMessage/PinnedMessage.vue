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
import IconPinOff from 'vue-material-design-icons/PinOffOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import { AVATAR } from '../../..//constants.ts'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const store = useStore()
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
const pinnedMessage = computed(() => {
	if (!sharedItemsStore.sharedItems(token.value).pinned) {
		return null
	}
	return Object.values(sharedItemsStore.sharedItems(token.value).pinned).find((item) => +item.id === conversation.value.lastPinnedId)
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
 *
 */
function handlePinClick() {
	if (pinnedMessage.value?.id && route.hash === '#message_' + pinnedMessage.value.id) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', { messageId: pinnedMessage.value.id })
	}
}
onMounted(() => {
	if (!sharedItemsStore.sharedItems(token.value).pinned) {
		sharedItemsStore.fetchPinnedMessages(token.value)
	}
})
</script>

<template>
	<div v-if="pinnedMessage">
		<NcListItem
			:name="pinnedMessage.actorDisplayName"
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
			<template #subname>
				{{ richSubline }}
			</template>
			<template #actions>
				<NcActionButton
					close-after-click
					:title="t('spreed', 'Dismiss')"
					@click.stop="handleHidePinnedMessage">
					<template #icon>
						<IconClose :size="20" />
					</template>
					{{ t('spreed', 'Dismiss') }}
				</NcActionButton>
				<NcActionButton
					v-if="isModerator"
					close-after-click
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

:deep(.list-item__wrapper) {
    padding: 0 !important;
}
</style>
