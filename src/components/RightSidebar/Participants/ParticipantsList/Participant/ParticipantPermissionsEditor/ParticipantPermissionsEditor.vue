<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@pm.me>
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
-->

<template>
	<Modal v-on="$listeners">
		<div class="wrapper">
			<p class="title">
				{{ t('spreed', 'In this conversation, ') }}<strong>{{ displayName }}</strong>{{ t('spreed', ' can:') }}
			</p>
			<form @submit.prevent="handleSubmitPermissions">
				<CheckboxRadioSwitch :checked.sync="callStart"
					class="checkbox">
					{{ t('spreed', 'Start a call') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="lobbyIgnore"
					class="checkbox">
					{{ t('spreed', 'Skip the lobby') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="publishAudio"
					class="checkbox">
					{{ t('spreed', 'Enable the microphone') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="publishVideo"
					class="checkbox">
					{{ t('spreed', 'Enable the camera') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch :checked.sync="publishScreen"
					class="checkbox">
					{{ t('spreed', 'Share the screen') }}
				</CheckboxRadioSwitch>
				<button type="submit" :disabled="submitButtonDisabled" class="nc-button primary">
					{{ t('spreed', 'Update permissions') }}
				</button>
			</form>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import { PARTICIPANT } from '../../../../../../constants'
import { showError, showSuccess } from '@nextcloud/dialogs'

const PERMISSIONS = PARTICIPANT.PERMISSIONS

export default {
	name: 'ParticipantPermissionsEditor',

	components: {
		Modal,
		CheckboxRadioSwitch,
	},

	props: {
		/**
		 * The participant's name.
		 */
		participant: {
			type: Object,
			required: true,
		},

		/**
		 * The conversation's token.
		 */
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			// Permission to start a call
			callStart: false,
			// Permission to bypass the lobby
			lobbyIgnore: false,
			// Permission to enable the microphone
			publishAudio: false,
			// Permission to enable the camera
			publishVideo: false,
			// Permission to start a screenshare
			publishScreen: false,

		}
	},

	computed: {
		/**
		 * The participant's name.
		 */
		displayName() {
			return this.participant.displayName
		},

		/**
		 * The participant's attendeeId: a unique number that identifies the
		 * participant.
		 */
		attendeeId() {
			return this.participant.attendeeId
		},

		/**
		 * Permisssions of the current participant, from the participants
		 * store.
		 */
		currentPermissions() {
			return this.participant.permissions
		},

		/**
		 * The number of the edited permissions during the editing of the form.
		 * We use this to compare it with the actual permissions of the
		 * participant in the store and enable or disable the submit button
		 * accordingly.
		 */
		formPermissions() {
			return (this.callStart ? PERMISSIONS.CALL_START : 0)
			| (this.lobbyIgnore ? PERMISSIONS.LOBBY_IGNORE : 0)
			| (this.publishAudio ? PERMISSIONS.PUBLISH_AUDIO : 0)
			| (this.publishVideo ? PERMISSIONS.PUBLISH_VIDEO : 0)
			| (this.publishScreen ? PERMISSIONS.PUBLISH_SCREEN : 0)
			| PERMISSIONS.CUSTOM
		},

		/**
		 * If the permissions are not changed, the submit button will stay
		 * disabled.
		 */
		submitButtonDisabled() {
			return this.currentPermissions === this.formPermissions
		},
	},

	watch: {
		/**
		 * Every time that the permissions change in the store, we write them
		 * to this component's data.
		 *
		 * @param {number} newValue - the updated permissions.
		 */
		currentPermissions(newValue) {
			this.writePermissionsToComponent(newValue)
		},
	},

	beforeMount() {
		this.writePermissionsToComponent(this.currentPermissions)
	},

	methods: {
		/**
		 * Takes the permissions from the store and writes them in the data of
		 * this component.
		 *
		 * @param {number} permissions - the permissions number.
		 */
		writePermissionsToComponent(permissions) {
			permissions & PERMISSIONS.CALL_START ? this.callStart = true : this.callStart = false
			permissions & PERMISSIONS.LOBBY_IGNORE ? this.lobbyIgnore = true : this.lobbyIgnore = false
			permissions & PERMISSIONS.PUBLISH_AUDIO ? this.publishAudio = true : this.publishAudio = false
			permissions & PERMISSIONS.PUBLISH_VIDEO ? this.publishVideo = true : this.publishVideo = false
			permissions & PERMISSIONS.PUBLISH_SCREEN ? this.publishScreen = true : this.publishScreen = false
		},

		/**
		 * Binary sum all the permissions and make the request to change them.
		 */
		handleSubmitPermissions() {
			try {
				this.$store.dispatch('setPermissions', {
					token: this.token,
					attendeeId: this.attendeeId,
					permissions: this.formPermissions,
				})
				showSuccess(t('spreed', 'Permissions modified for {displayName}', { displayName: this.displayName }))
			} catch (error) {
				console.debug(error)
				showError(t('spreed', 'Could not modify permissions for {displayName}', { displayName: this.displayName }))
			} finally {
				// Closes the modal window
				this.$emit('close')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../../../../assets/buttons.scss';

.wrapper {
	width: 350px;
	padding: 24px;
}

.nc-button {
	width: 100%;
	margin-top: 12px;
}

.title {
	font-size: 16px;
	margin-bottom: 12px;
}
</style>
