<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script setup lang="ts">
import type { PinnedChatMessage } from '../../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconPinOff from 'vue-material-design-icons/PinOffOutline.vue'
import IconPin from 'vue-material-design-icons/PinOutline.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { AVATAR } from '../../../constants.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.ts'
import { formatDateTime } from '../../../utils/formattedTime.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const { pinnedMessage } = defineProps<{ pinnedMessage: PinnedChatMessage }>()
const store = useStore()
const token = useGetToken()
const route = useRoute()
const sharedItemsStore = useSharedItemsStore()
const isModerator = computed(() => store.getters.isModerator)
const timeCaption = computed(() => {
	if (!pinnedMessage.metaData.pinnedUntil) {
		return ''
	}

	const absoluteDate = formatDateTime(pinnedMessage.metaData.pinnedUntil * 1000, 'shortDateWithTime')
	return t('spreed', 'until {absoluteDate}', { absoluteDate })
})

const subname = computed(() => {
	return parseToSimpleMessage(
		pinnedMessage.message,
		pinnedMessage.messageParameters,
	)
})

const to = computed(() => ({
	name: 'conversation',
	hash: `#message_${pinnedMessage.id}`,
	params: { token: token.value },
	query: { threadId: pinnedMessage.id !== pinnedMessage.id ? pinnedMessage.threadId : undefined },
}))

/**
 *  Unpin the message
 */
function unpinMessage() {
	sharedItemsStore.handleUnpinMessage(
		token.value,
		pinnedMessage.id,
	)
}

/**
 * Focus selected message
 */
function handleClick() {
	if (route.hash === '#message_' + pinnedMessage.id) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', { messageId: pinnedMessage.id })
	}
}
</script>

<template>
	<NcListItem
		:data-nav-id="`pin_${pinnedMessage.id}`"
		class="pin"
		:name="subname"
		:to="to"
		:active="false"
		force-menu
		@click="handleClick">
		<template #icon>
			<IconPin :size="0.6 * AVATAR.SIZE.DEFAULT" />
		</template>
		<template v-if="timeCaption" #name>
			<span>{{ timeCaption }}</span>
		</template>
		<template #subname>
			{{ subname }}
		</template>
		<template v-if="isModerator" #actions>
			<NcActionButton
				key="unpin-message"
				close-after-click
				@click="unpinMessage">
				<template #icon>
					<IconPinOff :size="20" />
				</template>
				{{ t('spreed', 'Unpin') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<style scoped lang="scss">
.pin {
    :deep(.list-item-content__name) {
        font-size: var(--font-size-small);
        font-weight: 400;
        color: var(--color-text-maxcontrast);
    }

    :deep(.list-item-content__subname) {
        color: var(--color-main-text);
    }
}
</style>
