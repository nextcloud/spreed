<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="dialogName"
		:close-on-click-outside="!isFilled"
		:container="container"
		v-on="$listeners"
		@update:open="emit('close')">
		<NcButton v-if="supportPollDrafts && isOpenedFromDraft"
			class="poll-editor__back-button"
			type="tertiary"
			:title="t('spreed', 'Back')"
			:aria-label="t('spreed', 'Back')"
			@click="goBack">
			<template #icon>
				<IconArrowLeft class="bidirectional-icon" :size="20" />
			</template>
		</NcButton>
		<!-- Poll Question -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Question') }}
		</p>
		<div class="poll-editor__wrapper">
			<NcTextField v-model="pollForm.question" :label="t('spreed', 'Ask a question')" v-on="$listeners" />
			<!--native file picker, hidden -->
			<input id="poll-upload"
				ref="pollImport"
				type="file"
				class="hidden-visually"
				@change="importPoll">
			<NcActions v-if="supportPollDrafts" force-menu>
				<NcActionButton v-if="props.canCreatePollDrafts && !isOpenedFromDraft" close-after-click @click="openPollDraftHandler">
					<template #icon>
						<IconFileEdit :size="20" />
					</template>
					{{ t('spreed', 'Browse poll drafts') }}
				</NcActionButton>
				<NcActionButton close-after-click @click="triggerImport">
					<template #icon>
						<IconFileUpload :size="20" />
					</template>
					{{ t('spreed', 'Import draft from file') }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Poll options -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Answers') }}
		</p>
		<div v-for="(option, index) in pollForm.options"
			:key="index"
			class="poll-editor__option">
			<NcTextField ref="pollOption"
				v-model="pollForm.options[index]"
				:label="t('spreed', 'Answer {option}', { option: index + 1 })" />
			<NcButton v-if="pollForm.options.length > 2"
				type="tertiary"
				:aria-label="t('spreed', 'Delete poll option')"
				@click="deleteOption(index)">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
		</div>

		<!-- Add options -->
		<NcButton class="poll-editor__add-more" type="tertiary" @click="addOption">
			<template #icon>
				<Plus />
			</template>
			{{ t('spreed', 'Add answer') }}
		</NcButton>

		<!-- Poll settings -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Settings') }}
		</p>
		<div class="poll-editor__settings">
			<NcCheckboxRadioSwitch v-model="isAnonymous" type="checkbox">
				{{ t('spreed', 'Anonymous poll') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch v-model="isMultipleAnswer" type="checkbox">
				{{ t('spreed', 'Multiple answers') }}
			</NcCheckboxRadioSwitch>
		</div>
		<template #actions>
			<NcActions v-if="supportPollDrafts" force-menu>
				<NcActionButton v-if="props.canCreatePollDrafts && !editingDraftId" :disabled="!isFilled" @click="createPollDraft">
					<template #icon>
						<IconFileEdit :size="20" />
					</template>
					{{ t('spreed', 'Save as draft') }}
				</NcActionButton>
				<NcActionLink v-if="isFilled" :href="exportPollURI" :download="exportPollFileName">
					<template #icon>
						<IconFileDownload :size="20" />
					</template>
					{{ t('spreed', 'Export draft to file') }}
				</NcActionLink>
			</NcActions>
			<NcButton type="primary" :disabled="!isFilled" @click="handleSubmit">
				{{ createPollLabel }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref } from 'vue'

import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Close from 'vue-material-design-icons/Close.vue'
import IconFileDownload from 'vue-material-design-icons/FileDownload.vue'
import IconFileEdit from 'vue-material-design-icons/FileEdit.vue'
import IconFileUpload from 'vue-material-design-icons/FileUpload.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { useStore } from '../../composables/useStore.js'
import { POLL } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { usePollsStore } from '../../stores/polls.ts'
import type { createPollParams, requiredPollParams } from '../../types/index.ts'
import { convertToJSONDataURI } from '../../utils/fileDownload.ts'
import { validatePollForm } from '../../utils/validatePollForm.ts'

const props = defineProps<{
	token: string
	canCreatePollDrafts: boolean
	container?: string
}>()
const emit = defineEmits<{
	(event: 'close'): void
}>()
defineExpose({
	fillPollEditorFromDraft,
})

const supportPollDrafts = hasTalkFeature(props.token, 'talk-polls-drafts')

const store = useStore()
const pollsStore = usePollsStore()

const isOpenedFromDraft = ref(false)
const editingDraftId = ref<number | null>(null)
const pollOption = ref<InstanceType<typeof NcTextField>[] | null>(null)
const pollImport = ref<HTMLInputElement | null>(null)

const pollForm = reactive<createPollParams>({
	question: '',
	options: ['', ''],
	resultMode: POLL.MODE.PUBLIC,
	maxVotes: POLL.ANSWER_TYPE.SINGLE,
})

const isFilled = computed(() => Boolean(pollForm.question) && pollForm.options.filter((option) => Boolean(option)).length >= 2)
const dialogName = computed(() => {
	return editingDraftId.value ? t('spreed', 'Edit poll draft') : t('spreed', 'Create new poll')
})
const createPollLabel = computed(() => {
	if (editingDraftId.value) {
		return t('spreed', 'Save')
	}

	return store.getters.getToken() !== props.token
		? t('spreed', 'Create poll in {name}', { name: store.getters.conversation(props.token).displayName }, undefined, { escape: false, sanitize: false })
		: t('spreed', 'Create poll')
})

const isAnonymous = computed({
	get() {
		return pollForm.resultMode === POLL.MODE.HIDDEN
	},
	set(value) {
		pollForm.resultMode = value ? POLL.MODE.HIDDEN : POLL.MODE.PUBLIC
	},
})

const isMultipleAnswer = computed({
	get() {
		return pollForm.maxVotes === POLL.ANSWER_TYPE.MULTIPLE
	},
	set(value) {
		pollForm.maxVotes = value ? POLL.ANSWER_TYPE.MULTIPLE : POLL.ANSWER_TYPE.SINGLE
	},
})

const exportPollURI = computed(() => convertToJSONDataURI(pollForm))
const exportPollFileName = `Talk Poll ${new Date().toISOString().slice(0, 10)}`

/**
 * Remove a previously added option
 * @param index option index
 */
function deleteOption(index: number) {
	pollForm.options.splice(index, 1)
}

/**
 * Add an empty option to the form
 */
function addOption() {
	pollForm.options.push('')
	nextTick(() => {
		pollOption.value!.at(-1).focus()
	})
}

/**
 * Post a poll into conversation
 */
async function handleSubmit() {
	if (editingDraftId.value) {
		const pollDraft = await pollsStore.updatePollDraft({
			token: props.token,
			pollId: editingDraftId.value,
			form: pollForm,
		})
		if (pollDraft) {
			openPollDraftHandler()
			nextTick(() => {
				emit('close')
			})
		}
		return
	}

	const poll = await pollsStore.createPoll({
		token: props.token,
		form: pollForm,
	})
	if (poll) {
		emit('close')
	}
}

/**
 * Pre-fills form from the draft
 * @param id poll draft ID
 * @param fromDrafts whether editor was opened from drafts handler
 * @param action required action ('fill' from draft or 'edit' draft)
 */
function fillPollEditorFromDraft(id: number | null, fromDrafts: boolean, action?: string) {
	if (fromDrafts) {
		// Show 'Back' button, do not reset until closed
		isOpenedFromDraft.value = true
	}
	if (action === 'edit') {
		// Show Edit interface
		editingDraftId.value = id
	}

	if (id === null) {
		return
	}

	const draft = pollsStore.drafts[props.token][id]
	if (draft) {
		fillPollForm({
			question: draft.question,
			options: [...draft.options],
			resultMode: draft.resultMode,
			maxVotes: draft.maxVotes,
		})
	}
}

/**
 * Call native input[type='file'] to import a file
 */
function triggerImport() {
	pollImport.value!.click()
}

/**
 * Validate imported file and insert data into form fields
 * @param event import event
 */
function importPoll(event: Event) {
	const file = (event.target as HTMLInputElement).files?.[0]
	if (!file) {
		return
	}

	const reader = new FileReader()
	reader.onload = (e: ProgressEvent) => {
		try {
			const parsedObject = validatePollForm(JSON.parse((e.target as FileReader).result as string))
			fillPollForm(parsedObject)
		} catch (error) {
			showError(t('spreed', 'Error while importing poll'))
			console.error('Error while importing poll:', error)
		}
	}

	reader.readAsText(file)
}

/**
 * Insert data into form fields
 * @param payload data to fill with
 */
function fillPollForm(payload: requiredPollParams) {
	Object.assign(pollForm, payload)
}

/**
 * Saves a poll draft for this conversation
 */
async function createPollDraft() {
	await pollsStore.createPollDraft({
		token: props.token,
		form: pollForm,
	})
}

/**
 * Open a PollDraftHandler dialog
 */
function openPollDraftHandler() {
	EventBus.emit('poll-drafts-open', { selector: props.container })
}

/**
 * Open a PollDraftHandler dialog as Back action
 */
function goBack() {
	openPollDraftHandler()
	if (isOpenedFromDraft.value) {
		nextTick(() => {
			emit('close')
		})
	}
}
</script>

<style lang="scss" scoped>
.poll-editor {
	&__wrapper {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);
	}

	&__caption {
		margin: calc(var(--default-grid-baseline) * 2) 0 var(--default-grid-baseline);
		font-weight: bold;
		color: var(--color-primary-element);
	}

	&__option {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);
		width: 100%;
		margin-bottom: calc(var(--default-grid-baseline) * 2);
	}

	&__settings {
		display: flex;
		flex-direction: column;
		gap: 4px;
		margin-bottom: 8px;
	}

	&__back-button {
		position: absolute !important;
		top: var(--default-grid-baseline);
		inset-inline-start: var(--default-grid-baseline);
		z-index: 1;
	}
}
</style>
