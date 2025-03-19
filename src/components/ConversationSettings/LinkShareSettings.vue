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

			<template v-if="isSharedPublicly">
				<NcCheckboxRadioSwitch v-if="!forcePasswordProtection"
					:model-value="isPasswordProtectionChecked"
					:disabled="isSaving"
					type="switch"
					aria-describedby="link_share_settings_password_hint"
					@update:model-value="togglePassword">
					{{ t('spreed', 'Password protection') }}
				</NcCheckboxRadioSwitch>
				<template v-else>
					<p v-if="isPasswordProtectionChecked" class="app-settings-section__hint">
						{{ t('spreed', 'This conversation is password-protected. Guests need password to join') }}
					</p>
					<NcNoteCard v-else-if="!isSaving"
						type="warning">
						{{ t('spreed', 'Password protection is needed for public conversations') }}
						<NcButton class="warning__button" type="primary" @click="enforcePassword">
							{{ t('spreed', 'Set a password') }}
						</NcButton>
					</NcNoteCard>
				</template>

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
							<IconContentSaveOutline />
						</template>
						{{ t('spreed', 'Save password') }}
					</NcButton>
					<NcButton v-if="password"
						type="tertiary"
						:aria-label="t('spreed', 'Copy password')"
						:title="t('spreed', 'Copy password')"
						class="password-form__button"
						@click="copyPassword">
						<template #icon>
							<IconContentCopy :size="16" />
						</template>
					</NcButton>
				</form>
			</template>
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
					<IconClipboardTextOutline />
				</template>
				{{ t('spreed', 'Copy link') }}
			</NcButton>
			<NcButton v-if="isSharedPublicly && canModerate"
				:disabled="isSendingInvitations"
				@click="handleResendInvitations">
				<template #icon>
					<NcLoadingIcon v-if="isSendingInvitations" />
					<IconEmail v-else />
				</template>
				{{ t('spreed', 'Resend invitations') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import IconClipboardTextOutline from 'vue-material-design-icons/ClipboardTextOutline.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import IconEmail from 'vue-material-design-icons/Email.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'

import { CONVERSATION } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import generatePassword from '../../utils/generatePassword.ts'
import { copyConversationLinkToClipboard } from '../../utils/handleUrl.ts'

export default {
	name: 'LinkShareSettings',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcNoteCard,
		NcLoadingIcon,
		// Icons
		IconClipboardTextOutline,
		IconContentCopy,
		IconContentSaveOutline,
		IconEmail,
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

		forcePasswordProtection() {
			return this.supportForcePasswordProtection && getTalkConfig(this.token, 'conversations', 'force-passwords')
		},

		supportForcePasswordProtection() {
			return hasTalkFeature(this.token, 'conversation-creation-password')
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
			if (this.forcePasswordProtection && allowGuests) {
				await this.togglePassword(allowGuests)
				await this.$store.dispatch('toggleGuests', { token: this.token, allowGuests, password: this.password })
			} else {
				if (!allowGuests) {
					await this.togglePassword(false)
				}
				await this.$store.dispatch('toggleGuests', { token: this.token, allowGuests })
			}
			this.isSaving = false
		},

		async togglePassword(checked) {
			if (checked) {
				// Generate a random password
				this.password = await generatePassword()
				this.showPasswordField = true
			} else {
				// disable the password protection for the current conversation
				if (this.conversation.hasPassword) {
					await this.setConversationPassword('')
				}
				this.password = ''
				this.showPasswordField = false
				this.isValid = true
			}
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

		async copyPassword() {
			try {
				await navigator.clipboard.writeText(this.password)
				showSuccess(t('spreed', 'Password copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Password could not be copied'))
			}
		},

		async enforcePassword() {
			// Turn on password protection and set a password
			await this.togglePassword(true)
			await this.$store.dispatch('toggleGuests', { token: this.token, allowGuests: true, password: this.password })
		}
	},
}
</script>

<style lang="scss" scoped>
.password-form {
	display: flex;
	gap: 8px;
	align-items: flex-start;

	:deep(.input-field) {
		width: 200px;
	}

	&__button {
		margin-top: 6px;
	}
}

.warning__button {
	margin-top: var(--default-grid-baseline);
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
