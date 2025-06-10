<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="conversation-permissions-editor">
		<div class="app-settings-section__hint">
			{{ t('spreed', 'Edit the default permissions for participants in this conversation. These settings do not affect moderators.') }}
		</div>

		<NcNoteCard type="warning"
			:text="t('spreed', 'Every time permissions are modified in this section, custom permissions previously assigned to individual participants will be lost.')" />

		<!-- All permissions -->
		<div class="conversation-permissions-editor__setting">
			<NcCheckboxRadioSwitch v-model="radioValue"
				:disabled="loading"
				value="all"
				name="permission_radio"
				type="radio"
				@update:model-value="handleSubmitPermissions">
				{{ t('spreed', 'All permissions') }}
			</NcCheckboxRadioSwitch>
			<span v-show="loading && radioValue === 'all'" class="icon-loading-small" />
		</div>
		<p class="conversation-permissions-editor__hint">
			{{ t('spreed', 'Participants have permissions to start a call, join a call, enable audio and video, and share screen.') }}
		</p>

		<!-- No permissions -->
		<div class="conversation-permissions-editor__setting">
			<NcCheckboxRadioSwitch v-model="radioValue"
				value="restricted"
				:disabled="loading"
				name="permission_radio"
				type="radio"
				@update:model-value="handleSubmitPermissions">
				{{ t('spreed', 'Restricted') }}
			</NcCheckboxRadioSwitch>
			<span v-show="loading && radioValue === 'restricted'" class="icon-loading-small" />
		</div>
		<p class="conversation-permissions-editor__hint">
			{{ t('spreed', 'Participants can join calls, but cannot enable audio nor video nor share screen until a moderator manually grants them permissions.') }}
		</p>

		<!-- Advanced permissions -->
		<div class="conversation-permissions-editor__setting--advanced">
			<NcCheckboxRadioSwitch v-model="radioValue"
				value="advanced"
				:disabled="loading"
				name="permission_radio"
				type="radio"
				@update:model-value="showPermissionsEditor = true">
				{{ t('spreed', 'Advanced permissions') }}
			</NcCheckboxRadioSwitch>

			<!-- Edit advanced permissions -->
			<NcButton v-show="showEditButton"
				class="edit-button"
				variant="tertiary"
				:aria-label="t('spreed', 'Edit permissions')"
				@click="showPermissionsEditor = true">
				<template #icon>
					<Pencil :size="20" />
				</template>
			</NcButton>
		</div>
		<PermissionEditor v-if="showPermissionsEditor"
			:conversation-name="conversationName"
			:permissions="conversationPermissions"
			:loading="loading"
			nested-container=".conversation-permissions-editor"
			@close="handleClosePermissionsEditor"
			@submit="handleSubmitPermissions" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import PermissionEditor from '../PermissionsEditor/PermissionsEditor.vue'
import { PARTICIPANT } from '../../constants.ts'

const PERMISSIONS = PARTICIPANT.PERMISSIONS

export default {
	name: 'ConversationPermissionsSettings',

	components: {
		PermissionEditor,
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
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
		t,
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
				case PERMISSIONS.MAX_CUSTOM:
					return 'all'
				case PERMISSIONS.CALL_JOIN:
				case PERMISSIONS.CALL_JOIN | PERMISSIONS.CUSTOM:
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
:deep(.mx-input) {
	margin: 0;
}

.conversation-permissions-editor {
	&__setting {
		display: flex;
		justify-content: space-between;
		&--advanced {
			display: flex;
			justify-content: flex-start;
		}
	}
}

.edit-button {
	margin-inline-start: 16px;
}

.conversation-permissions-editor__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}
</style>
