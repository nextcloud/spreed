<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal size="small"
		:close-on-click-outside="!isFilled"
		:container="container"
		v-on="$listeners">
		<div class="poll-editor">
			<h2>{{ t('spreed', 'Create new poll') }}</h2>

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
					type="tertiary-no-background"
					:aria-label="t('spreed', 'Delete poll option')"
					@click="deleteOption(index)">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
			</div>

			<!-- Add options -->
			<NcButton class="poll-editor__add-more" type="tertiary-no-background" @click="addOption">
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
			<div class="poll-editor__actions">
				<NcButton type="tertiary" @click="dismissEditor">
					{{ t('spreed', 'Dismiss') }}
				</NcButton>
				<!-- create poll button-->
				<NcButton type="primary" @click="createPoll">
					{{ t('spreed', 'Create poll') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import Close from 'vue-material-design-icons/Close.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import pollService from '../../services/pollService.js'

export default {
	name: 'NewMessagePollEditor',

	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		NcModal,
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
				this.$store.dispatch('addPoll', {
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
	padding: 20px;
	display: flex;
	flex-direction: column;
	justify-content: center;

	&__caption {
		padding: 16px 0 4px 0;
		font-weight: bold;
		color: var(--color-primary-element);
	}

	&__option {
		display: flex;
		align-items: center;
		width: 100%;
		height: 44px;
		margin-bottom: 12px;
	}

	&__settings {
		display: flex;
		flex-direction: column;
		gap: 4px;
		margin-bottom: 8px;
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: 4px;
	}
}
</style>
