<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- eslint-disable vue/multiline-html-element-content-newline -->
<template>
	<NcNoteCard type="info" class="chat-summary">
		<template #icon>
			<NcLoadingIcon v-if="loading" />
			<IconCreation v-else />
		</template>
		<NcButton v-if="isTextMoreThanOneLine"
			class="chat-summary__button"
			variant="tertiary"
			:title="!collapsed ? t('spreed', 'Collapse') : t('spreed', 'Expand')"
			:aria-label="!collapsed ? t('spreed', 'Collapse') : t('spreed', 'Expand')"
			@click="toggleCollapsed">
			<template #icon>
				<IconChevronUp class="icon" :class="{ 'icon--reverted': !collapsed }" :size="20" />
			</template>
		</NcButton>
		<template v-if="loading">
			<p class="chat-summary__caption">
				{{ t('spreed', 'Generating summary of unread messages …') }}
			</p>
			<p>{{ t('spreed', 'This might take a moment') }}</p>
		</template>
		<template v-else>
			<p class="chat-summary__caption">
				{{ t('spreed', 'Summary is AI generated and might contain mistakes') }}
			</p>
			<p ref="chatSummaryRef"
				class="chat-summary__message"
				:class="{ 'chat-summary__message--collapsed': collapsed }">{{ chatSummaryMessage }}</p>
		</template>
		<div class="chat-summary__actions">
			<NcButton v-if="loading"
				class="chat-summary__action"
				variant="primary"
				:disabled="cancelling"
				@click="cancelSummary">
				<template v-if="cancelling" #icon>
					<NcLoadingIcon />
				</template>
				{{ t('spreed', 'Cancel') }}
			</NcButton>
			<NcButton v-else-if="chatSummaryMessage"
				class="chat-summary__action"
				variant="primary"
				@click="dismissSummary">
				{{ t('spreed', 'Dismiss') }}
			</NcButton>
		</div>
	</NcNoteCard>
</template>

<script setup lang="ts">
import type { ChatTask, TaskProcessingResponse } from '../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import IconCreation from 'vue-material-design-icons/Creation.vue' // Filled as in Assistant app icon
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

const chatSummaryRef = ref(null)
const collapsed = ref(true)
const isTextMoreThanOneLine = ref(false)

const loading = ref(true)
const cancelling = ref(false)

const store = useStore()
const chatExtrasStore = useChatExtrasStore()

const token = useGetToken()

const chatSummaryMessage = ref('')

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
	// @ts-expect-error: template ref typing
	isTextMoreThanOneLine.value = chatSummaryRef.value?.scrollHeight > chatSummaryRef.value?.clientHeight
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.chat-summary {
	// Override NcNoteCard styles
	margin: 0 calc(var(--default-grid-baseline) * 4) calc(var(--default-grid-baseline) * 2) !important;
	padding: calc(var(--default-grid-baseline) * 2) !important;
	& > :deep(div) {
		width: 100%;
	}

	&__caption {
		font-weight: bold;
		margin: var(--default-grid-baseline) var(--default-clickable-area);
		margin-inline-start: 0;
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

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: var(--default-grid-baseline);
		z-index: 1;
	}

	&__button {
		position: absolute !important;
		top: var(--default-grid-baseline);
		inset-inline-end: calc(5 * var(--default-grid-baseline));
		z-index: 1;

		& .icon {
			transition: $transition;

			&--reverted {
				transform: rotate(180deg);
			}
		}
	}
}
</style>
