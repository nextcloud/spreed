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
import { useActorStore } from '../../../stores/actor.ts'
import { useSharedItemsStore } from '../../../stores/sharedItems.ts'
import { formatDateTime } from '../../../utils/formattedTime.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const { item } = defineProps<{ item: PinnedChatMessage }>()
const store = useStore()
const token = useGetToken()
const route = useRoute()
const actorStore = useActorStore()
const sharedItemsStore = useSharedItemsStore()
const isModerator = computed(() => store.getters.isModerator)

const timeCaption = computed(() => {
	if (!item.metaData.pinnedUntil) {
		const isPinnerSameAsCurrentUser = actorStore.checkIfSelfIsActor({
			actorType: item.metaData.pinnedActorType,
			actorId: item.metaData.pinnedActorId,
		})
		if (isPinnerSameAsCurrentUser) {
			return t('spreed', 'Pinned by you')
		} else if (item.metaData.pinnedActorDisplayName) {
			return t('spreed', 'Pinned by {actor}', {
				actor: item.metaData.pinnedActorDisplayName,
			})
		} else {
			return t('spreed', 'Pinned by a deleted user')
		}
	}
	const isSameYear = new Date(item.metaData.pinnedUntil * 1000).getFullYear() === new Date().getFullYear()
	const absoluteDate = formatDateTime(item.metaData.pinnedUntil * 1000, isSameYear ? 'longDateSameYear' : 'shortDate')
	return t('spreed', 'Until {absoluteDate}', { absoluteDate })
})

const subname = computed(() => {
	return parseToSimpleMessage(
		item.message,
		item.messageParameters,
	)
})

const to = computed(() => ({
	name: 'conversation',
	hash: `#message_${item.id}`,
	params: { token: token.value },
	query: { threadId: (item.isThread && item.threadId !== item.id) ? item.threadId : undefined },
}))

/**
 *  Unpin the message
 */
function unpinMessage() {
	sharedItemsStore.handleUnpinMessage(
		token.value,
		item.id,
	)
}

/**
 * Focus selected message
 */
function handleClick() {
	if (route.hash === '#message_' + item.id) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', { messageId: item.id })
	}
}
</script>

<template>
	<NcListItem
		:data-nav-id="`pin_${item.id}`"
		class="pin"
		:name="subname"
		:to="to"
		:active="false"
		forceMenu
		@click="handleClick">
		<template #icon>
			<IconPin :size="AVATAR.SIZE.COMPACT" class="icon-pin" />
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
				closeAfterClick
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

.icon-pin {
	width: 40px; // Align with thread item avatar width
}
</style>
