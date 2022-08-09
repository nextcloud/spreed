<!--
  - @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
  -
  - @author Vincent Petry <vincent@nextcloud.com>
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
	<div>
		<div class="app-settings-subsection">
			<div id="link_share_settings_hint" class="app-settings-section__hint">
				{{ t('spreed', 'Allow guests to use a public link to join this conversation.') }}
			</div>
			<div>
				<input id="link_share_settings_toggle_guests"
					ref="toggleGuests"
					aria-describedby="link_share_settings_hint"
					type="checkbox"
					class="checkbox"
					name="link_share_settings_toggle_guests"
					:checked="isSharedPublicly"
					:disabled="isSaving"
					@change="toggleGuests">
				<label for="link_share_settings_toggle_guests">{{ t('spreed', 'Allow guests') }}</label>
			</div>
		</div>
		<div v-show="isSharedPublicly" class="app-settings-subsection" aria-live="polite">
			<div id="link_share_settings_password_hint" class="app-settings-section__hint">
				{{ t('spreed', 'Set a password to restrict who can use the public link.') }}
			</div>
			<div>
				<input id="link_share_settings_toggle_password"
					ref="togglePassword"
					aria-describedby="link_share_settings_password_hint"
					type="checkbox"
					class="checkbox"
					:checked="conversation.hasPassword"
					name="link_share_settings_toggle_password"
					:disabled="isSaving"
					@change="togglePassword">
				<label for="link_share_settings_toggle_password">{{ t('spreed', 'Password protection') }}</label>
			</div>
		</div>
		<div class="app-settings-subsection">
			<div v-show="showPasswordField">
				<form :disabled="isSaving"
					@submit.prevent="handleSetNewPassword">
					<span class="icon-password" />
					<input id="link_share_settings_link_password"
						ref="passwordField"
						v-model="password"
						aria-describedby="link_share_settings_password_hint"
						type="password"
						class="checkbox"
						autocomplete="new-password"
						name="link_share_settings_link_password"
						:placeholder="t('spreed', 'Enter a password')"
						:disabled="isSaving">
					<button id="link_share_settings_link_password_submit"
						:aria-label="t('spreed', 'Save password')"
						:disabled="isSaving"
						type="submit"
						class="icon icon-confirm-fade" />
				</form>
			</div>
		</div>
		<div class="app-settings-subsection">
			<button ref="copyLinkButton"
				@click.prevent="handleCopyLink">
				<ClipboardTextOutline :size="16" />
				{{ t('spreed', 'Copy conversation link') }}
			</button>
		</div>
		<div v-if="isSharedPublicly" class="app-settings-subsection">
			<button :disabled="isSendingInvitations"
				@click.prevent="handleResendInvitations">
				<Email :size="16" />
				{{ t('spreed', 'Resend invitations') }}
			</button>
			<span v-if="isSendingInvitations" class="icon-loading-small spinner" />
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants.js'
import {
	setConversationPassword,
} from '../../services/conversationsService.js'
import { generateUrl } from '@nextcloud/router'
import ClipboardTextOutline from 'vue-material-design-icons/ClipboardTextOutline'
import Email from 'vue-material-design-icons/Email'

export default {
	name: 'LinkShareSettings',

	components: {
		ClipboardTextOutline,
		Email,
	},

	data() {
		return {
			// The conversation's password
			password: '',
			// Switch for the password-editing operation
			showPasswordField: false,
			isSaving: false,
			isSendingInvitations: false,
		}
	},

	computed: {
		isSharedPublicly() {
			return this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		linkToConversation() {
			return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.token)
		},
	},

	methods: {
		focus() {
			this.$nextTick(() => {
				this.$refs.toggleGuests.focus()
			})
		},

		async setConversationPassword(newPassword) {
			this.isSaving = true
			try {
				await setConversationPassword(this.token, newPassword)
				if (newPassword !== '') {
					showSuccess(t('spreed', 'Conversation password has been saved'))
				} else {
					showSuccess(t('spreed', 'Conversation password has been removed'))
				}
			} catch (error) {
				console.error('Error saving conversation password', error)
				if (error?.response?.data?.ocs?.data?.message) {
					showError(error.response.data.ocs.data.message)
				} else {
					showError(t('spreed', 'Error occurred while saving conversation password'))
				}
			}
			this.isSaving = false
		},

		async toggleGuests() {
			const enabled = this.conversation.type !== CONVERSATION.TYPE.PUBLIC
			this.isSaving = true
			try {
				await this.$store.dispatch('toggleGuests', {
					token: this.token,
					allowGuests: enabled,
				})

				if (enabled) {
					showSuccess(t('spreed', 'You allowed guests'))
				} else {
					showSuccess(t('spreed', 'You disallowed guests'))
				}
			} catch (e) {
				if (enabled) {
					showError(t('spreed', 'Error occurred while allowing guests'))
				} else {
					showError(t('spreed', 'Error occurred while disallowing guests'))
				}
				console.error('Error toggling guest mode', e)
			}
			this.isSaving = false
		},

		async togglePassword() {
			if (this.$refs.togglePassword.checked) {
				this.showPasswordField = true
				this.$refs.passwordField.focus()
				await this.handlePasswordEnable()
			} else {
				this.showPasswordField = false
				await this.handlePasswordDisable()
			}
		},

		async handlePasswordDisable() {
			// disable the password protection for the current conversation
			if (this.conversation.hasPassword) {
				await this.setConversationPassword('')
			}
			this.password = ''
			this.showPasswordField = false
		},

		async handlePasswordEnable() {
			this.showPasswordField = true
		},

		async handleSetNewPassword() {
			await this.setConversationPassword(this.password)
			this.password = ''
			this.showPasswordField = false
		},

		async handleCopyLink() {
			try {
				await this.$copyText(this.linkToConversation)
				showSuccess(t('spreed', 'Conversation link copied to clipboard.'))
			} catch (error) {
				showError(t('spreed', 'The link could not be copied.'))
			}
			// workaround for https://github.com/Inndy/vue-clipboard2/issues/105
			this.$refs.copyLinkButton.focus()
		},

		async handleResendInvitations() {
			this.isSendingInvitations = true
			try {
				await this.$store.dispatch('resendInvitations', { token: this.token })
				showSuccess(t('spreed', 'Invitations sent'))
			} catch (e) {
				showError(t('spreed', 'Error occurred when sending invitations'))
			}
			this.isSendingInvitations = false
		},
	},
}
</script>

<style lang="scss" scoped>
button > .material-design-icon {
	display: inline-block;
	vertical-align: middle;
	margin-right: 7px;
}

.spinner {
	margin-left: 24px;
}

input[type=password] {
	width: 200px;
}
</style>
