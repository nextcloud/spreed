<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('spreed', 'Create new poll')"
		:close-on-click-outside="!isFilled"
		:container="container"
		v-on="$listeners"
		@update:open="dismissEditor">
		<!-- Poll Question -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Question') }}
		</p>
		<NcTextField :value.sync="pollQuestion" :label="t('spreed', 'Ask a question')" v-on="$listeners" />

		<!-- Poll options -->
		<p class="poll-editor__caption">
			{{ t('spreed', 'Answers') }}
		</p>
		<div v-for="(option, index) in pollOptions"
			:key="index"
			class="poll-editor__option">
			<NcTextField ref="pollOption"
				:value.sync="pollOptions[index]"
				:label="t('spreed', 'Answer {option}', {option: index + 1})" />
			<NcButton v-if="pollOptions.length > 2"
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
			<!-- create poll button-->
			<NcButton type="primary" @click="createPoll">
				{{ t('spreed', 'Create poll') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import pollService from '../../services/pollService.js'
import { usePollsStore } from '../../stores/polls.js'

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
		return {
			pollsStore: usePollsStore(),
		}
	},

	data() {
		return {
			isPrivate: false,
			isMultipleAnswer: false,
			pollQuestion: '',
			pollOptions: ['', ''],
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		isFilled() {
			return !!this.pollQuestion || this.pollOptions.some(option => option)
		},
	},

	methods: {
		t,
		// Remove a previously added option
		deleteOption(index) {
			this.pollOptions.splice(index, 1)
		},

		dismissEditor() {
			this.$emit('close')
		},

		addOption() {
			this.pollOptions.push('')
			this.$nextTick(() => {
				this.$refs.pollOption.at(-1).focus()
			})
		},

		async createPoll() {
			try {
				const response = await pollService.postNewPoll(
					this.token,
					this.pollQuestion,
					this.pollOptions,
					this.isPrivate ? 1 : 0,
					this.isMultipleAnswer ? 0 : 1)
				// Add the poll immediately to the store
				this.pollsStore.addPoll({
					token: this.token,
					poll: response.data.ocs.data,
				})
				this.dismissEditor()
			} catch (error) {
				console.debug(error)
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
