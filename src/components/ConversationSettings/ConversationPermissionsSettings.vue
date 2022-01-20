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
	<div class="conversation-permissions-editor">
		<h4 class="conversation-permissions-editor__title">
			{{ t('spreed', 'Edit the default permissions for participants in this conversation. These settings do not affect moderators.') }}
		</h4>
		<p>{{ t('spreed', 'Warning: Every time permissions are modified in this section, custom permissions previously assigned to individual participants will be lost.') }}</p>

		<!-- All permissions -->
		<div class="conversation-permissions-editor__setting">
			<CheckboxRadioSwitch
				:checked.sync="radioValue"
				:disabled="loading"
				value="all"
				name="permission_radio"
				type="radio"
				@update:checked="handleSubmitPermissions">
				{{ t('spreed', 'All permissions') }}
			</CheckboxRadioSwitch>
			<span v-show="loading && radioValue === 'all'" class="icon-loading-small" />
		</div>
		<p>{{ t('spreed', 'Participants have permissions to start a call, join a call, enable audio and video, and share screen.') }}</p>

		<!-- No permissions -->
		<div class="conversation-permissions-editor__setting">
			<CheckboxRadioSwitch
				:checked.sync="radioValue"
				value="restricted"
				:disabled="loading"
				name="permission_radio"
				type="radio"
				@update:checked="handleSubmitPermissions">
				{{ t('spreed', 'Restricted') }}
			</CheckboxRadioSwitch>
			<span v-show="loading && radioValue === 'restricted'" class="icon-loading-small" />
		</div>
		<p>{{ t('spreed', 'Participants can join calls, but cannot enable audio nor video nor share screen until a moderator manually grants them permissions.') }}</p>

		<!-- Advanced permissions -->
		<div class="conversation-permissions-editor__setting--advanced">
			<CheckboxRadioSwitch
				:checked.sync="radioValue"
				value="advanced"
				:disabled="loading"
				name="permission_radio"
				type="radio"
				@update:checked="showPermissionsEditor = true">
				{{ t('spreed', 'Advanced permissions') }}
			</CheckboxRadioSwitch>

			<!-- Edit advanced permissions -->
			<button
				v-show="showEditButton"
				:aria-label="t('spreed', 'Edit permissions')"
				class="nc-button nc-button__main"
				@click="showPermissionsEditor = true">
				<Pencil
					:size="20"
					decorative
					title="" />
			</button>
		</div>
		<PermissionEditor
			v-if="showPermissionsEditor"
			:conversation-name="conversationName"
			:permissions="conversationPermissions"
			:loading="loading"
			@close="handleClosePermissionsEditor"
			@submit="handleSubmitPermissions" />
	</div>
</template>

<script>
import PermissionEditor from '../PermissionsEditor/PermissionsEditor.vue'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import { PARTICIPANT } from '../../constants'
import { showError, showSuccess } from '@nextcloud/dialogs'

const PERMISSIONS = PARTICIPANT.PERMISSIONS

export default {
	name: 'ConversationPermissionsSettings',

	components: {
		PermissionEditor,
		CheckboxRadioSwitch,
		Pencil,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			showPermissionsEditor: false,
			isEditingPermissions: false,
			loading: false,
			radioValue: '',
		}
	},

	computed: {
		/**
		 * The participant's name.
		 */
		conversationName() {
			return this.$store.getters.conversation(this.token).name
		},

		/**
		 * The current conversation permissions.
		 */
		conversationPermissions() {
			return this.$store.getters.conversation(this.token).defaultPermissions
		},

		/**
		 * Hides and shows the edit button for advanced permissions.
		 */
		showEditButton() {
			return this.radioValue === 'advanced' && !this.showPermissionsEditor
		},
	},

	mounted() {
		/**
		 * Set the initial radio value.
		 */
		this.setCurrentRadioValue()
	},

	methods: {
		/**
		 * Binary sum all the permissions and make the request to change them.
		 *
		 * @param {string | number} value - The permissions value, which is a
		 * string (e.g. 'restricted' or 'all') unless this method is called by
		 * the click event emitted by the `permissionsEditor` component, in
		 * which case it's a number indicating the permissions value.
		 */
		async handleSubmitPermissions(value) {
			let permissions

			// Compute the permissions value
			switch (value) {
			case 'all':
				permissions = PERMISSIONS.MAX_DEFAULT
				break
			case 'restricted':
				permissions = PERMISSIONS.CALL_JOIN
				break
			default:
				permissions = value
			}

			this.loading = true

			// Make the store call
			try {
				await this.$store.dispatch('setConversationPermissions', {
					token: this.token,
					permissions,
				})
				showSuccess(t('spreed', 'Default permissions modified for {conversationName}', { conversationName: this.conversationName }))

				// Modify the radio buttons value
				this.radioValue = this.getPermissionRadioValue(permissions)
				this.showPermissionsEditor = false
			} catch (error) {
				console.debug(error)
				showError(t('spreed', 'Could not modify default permissions for {conversationName}', { conversationName: this.conversationName }))

				// Go back to the previous radio value
				this.radioValue = this.getPermissionRadioValue(this.conversationPermissions)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Get the radio button string value given a permission number.
		 *
		 * @param {number} value - The permissions value.
		 */
		getPermissionRadioValue(value) {
			switch (value) {
			case PERMISSIONS.MAX_DEFAULT:
				return 'all'
			case PERMISSIONS.CALL_JOIN:
				return 'restricted'

			default:
				return 'advanced'
			}
		},

		/**
		 * Set the radio value that corresponds to the current default
		 * permissions in the store.
		 */
		setCurrentRadioValue() {
			this.radioValue = this.getPermissionRadioValue(this.conversationPermissions)
		},

		/**
		 * Hides the modal and resets conversation permissions to the previous
		 * value.
		 */
		handleClosePermissionsEditor() {
			this.showPermissionsEditor = false
			this.setCurrentRadioValue()
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/buttons';

::v-deep .mx-input {
	margin: 0;
}

.conversation-permissions-editor {
	&__setting {
		display: flex;
		justify-content: space-between;
		&--advanced {
			display: flex;
			justify-content: flex-start;

			// Edit button
			button {
				margin-left: 16px;
			}
		}
	}
}

p {
	color: var(--color-text-lighter);
	margin-bottom: 16px;
}
</style>
