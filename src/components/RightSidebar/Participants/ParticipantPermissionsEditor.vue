<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="wrapper">
		<PermissionEditor :display-name="displayName"
			:permissions="permissions"
			v-on="$listeners"
			@submit="handleSubmitPermissions" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'

import PermissionEditor from '../../PermissionsEditor/PermissionsEditor.vue'

import { PARTICIPANT } from '../../../constants.js'

export default {
	name: 'ParticipantPermissionsEditor',

	components: {
		PermissionEditor,
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

	emits: ['close'],

	computed: {
		/**
		 * The participant's name.
		 */
		displayName() {
			if (this.participant.displayName === '' && this.isGuest) {
				return t('spreed', 'Guest')
			}

			return this.participant.displayName
		},

		/**
		 * Whether the participant is a guest or not.
		 */
		isGuest() {
			return [PARTICIPANT.TYPE.GUEST, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(this.participant.participantType)
		},

		/**
		 * The participant's attendeeId: a unique number that identifies the
		 * participant.
		 */
		attendeeId() {
			return this.participant.attendeeId
		},

		/**
		 * Combined final permissions of the current participant, from the
		 * participants store.
		 */
		permissions() {
			return this.participant.permissions
		},
	},

	methods: {
		/**
		 * Binary sum all the permissions and make the request to change them.
		 *
		 * @param {number} permissions - the permission number.
		 */
		handleSubmitPermissions(permissions) {
			try {
				this.$store.dispatch('setPermissions', {
					token: this.token,
					attendeeId: this.attendeeId,
					permissions,
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
