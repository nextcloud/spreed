<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('spreed', 'Create new poll')"
		:close-on-click-outside="!isFilled"
		v-on="$listeners"
		@update:open="emit('close')">
		<!-- Draft imports -->
		<div v-if="supportPollDrafts && isModerator" class="poll-editor__wrapper">
			<NcSelect v-model="selectedDraft"
				class="poll-editor__select"
				name="poll_drafts_select"
				label="question"
				:options="pollDrafts"
				:input-label="t('spreed', 'Choose poll from drafts:')"
				:placeholder="t('spreed', 'Select a draft')"
				:loading="!pollsStore.drafts[props.token]" />
		</div>

		<!-- Poll Question -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Question') }}
		</p>
		<NcTextField :value.sync="pollForm.question" :label="t('spreed', 'Ask a question')" v-on="$listeners" />

		<!-- Poll options -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Answers') }}
		</p>
		<div v-for="(option, index) in pollForm.options"
			:key="index"
			class="poll-editor__option">
			<NcTextField ref="pollOption"
				:value.sync="pollForm.options[index]"
				:label="t('spreed', 'Answer {option}', {option: index + 1})" />
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
			<NcCheckboxRadioSwitch :checked.sync="isPrivate" type="checkbox">
				{{ t('spreed', 'Private poll') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch :checked.sync="isMultipleAnswer" type="checkbox">
				{{ t('spreed', 'Multiple answers') }}
			</NcCheckboxRadioSwitch>
		</div>
		<template #actions>
			<NcButton type="tertiary" @click="emit('close')">
				{{ t('spreed', 'Dismiss') }}
			</NcButton>
			<NcButton type="secondary" :disabled="!isFilled" @click="createPollDraft">
				{{ t('spreed', 'Save as draft') }}
			</NcButton>
			<NcButton type="primary" :disabled="!isFilled" @click="createPoll">
				{{ t('spreed', 'Create poll') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'

import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { useStore } from '../../composables/useStore.js'
import { POLL } from '../../constants.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { usePollsStore } from '../../stores/polls.ts'
import type { createPollParams } from '../../types/index.ts'

const props = defineProps<{
	token: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const supportPollDrafts = hasTalkFeature(props.token, 'talk-polls-drafts')

const store = useStore()
const pollsStore = usePollsStore()

const pollOption = ref(null)

const POLL_FORM: createPollParams = {
	question: '',
	options: ['', ''],
	resultMode: POLL.MODE.PUBLIC,
	maxVotes: POLL.ANSWER_TYPE.SINGLE,
}
const pollForm = reactive({ ...POLL_FORM })

const isFilled = computed(() => !!pollForm.question || pollForm.options.some(option => option))

const isPrivate = computed({
	get() {
		return pollForm.resultMode === POLL.MODE.HIDDEN
	},
	set(value) {
		pollForm.resultMode = value ? POLL.MODE.HIDDEN : POLL.MODE.PUBLIC
	}
})

const isMultipleAnswer = computed({
	get() {
		return pollForm.maxVotes === POLL.ANSWER_TYPE.MULTIPLE
	},
	set(value) {
		pollForm.maxVotes = value ? POLL.ANSWER_TYPE.MULTIPLE : POLL.ANSWER_TYPE.SINGLE
	}
})

/**
 * Receive poll drafts for the current conversation as owner/moderator
 */
const isModerator = computed(() => (store.getters as unknown).isModerator)
if (supportPollDrafts && isModerator.value) {
	pollsStore.getPollDrafts(props.token)
}
const pollDrafts = computed(() => supportPollDrafts ? pollsStore.getDrafts(props.token) : [])
const selectedDraft = ref(null)
watch(selectedDraft, (value) => fillPollForm(value !== null ? value : { ...POLL_FORM }))

/**
 * Remove a previously added option
 * @param index option index
 */
function deleteOption(index) {
	pollForm.options.splice(index, 1)
}

/**
 * Add an empty option to the form
 */
function addOption() {
	pollForm.options.push('')
	nextTick(() => {
		pollOption.value.at(-1).focus()
	})
}

/**
 * Post a poll into conversation
 */
async function createPoll() {
	const poll = await pollsStore.createPoll({
		token: props.token,
		form: pollForm,
	})
	if (poll) {
		emit('close')
	}
}

/**
 * Insert data into form fields
 * @param payload data to fill with
 */
function fillPollForm(payload: createPollParams) {
	for (const key of Object.keys(pollForm)) {
		pollForm[key] = payload[key]
	}
}

/**
 * Saves a poll draft for this conversation
 */
async function createPollDraft() {
	const poll = await pollsStore.createPollDraft({
		token: props.token,
		form: pollForm,
	})
	if (poll) {
		showSuccess(t('spreed', 'Poll draft has been saved'))
	}
}
</script>

<style lang="scss" scoped>
.poll-editor {
	&__caption {
		margin: calc(var(--default-grid-baseline) * 2) 0 var(--default-grid-baseline);
		font-weight: bold;
		color: var(--color-primary-element);
	}

	&__wrapper {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);
	}

	&__select {
		width: 100%;
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
}
</style>
