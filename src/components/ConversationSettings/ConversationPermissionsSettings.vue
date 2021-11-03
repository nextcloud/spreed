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
			{{ t('spreed', 'Edit default permissions') }}
		</h4>
		<CheckboxRadioSwitch :checked.sync="permissionType"
			value="unrestricted"
			name="permission_radio"
			type="radio">
			{{ t('spreed', 'Unrestricted') }}
		</CheckboxRadioSwitch>
		<p>{{ t('spreed', 'Everyone has permissions to start a call, join a call, enable audio, video and screenshare.') }}</p>
		<CheckboxRadioSwitch :checked.sync="permissionType"
			value="restricted"
			name="permission_radio"
			type="radio">
			{{ t('spreed', 'Restricted') }}
		</CheckboxRadioSwitch>
		<p>{{ t('spreed', 'Same as above, but only moderators can start calls.') }}</p>
		<CheckboxRadioSwitch :checked.sync="permissionType"
			value="custom"
			name="permission_radio"
			type="radio">
			{{ t('spreed', 'Advanced permissions') }}
		</CheckboxRadioSwitch>
			<button
				v-show="showEditButton"
				class="nc-button nc-button__main"
				@click="showPermissionsEditor = true">
				<Pencil
					:size="20"
					decorative
					title="" />
			</button>
		<PermissionEditor
			v-if="showPermissionsEditor"
			:conversation-name="conversationName"
			:permissions="conversationPermissions"
			@close="showPermissionsEditor = false"
			@submit="handleSubmitPermissions" />
	</div>
</template>

<script>
import PermissionEditor from '../PermissionsEditor/PermissionsEditor.vue'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import Pencil from 'vue-material-design-icons/Pencil.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'

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
			permissionType: '',
			showPermissionsEditor: false,
			isEditingPermissions: false,
		}
	},

	computed: {
		/**
		 * The participant's name.
		 */
		conversationName() {
			return this.$store.getters.conversation(this.token).name
		},

		conversationPermissions() {
			return this.$store.getters.conversation(this.token).permissions
		},

		showEditButton() {
			return this.permissionType === 'custom' && !this.showPermissionsEditor
		},
	},

	watch: {
		permissionType(newValue) {
			this.isEditingPermissions = true

			if (newValue === 'custom') {
				this.showPermissionsEditor = true
			}
			if (newValue === 'unrestricted') {
				this.showPermissionsEditor = false
			}
			if (newValue === 'restricted') {
				this.showPermissionsEditor = false
			}
		},
	},

	methods: {
		/**
		 * Binary sum all the permissions and make the request to change them.
		 *
		 * @param {number} permissions - the permission number.
		 * @param previousPermissions
		 */
		async handleSubmitPermissions(permissions, previousPermissions) {
			try {
				await this.$store.dispatch('setConversationPermissions', {
					token: this.token,
					permissions,
				})
				showSuccess(t('spreed', 'Default permissions modified for {conversationName}', { conversationName: this.conversationName }))
				this.showPermissionsEditor = false
			} catch (error) {
				console.debug(error)
				showError(t('spreed', 'Could not modify default permissions for {conversationName}', { conversationName: this.conversationName }))
				// Go back to previous permissions in the form
				this.permissionType = previousPermissions
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/buttons.scss';

::v-deep .mx-input {
	margin: 0;
}
</style>
