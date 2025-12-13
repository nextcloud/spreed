<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { BigIntChatMessage } from '../../../../../types/index.ts'
import type { RawTemporaryMessagePayload } from '../../../../../utils/prepareTemporaryMessage.ts'

import { t } from '@nextcloud/l10n'
import { computed, inject, ref } from 'vue'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcActionText from '@nextcloud/vue/components/NcActionText'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconAlarm from 'vue-material-design-icons/Alarm.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconCalendarClockOutline from 'vue-material-design-icons/CalendarClockOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconSend from 'vue-material-design-icons/Send.vue'
import IconSendVariantClock from 'vue-material-design-icons/SendVariantClock.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import { useTemporaryMessage } from '../../../../../composables/useTemporaryMessage.ts'
import { EventBus } from '../../../../../services/EventBus.ts'
import { useChatExtrasStore } from '../../../../../stores/chatExtras.ts'
import { convertToUnix, formatDateTime } from '../../../../../utils/formattedTime.ts'
import { getCustomDateOptions } from '../../../../../utils/getCustomDateOptions.ts'

const props = defineProps<{
	message: BigIntChatMessage
	isActionMenuOpen: boolean
}>()

const emit = defineEmits<{
	(event: 'update:isActionMenuOpen', value: boolean): void
	(event: 'edit'): void
}>()

const getMessagesListScroller = inject('getMessagesListScroller', () => undefined)

const chatExtrasStore = useChatExtrasStore()
const vuexStore = useStore()

const { createTemporaryMessage } = useTemporaryMessage()

const submenu = ref<'schedule' | null>(null)
const customScheduleTimestamp = ref(new Date(new Date().setHours(new Date().getHours() + 1, 0, 0, 0)))

const messageDateTime = computed(() => {
	return formatDateTime(props.message.timestamp * 1000, 'shortDateWithTime')
})

/**
 * Edit the scheduled message (trigger editing mode)
 */
async function handleEdit() {
	emit('edit')
}

/**
 * Edit the scheduled message (sendAt only)
 *
 * @param timestamp new scheduled timestamp (in ms)
 */
async function handleReschedule(timestamp: number) {
	await chatExtrasStore.editScheduledMessage(props.message.token, props.message.id, {
		message: props.message.message,
		sendAt: convertToUnix(timestamp),
	})
}

/**
 * Delete the scheduled message
 */
async function handleDelete() {
	await chatExtrasStore.deleteScheduledMessage(props.message.token, props.message.id)
}

/**
 * Delete the scheduled message
 */
async function handleSubmit() {
	const temporaryMessagePayload: RawTemporaryMessagePayload = {
		message: props.message.message,
		token: props.message.token,
		silent: props.message.silent,
	}

	if ((props.message.threadId ?? 0) > 0) {
		temporaryMessagePayload.threadId = props.message.threadId
		temporaryMessagePayload.isThread = true
	}
	if (props.message.parent?.id && !props.message.parent.deleted) {
		temporaryMessagePayload.parent = props.message.parent
	}
	if (props.message.threadId === -1) {
		// Substitute thread title with message text, if missing
		temporaryMessagePayload.threadTitle = props.message.threadTitle
		temporaryMessagePayload.threadReplies = 0
		temporaryMessagePayload.isThread = true
	}

	const temporaryMessage = createTemporaryMessage(temporaryMessagePayload)

	// FIXME: quite scheduled messages view
	// FIXME: Scroll to bottom after sending the scheduled message
	EventBus.emit('scroll-chat-to-bottom', { smooth: true, force: true })

	await vuexStore.dispatch('postNewMessage', { token: props.message.token, temporaryMessage })
	await chatExtrasStore.deleteScheduledMessage(props.message.token, props.message.id)
}

/**
 * Toggle action menu open state
 */
function onMenuOpen() {
	emit('update:isActionMenuOpen', true)
}

/**
 * Toggle action menu open state
 */
function onMenuClose() {
	emit('update:isActionMenuOpen', false)
}
</script>

<template>
	<div>
		<NcButton
			v-if="!isActionMenuOpen"
			variant="tertiary"
			:aria-label="t('spreed', 'More actions')"
			:title="t('spreed', 'More actions')"
			@click="onMenuOpen">
			<template #icon>
				<IconDotsHorizontal :size="20" />
			</template>
		</NcButton>
		<NcActions
			v-else
			force-menu
			open
			placement="bottom-end"
			:boundaries-element="getMessagesListScroller()"
			@close="onMenuClose">
			<template v-if="submenu === null">
				<!-- Message timestamp -->
				<NcActionText>
					<template #icon>
						<IconSendVariantClock :size="20" />
					</template>
					{{ messageDateTime }}
				</NcActionText>

				<NcActionButton
					key="set-schedule-menu"
					is-menu
					@click.stop="submenu = 'schedule'">
					<template #icon>
						<IconAlarm :size="20" />
					</template>
					{{ t('spreed', 'Reschedule') }}
				</NcActionButton>

				<NcActionSeparator />

				<NcActionButton
					key="edit-message"
					close-after-click
					@click.stop="handleEdit">
					<template #icon>
						<IconPencilOutline :size="20" />
					</template>
					{{ t('spreed', 'Edit message') }}
				</NcActionButton>
				<NcActionButton
					key="delete-message"
					close-after-click
					@click.stop="handleDelete">
					<template #icon>
						<IconTrashCanOutline :size="20" />
					</template>
					{{ t('spreed', 'Delete') }}
				</NcActionButton>

				<NcActionSeparator />

				<NcActionButton
					key="send-message"
					close-after-click
					@click.stop="handleSubmit">
					<template #icon>
						<IconSend :size="20" />
					</template>
					{{ t('spreed', 'Send message now') }}
				</NcActionButton>
			</template>

			<template v-else-if="submenu === 'schedule'">
				<NcActionButton
					key="action-back"
					:aria-label="t('spreed', 'Back')"
					@click.stop="submenu = null">
					<template #icon>
						<IconArrowLeft class="bidirectional-icon" />
					</template>
					{{ t('spreed', 'Back') }}
				</NcActionButton>

				<NcActionSeparator />

				<NcActionButton
					v-for="option in getCustomDateOptions()"
					:key="option.key"
					:aria-label="option.ariaLabel"
					close-after-click
					@click.stop="handleReschedule(option.timestamp)">
					{{ option.label }}
				</NcActionButton>

				<NcActionInput
					v-model="customScheduleTimestamp"
					type="datetime-local"
					:label="t('spreed', 'Custom date and time')"
					:min="new Date()"
					:step="300"
					is-native-picker>
					<template #icon>
						<IconCalendarClockOutline :size="20" />
					</template>
				</NcActionInput>

				<NcActionButton
					key="custom-time-submit"
					:disabled="!customScheduleTimestamp"
					close-after-click
					@click.stop="handleReschedule(customScheduleTimestamp.valueOf())">
					<template #icon>
						<IconCheck :size="20" />
					</template>
					{{ t('spreed', 'Send on custom time') }}
				</NcActionButton>
			</template>
		</NcActions>
	</div>
</template>
