<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
-->

<template>
	<NcModal v-bind="$attrs"
		:container="container"
		:class="{'modal-mask__participants-step': isEditingParticipants}"
		v-on="$listeners">
		<div class="breakout-rooms-editor"
			:class="{'breakout-rooms-editor__participants-step': isEditingParticipants}">
			<h2>{{ modalTitle }}</h2>
			<template v-if="!isEditingParticipants">
				<div class="breakout-rooms-editor__main">
					<label class="breakout-rooms-editor__caption" for="room-number">{{ t('spreed', 'Number of breakout rooms') }} </label>
					<NcTextField id="room-number"
						:value="amount.toString()"
						class="breakout-rooms-editor__number-input"
						type="number"
						min="1"
						max="20"
						@update:value="setAmount" />

					<label class="breakout-rooms-editor__caption">{{ t('spreed', 'Assignment method') }}</label>
					<fieldset>
						<NcCheckboxRadioSwitch :checked.sync="mode"
							value="1"
							name="mode_radio"
							type="radio">
							{{ t('spreed', 'Automatically assign participants') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch :checked.sync="mode"
							value="2"
							name="mode_radio"
							type="radio">
							{{ t('spreed', 'Manually assign participants') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch :checked.sync="mode"
							value="3"
							name="mode_radio"
							type="radio">
							{{ t('spreed', 'Allow participants to choose') }}
						</NcCheckboxRadioSwitch>
					</fieldset>
				</div>
				<div class="breakout-rooms-editor__buttons">
					<NcButton v-if="mode === '2'" type="primary" @click="isEditingParticipants = true">
						{{ t('spreed', 'Assign participants to rooms') }}
					</NcButton>
					<NcButton v-else type="primary" @click="handleCreateRooms">
						{{ t('spreed', 'Create rooms') }}
					</NcButton>
				</div>
			</template>
			<template v-else>
				<BreakoutRoomsParticipantsEditor :token="token"
					:room-number="amount"
					v-on="$listeners"
					@back="isEditingParticipants = false"
					@create-rooms="handleCreateRooms" />
			</template>
		</div>
	</ncmodal>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import BreakoutRoomsParticipantsEditor from './BreakoutRoomsParticipantsEditor.vue'

export default {
	name: 'BreakoutRoomsEditor',

	components: {
		BreakoutRoomsParticipantsEditor,
		NcButton,
		NcCheckboxRadioSwitch,
		NcTextField,
		NcModal,
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
			mode: '1',
			amount: 2,
			attendeeMap: '',
			isEditingParticipants: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		modalTitle() {
			return this.isEditingParticipants
				? t('spreed', 'Assign participants to rooms')
				: t('spreed', 'Configure breakout rooms')
		},
	},

	methods: {
		async handleCreateRooms() {
			try {
				await this.$store.dispatch('configureBreakoutRoomsAction', {
					token: this.token,
					mode: this.mode,
					amount: this.amount,
				})
				this.$emit('close')
			} catch (error) {
				console.debug(error)
			}
		},

		// FIXME upstream: support of value type as Number should be added to NcInputField,
		// now it breaks validation. Another option: Create NcNumberField component
		setAmount(value) {
			this.amount = parseFloat(value)
		},
	},
}
</script>

<style lang="scss" scoped>
.breakout-rooms-editor {
	display: flex;
	flex-direction: column;
	padding: 20px;
	justify-content: flex-start;
	align-items: flex-start;

	&__number-input{
		display: block;
		margin-bottom: calc(var(--default-grid-baseline)*4);
	}

	&__caption {
		font-weight: bold;
		display: block;
		margin: calc(var(--default-grid-baseline)*3) 0 calc(var(--default-grid-baseline)*2) 0;
	}

	&__participants-step {
		height: 100%;

	}

	&__main {
		height: 100%;
	}

	&__buttons {
		display: flex;
		justify-content: flex-end;
		gap: calc(var(--default-grid-baseline) * 2);
		width: 100%;
	}
}

.modal-mask__participants-step {
	:deep(.modal-container) {
		overflow: hidden !important;
		height: 100% !important;
	}
}
</style>
