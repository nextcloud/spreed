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
			<template v-if="!isEditingParticipants">
				<h2>{{ t('spreed', 'Create rooms') }}</h2>
				<NcInputField :label="t('spreed', 'Number of breakout rooms')" type="number" :value.sync="amount" />
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
			</template>
			<template v-else>
				<BreakoutRoomsParticipantsEditor :token="token" />
			</template>
			<div class="breakout-rooms-editor__buttons">
				<NcButton @click="handleCreateRooms">
					{{ t('spreed', 'Create rooms') }}
				</NcButton>
			</div>
		</div>
	</ncmodal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import BreakoutRoomsParticipantsEditor from './BreakoutRoomsParticipantsEditor.vue'

export default {
	name: 'BreakoutRoomsEditor',

	components: {
		NcModal,
		NcInputField,
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
			mode: 'auto',
			amount: 1,
			attendeeMap: '',
			isEditingParticipants: true,
		}
	},

	methods: {
		handleCreateRooms() {
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

<style scoped>
.breakout-rooms-editor {
	display: flex;
	flex-direction: column;
	padding: 20px;
	justify-content: flex-start;
	align-items: flex-start;
}
</style>
