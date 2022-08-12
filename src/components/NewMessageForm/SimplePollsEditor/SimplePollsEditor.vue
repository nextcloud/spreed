<!--
  - @copyright Copyright (c) 2022, Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Modal size="small" v-on="$listeners">
		<div class="simple-polls-editor">
			<h2>{{ t('spreed', 'Create new poll') }}</h2>

			<!-- Poll Question -->
			<p class="simple-polls-editor__caption">
				{{ t('spreed', 'Question') }}
			</p>
			<TextField :value.sync="pollQuestion" :placeholder="t('spreed', 'Ask a question ')" v-on="$listeners" />

			<!-- Poll options -->
			<p class="simple-polls-editor__caption">
				{{ t('spreed', 'Answers') }}
			</p>
			<PollOption v-for="option, index in pollOptions"
				:key="index"
				class="poll-option"
				:value.sync="pollOptions[index]"
				:placeholder="t('spreed', 'Answer {option}', {option: index + 1})"
				:can-delete="pollOptions.length > 2"
				@delete-option="deleteOption(index)" />

			<!-- Add options -->
			<Button class="simple-polls-editor__add-more" type="tertiary-no-background" @click="addOption">
				<Plus slot="icon" />
				{{ t('spreed', 'Add answer') }}
			</Button>

			<!-- Poll settings -->
			<p class="simple-polls-editor__caption">
				{{ t('spreed', 'Settings') }}
			</p>
			<div class="simple-polls-editor__settings">
				<CheckBoxRadioSwitch :checked.sync="isPrivate" type="checkbox">
					{{ t('spreed', 'Private poll') }}
				</CheckBoxRadioSwitch>
				<CheckBoxRadioSwitch :checked.sync="isMultipleAnswer" type="checkbox">
					{{ t('spreed', 'Multiple answers') }}
				</CheckBoxRadioSwitch>
				<div class="simple-polls-editor__actions">
					<Button type="tertiary" @click="dismissEditor">
						{{ t('spreed', 'Dismiss') }}
					</Button>
					<!-- create poll button-->
					<Button type="primary" @click="createPoll">
						{{ t('spreed', 'Create poll') }}
					</Button>
				</div>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CheckBoxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Button from '@nextcloud/vue/dist/Components/Button'
import PollOption from './PollOption.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import TextField from '@nextcloud/vue/dist/Components/TextField'
import pollService from '../../../services/pollService.js'

export default {
	name: 'SimplePollsEditor',

	components: {
		Modal,
		CheckBoxRadioSwitch,
		Button,
		PollOption,
		Plus,
		TextField,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			isPrivate: false,
			isMultipleAnswer: false,
			pollQuestion: '',
			pollOptions: ['', ''],
		}
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

.simple-polls-editor {
	padding: 20px;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: left;

	&__caption {
		padding: 16px 0 4px 0;
		font-weight: bold;
		color: var(--color-primary-element);
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: 4px;
	}
}

// Upstream
::v-deep .checkbox-radio-switch {
	&__label {
		align-items: unset;
		height: unset;
		margin: 4px 0;
		padding: 8px;
		width: 100%;
		border-radius: var(--border-radius-large);
		span {
			align-self: flex-start;
		}
	}
}

</style>
