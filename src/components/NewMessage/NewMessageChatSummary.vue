<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- eslint-disable vue/multiline-html-element-content-newline -->
<template>
	<NcAssistantContent class="chat-summary">
		<div class="chat-summary__container">
			<NcAssistantIcon class="chat-summary__icon" />

			<div class="chat-summary__content">
				<div class="chat-summary__header">
					<p class="chat-summary__caption">
						{{ chatSummaryCaption }}
					</p>
					<NcButton
						v-if="loading"
						variant="tertiary"
						:disabled="cancelling"
						@click="cancelSummary">
						{{ t('spreed', 'Cancel') }}
					</NcButton>
					<NcButton
						v-else-if="chatSummaryMessage"
						variant="tertiary"
						@click="dismissSummary">
						{{ t('spreed', 'Dismiss') }}
					</NcButton>
					<NcButton
						v-if="isTextMoreThanOneLine"
						variant="tertiary"
						:title="collapsed ? t('spreed', 'Expand') : t('spreed', 'Collapse')"
						:aria-label="collapsed ? t('spreed', 'Expand') : t('spreed', 'Collapse')"
						@click="toggleCollapsed">
						<template #icon>
							<IconUnfoldMoreHorizontal v-if="collapsed" :size="20" />
							<IconUnfoldLessHorizontal v-else :size="20" />
						</template>
					</NcButton>
				</div>

				<p v-if="loading">
					{{ t('spreed', 'This might take a moment') }}
				</p>
				<p
					v-else
					ref="chatSummaryRef"
					class="chat-summary__message"
					:class="{ 'chat-summary__message--collapsed': collapsed }">
					{{ chatSummaryMessage }}
				</p>
			</div>
		</div>
	</NcAssistantContent>
</template>

<script setup lang="ts">
import type { ChatTask, TaskProcessingResponse } from '../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import NcAssistantContent from '@nextcloud/vue/components/NcAssistantContent'
import NcAssistantIcon from '@nextcloud/vue/components/NcAssistantIcon'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconUnfoldLessHorizontal from 'vue-material-design-icons/UnfoldLessHorizontal.vue'
import IconUnfoldMoreHorizontal from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { TASK_PROCESSING } from '../../constants.ts'
import { deleteTaskById, getTaskById } from '../../services/coreService.ts'
import { useChatExtrasStore } from '../../stores/chatExtras.ts'
import CancelableRequest from '../../utils/cancelableRequest.js'

type TaskProcessingCancelableRequest = {
	request: (taskId: number) => TaskProcessingResponse
	cancel: () => void
}

let getTaskInterval: NodeJS.Timeout | undefined
const cancelGetTask: Record<string, TaskProcessingCancelableRequest['cancel']> = {}

const chatSummaryRef = useTemplateRef<HTMLParagraphElement>('chatSummaryRef')
const collapsed = ref(true)
const isTextMoreThanOneLine = ref(false)

const loading = ref(true)
const cancelling = ref(false)

const chatExtrasStore = useChatExtrasStore()

const token = useGetToken()

const chatSummaryMessage = ref('')
const chatSummaryCaption = computed(() => {
	return loading.value
		? t('spreed', 'Generating summary of unread messages …')
		: t('spreed', 'Summary is AI generated and might contain mistakes')
})

watch(chatSummaryMessage, () => {
	nextTick(() => {
		setIsTextMoreThanOneLine()
	})
}, { immediate: true })

onBeforeUnmount(() => {
	Object.values(cancelGetTask).forEach((cancelFn) => cancelFn())
})

watch(token, (newValue, oldValue) => {
	// Cancel pending requests when leaving room
	if (oldValue && cancelGetTask[oldValue]) {
		cancelGetTask[oldValue]?.()
		clearInterval(getTaskInterval)
		getTaskInterval = undefined
	}
	if (newValue) {
		loading.value = true
		chatSummaryMessage.value = ''
		checkScheduledTasks(newValue)
	}
}, { immediate: true })

/**
 *
 * @param token conversation token
 */
function checkScheduledTasks(token: string) {
	const taskQueue: ChatTask[] = chatExtrasStore.getChatSummaryTaskQueue(token)

	if (!taskQueue.length) {
		return
	}

	for (const task of taskQueue) {
		if (task.summary) {
			// Task is already finished, checking next one
			continue
		}
		const { request, cancel } = CancelableRequest(getTaskById) as TaskProcessingCancelableRequest
		cancelGetTask[token] = cancel

		getTaskInterval = setInterval(() => {
			getTask(token, request, task)
		}, 5000)
		return
	}

	// There was no return, so checking all tasks are finished
	chatSummaryMessage.value = chatExtrasStore.getChatSummary(token)
	loading.value = false
}

/**
 *
 * @param token conversation token
 * @param request cancelable request to get task from API
 * @param task task object
 */
async function getTask(token: string, request: TaskProcessingCancelableRequest['request'], task: ChatTask) {
	try {
		const response = await request(task.taskId)
		const status = response.data.ocs.data.task.status
		switch (status) {
			case TASK_PROCESSING.STATUS.SUCCESSFUL: {
			// Task is completed, proceed to the next task
				const summary = response.data.ocs.data.task.output?.output as string || ''
				chatExtrasStore.storeChatSummary(token, task.fromMessageId, summary)
				clearInterval(getTaskInterval)
				getTaskInterval = undefined
				checkScheduledTasks(token)
				break
			}
			case TASK_PROCESSING.STATUS.FAILED:
			case TASK_PROCESSING.STATUS.UNKNOWN:
			case TASK_PROCESSING.STATUS.CANCELLED: {
			// Task is likely failed, proceed to the next task
				showError(t('spreed', 'Error occurred during a summary generation'))
				cancelSummary()
				break
			}
			case TASK_PROCESSING.STATUS.SCHEDULED:
			case TASK_PROCESSING.STATUS.RUNNING:
			default: {
			// Task is still processing, scheduling next request
				break
			}
		}
	} catch (error) {
		if (CancelableRequest.isCancel(error)) {
			return
		}
		console.error('Error getting chat summary:', error)
	}
}

/**
 *
 */
function dismissSummary() {
	Object.values(cancelGetTask).forEach((cancelFn) => cancelFn())
	clearInterval(getTaskInterval)
	getTaskInterval = undefined
	chatExtrasStore.dismissChatSummary(token.value)
}

/**
 *
 */
async function cancelSummary() {
	cancelling.value = true
	const taskQueue: ChatTask[] = chatExtrasStore.getChatSummaryTaskQueue(token.value)
	for await (const task of taskQueue) {
		await deleteTaskById(task.taskId)
	}
	cancelling.value = false
	dismissSummary()
}

/**
 *
 */
function toggleCollapsed() {
	collapsed.value = !collapsed.value
}

/**
 *
 */
function setIsTextMoreThanOneLine() {
	if (!chatSummaryRef.value) {
		return
	}
	isTextMoreThanOneLine.value = chatSummaryRef.value.scrollHeight > chatSummaryRef.value.clientHeight
	collapsed.value = !isTextMoreThanOneLine.value
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.chat-summary {
	margin-block: 0 calc(var(--default-grid-baseline) * 2);
	margin-inline: calc(var(--default-grid-baseline) * 4);

	&__container {
		display: flex;
		gap: var(--default-grid-baseline);
		align-items: flex-start;
	}

	&__icon {
		flex-shrink: 0;
		align-self: flex-start;
	}

	&__content {
		flex-grow: 1;
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
	}

	&__header {
		display: flex;
		gap: var(--default-grid-baseline);
		align-items: center;
	}

	&__caption {
		font-weight: bold;
		margin-inline-end: auto;
	}

	&__message {
		white-space: pre-line;
		word-wrap: break-word;
		max-height: 30vh;
		overflow: auto;

		&--collapsed {
			text-overflow: ellipsis;
			overflow: hidden;
			display: -webkit-box;
			-webkit-line-clamp: 1;
			-webkit-box-orient: vertical;
		}
	}
}
</style>
