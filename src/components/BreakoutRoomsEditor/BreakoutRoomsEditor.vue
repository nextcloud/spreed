\<!--
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
		v-on="$listeners">
		<div class="breakout-rooms-editor">
			<h2>{{ modalTitle }}</h2>
			<template v-if="!isEditingParticipants">
				<div class="breakout-rooms-editor__main">
					<label for="room-number">{{ t('spreed', 'Number of breakout rooms') }} </label>
					<input id="room-number" v-model.number="amount" type="number">
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
					@back="isEditingParticipants = false"
					@create-rooms="handleCreateRooms" />
			</template>
		</div>
	</ncmodal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import BreakoutRoomsParticipantsEditor from './BreakoutRoomsParticipantsEditor.vue'

export default {
	name: 'BreakoutRoomsEditor',

	components: {
		NcModal,
		NcCheckboxRadioSwitch,
		NcButton,
		BreakoutRoomsParticipantsEditor,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			mode: '1',
			amount: 1,
			attendeeMap: '',
			isEditingParticipants: false,
		}
	},

	computed: {
		modalTitle() {
			return this.isEditingParticipants
				? t('spreed', 'Assign participants to rooms')
				: t('spreed', 'Configure breakout rooms')
		},
	},

	methods: {
		handleCreateRooms(payload) {
			console.debug(payload)
			this.$store.dispatch('configureBreakoutRoomsAction', {
				token: this.token,
				mode: this.mode,
				amount: this.amount,
				attendeeMap: this.attendeeMap,
			})
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
	height: calc(100% - 40px);
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

::v-deep .modal-container {
	overflow: hidden !important;
	height: 100%;
}
</style>
