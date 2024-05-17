<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
import { t } from '@nextcloud/l10n'

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
		t,
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
