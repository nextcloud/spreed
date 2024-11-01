<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Guest access') }}
		</h4>

		<template v-if="canModerate">
			<p v-if="hasBreakoutRooms" class="app-settings-section__hint">
				{{ t('spreed', 'Breakout rooms are not allowed in public conversations.') }}
			</p>
			<NcCheckboxRadioSwitch :model-value="isSharedPublicly"
				:disabled="hasBreakoutRooms || isSaving"
				type="switch"
				aria-describedby="link_share_settings_hint"
				@update:model-value="toggleGuests">
				{{ t('spreed', 'Allow guests to join this conversation via link') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch v-show="isSharedPublicly"
				:model-value="isPasswordProtectionChecked"
				:disabled="isSaving"
				type="switch"
				aria-describedby="link_share_settings_password_hint"
				@update:model-value="togglePassword">
				{{ t('spreed', 'Password protection') }}
			</NcCheckboxRadioSwitch>

			<form v-if="showPasswordField" class="password-form" @submit.prevent="handleSetNewPassword">
				<NcPasswordField ref="passwordField"
					v-model="password"
					autocomplete="new-password"
					check-password-strength
					:disabled="isSaving"
					class="password-form__input-field"
					label-visible
					:label="t('spreed', 'Enter new password')"
					@valid="isValid = true"
					@invalid="isValid = false" />
				<NcButton :disabled="isSaving || !isValid"
					type="primary"
					native-type="submit"
					class="password-form__button">
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
				@click="handleCopyLink">
				<template #icon>
					<ClipboardTextOutline />
				</template>
				{{ t('spreed', 'Copy conversation link') }}
			</NcButton>
			<NcButton v-if="isSharedPublicly && canModerate"
				:disabled="isSendingInvitations"
				@click="handleResendInvitations">
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
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'

import { CONVERSATION } from '../../constants.js'
import { copyConversationLinkToClipboard } from '../../utils/handleUrl.ts'

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

		canModerate: {
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
			isValid: true,
		}
	},

	computed: {
		isSharedPublicly() {
			return this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasBreakoutRooms() {
			return this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},

		isPasswordProtectionChecked() {
			return this.conversation.hasPassword || this.showPasswordField
		},
	},

	methods: {
		t,
		async setConversationPassword(newPassword) {
			this.isSaving = true
			await this.$store.dispatch('setConversationPassword', {
				token: this.token,
				newPassword,
			})
			this.isSaving = false
		},

		async toggleGuests() {
			const allowGuests = this.conversation.type !== CONVERSATION.TYPE.PUBLIC
			this.isSaving = true
			await this.$store.dispatch('toggleGuests', { token: this.token, allowGuests })
			this.isSaving = false
		},

		async togglePassword(checked) {
			if (checked) {
				this.showPasswordField = true
				await this.handlePasswordEnable()
				this.$nextTick(() => {
					this.$refs.passwordField.focus()
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
			this.isValid = true
		},

		async handlePasswordEnable() {
			this.showPasswordField = true
		},

		async handleSetNewPassword() {
			if (this.isValid) {
				await this.setConversationPassword(this.password)
				this.password = ''
				this.showPasswordField = false
			}
		},

		handleCopyLink() {
			copyConversationLinkToClipboard(this.token)
		},

		async handleResendInvitations() {
			this.isSendingInvitations = true
			await this.$store.dispatch('resendInvitations', { token: this.token })
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
	align-items: flex-start;

	&__input-field {
		width: 200px;
	}

	&__button {
		margin-top: 6px;
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
