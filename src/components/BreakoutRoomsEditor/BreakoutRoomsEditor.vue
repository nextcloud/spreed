<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal :class="{'modal-mask__participants-step': isEditingParticipants}"
		:label-id="dialogHeaderId"
		v-on="$listeners">
		<div class="breakout-rooms-editor"
			:class="{'breakout-rooms-editor__participants-step': isEditingParticipants}">
			<h2 :id="dialogHeaderId" class="nc-dialog-alike-header">
				{{ modalTitle }}
			</h2>
			<template v-if="!isEditingParticipants">
				<div class="breakout-rooms-editor__main">
					<label class="breakout-rooms-editor__caption" for="room-number">{{ t('spreed', 'Number of breakout rooms') }} </label>
					<p v-if="isInvalidAmount" class="breakout-rooms-editor__error-hint">
						{{ t('spreed', 'You can create from 1 to 20 breakout rooms.') }}
					</p>
					<NcInputField id="room-number"
						ref="inputField"
						v-model="amount"
						class="breakout-rooms-editor__number-input"
						type="number"
						min="1"
						max="20" />

					<label class="breakout-rooms-editor__caption">{{ t('spreed', 'Assignment method') }}</label>
					<fieldset>
						<NcCheckboxRadioSwitch v-model="mode"
							value="1"
							name="mode_radio"
							type="radio">
							{{ t('spreed', 'Automatically assign participants') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="mode"
							value="2"
							name="mode_radio"
							type="radio">
							{{ t('spreed', 'Manually assign participants') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch v-model="mode"
							value="3"
							name="mode_radio"
							type="radio">
							{{ t('spreed', 'Allow participants to choose') }}
						</NcCheckboxRadioSwitch>
					</fieldset>
				</div>
				<div class="breakout-rooms-editor__buttons">
					<NcButton v-if="mode === '2'"
						type="primary"
						:disabled="isInvalidAmount"
						@click="isEditingParticipants = true">
						{{ t('spreed', 'Assign participants to rooms') }}
					</NcButton>
					<NcButton v-else
						type="primary"
						:disabled="isInvalidAmount"
						@click="handleCreateRooms">
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
	</NcModal>
</template>

<script>
import { ref } from 'vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import BreakoutRoomsParticipantsEditor from './BreakoutRoomsParticipantsEditor.vue'

import { useId } from '../../composables/useId.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'

export default {
	name: 'BreakoutRoomsEditor',

	components: {
		BreakoutRoomsParticipantsEditor,
		NcButton,
		NcCheckboxRadioSwitch,
		NcInputField,
		NcModal,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	emits: ['close'],

	setup() {
		const mode = ref('1')
		const amount = ref(2)
		const attendeeMap = ref('')
		const isEditingParticipants = ref(false)
		const isInvalidAmount = ref(false)
		const dialogHeaderId = `breakout-rooms-header-${useId()}`

		return {
			breakoutRoomsStore: useBreakoutRoomsStore(),
			mode,
			amount,
			attendeeMap,
			isEditingParticipants,
			isInvalidAmount,
			dialogHeaderId,
		}
	},

	computed: {
		modalTitle() {
			return this.isEditingParticipants
				? t('spreed', 'Assign participants to rooms')
				: t('spreed', 'Configure breakout rooms')
		},
	},

	watch: {
		amount(value) {
			this.isInvalidAmount = isNaN(value) || !this.$refs.inputField.$refs.input?.checkValidity()
		},
	},

	methods: {
		t,
		async handleCreateRooms() {
			try {
				await this.breakoutRoomsStore.configureBreakoutRooms({
					token: this.token,
					mode: this.mode,
					amount: this.amount,
				})
				this.$emit('close')
			} catch (error) {
				console.debug(error)
			}
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

	&__number-input {
		display: block;
		margin-bottom: calc(var(--default-grid-baseline)*4);
	}

	&__caption {
		font-weight: bold;
		display: block;
		margin: calc(var(--default-grid-baseline)*3) 0 calc(var(--default-grid-baseline)*2) 0;
	}

	&__error-hint {
		color: var(--color-error);
		font-size: 0.8rem;
	}

	&__participants-step {
		height: 100%;
	}

	&__main {
		height: 100%;
		align-self: flex-start;
	}

	&__buttons {
		display: flex;
		justify-content: flex-end;
		gap: calc(var(--default-grid-baseline) * 2);
		width: 100%;
	}
}
</style>
