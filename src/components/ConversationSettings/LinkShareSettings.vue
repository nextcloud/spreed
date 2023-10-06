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
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Guest access') }}
		</h4>

		<template v-if="canFullModerate">
			<NcCheckboxRadioSwitch :checked="isSharedPublicly"
				:disabled="isSaving"
				type="switch"
				aria-describedby="link_share_settings_hint"
				@update:checked="toggleGuests">
				{{ t('spreed', 'Allow guests to join this conversation via link') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch v-show="isSharedPublicly"
				:checked="conversation.hasPassword"
				:disabled="isSaving"
				type="switch"
				aria-describedby="link_share_settings_password_hint"
				@update:checked="togglePassword">
				{{ t('spreed', 'Password protection') }}
			</NcCheckboxRadioSwitch>

			<form v-if="showPasswordField" class="password-form" @submit.prevent="handleSetNewPassword">
				<NcPasswordField ref="passwordField"
					:value.sync="password"
					autocomplete="new-password"
					check-password-strength
					:disabled="isSaving"
					class="password-form__input-field"
					label-visible
					:label="t('spreed', 'Enter new password')" />
				<NcButton :disabled="isSaving" type="primary" native-type="submit">
					<template #icon>
						<ArrowRight />
					</template>
					{{ t('spreed', 'Save password') }}
				</NcButton>
			</form>
		</template>

		<p v-else-if="isSharedPublicly">
			{{ t('spreed', 'Guests are allowed to join this conversation via link') }}
		</p>
		<p v-else>
			{{ t('spreed', 'Guests are not allowed to join this conversation') }}
		</p>

		<div class="app-settings-subsection__buttons">
			<NcButton ref="copyLinkButton"
				@click="handleCopyLink"
				@keydown.enter="handleCopyLink">
				<template #icon>
					<ClipboardTextOutline />
				</template>
				{{ t('spreed', 'Copy conversation link') }}
			</NcButton>
			<NcButton v-if="isSharedPublicly && canFullModerate"
				:disabled="isSendingInvitations"
				@click="handleResendInvitations"
				@keydown.enter="handleResendInvitations">
				<template #icon>
					<Email />
				</template>
				{{ t('spreed', 'Resend invitations') }}
			</NcButton>
			<span v-if="isSendingInvitations" class="icon-loading-small spinner" />
		</div>
	</div>
</template>

<script>
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import ClipboardTextOutline from 'vue-material-design-icons/ClipboardTextOutline.vue'
import Email from 'vue-material-design-icons/Email.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'

import { CONVERSATION } from '../../constants.js'
import { copyConversationLinkToClipboard } from '../../services/urlService.js'

export default {
	name: 'LinkShareSettings',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcPasswordField,
		ArrowRight,
		ClipboardTextOutline,
		Email,
	},

	props: {
		token: {
			type: String,
			default: null,
		},

		canFullModerate: {
			type: Boolean,
			default: true,
		},
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

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
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
				await this.$store.dispatch('setConversationPassword', {
					token: this.token,
					newPassword,
				})
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

		async togglePassword(checked) {
			if (checked) {
				this.showPasswordField = true
				await this.handlePasswordEnable()
				this.$nextTick(() => {
					this.$refs.passwordField.$el.focus()
				})
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

		handleCopyLink() {
			copyConversationLinkToClipboard(this.token)
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

.password-form {
	display: flex;
	gap: 8px;
	align-items: flex-end;

	&__input-field {
		width: 200px;
	}
}

.app-settings-subsection__buttons {
	display: flex;
	gap: 8px;
	margin-top: 25px;
	& > button {
		flex-basis: 50%;
	}
}
</style>
