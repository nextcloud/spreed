<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('spreed', 'Create new poll')"
		:close-on-click-outside="!isFilled"
		v-on="$listeners"
		@update:open="dismissEditor">
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
			<NcButton type="tertiary" @click="dismissEditor">
				{{ t('spreed', 'Dismiss') }}
			</NcButton>
			<NcButton type="primary" :disabled="!isFilled" @click="createPoll">
				{{ t('spreed', 'Create poll') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { computed, reactive } from 'vue'

import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { POLL } from '../../constants.js'
import { usePollsStore } from '../../stores/polls.ts'

export default {
	name: 'NewMessagePollEditor',

	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		NcDialog,
		NcTextField,
		// Icons
		Close,
		Plus,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	emits: ['close'],

	setup() {
		const pollForm = reactive({
			question: '',
			options: ['', ''],
			resultMode: POLL.MODE.PUBLIC,
			maxVotes: POLL.ANSWER_TYPE.SINGLE,
		})
		const isFilled = computed(() => !!pollForm.question || pollForm.options.some(option => option))

		return {
			pollsStore: usePollsStore(),
			pollForm,
			isFilled,
		}
	},

	computed: {
		isPrivate: {
			get() {
				return this.pollForm.resultMode === POLL.MODE.HIDDEN
			},
			set(value) {
				this.pollForm.resultMode = value ? POLL.MODE.HIDDEN : POLL.MODE.PUBLIC
			}
		},
		isMultipleAnswer: {
			get() {
				return this.pollForm.maxVotes === POLL.ANSWER_TYPE.MULTIPLE
			},
			set(value) {
				this.pollForm.maxVotes = value ? POLL.ANSWER_TYPE.MULTIPLE : POLL.ANSWER_TYPE.SINGLE
			}
		},
	},

	methods: {
		t,

		deleteOption(index) {
			this.pollForm.options.splice(index, 1)
		},

		dismissEditor() {
			this.$emit('close')
		},

		addOption() {
			this.pollForm.options.push('')
			this.$nextTick(() => {
				this.$refs.pollOption.at(-1).focus()
			})
		},

		async createPoll() {
			const poll = await this.pollsStore.createPoll({
				token: this.token,
				form: this.pollForm,
			})
			if (poll) {
				this.dismissEditor()
			}
		},

	},
}
</script>

<style lang="scss" scoped>

.poll-editor {
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
}
</style>
