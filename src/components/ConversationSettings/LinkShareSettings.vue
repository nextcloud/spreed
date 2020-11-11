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
	<ul>
		<h3 class="app-settings-section__hint">
			{{ t('spreed', 'Allow guests to use a public link connect to join this conversation.') }}
		</h3>
		<ActionCheckbox
			:disabled="isSaving"
			:checked="isSharedPublicly"
			@change="toggleGuests">
			{{ t('spreed', 'Allow guests') }}
		</ActionCheckbox>
		<ActionCheckbox
			v-if="isSharedPublicly"
			class="share-link-password-checkbox"
			:disabled="isSaving"
			:checked="conversation.hasPassword"
			@check="handlePasswordEnable"
			@uncheck="handlePasswordDisable">
			{{ t('spreed', 'Password protection') }}
		</ActionCheckbox>
		<ActionInput
			v-show="showPasswordField"
			class="share-link-password"
			icon="icon-password"
			type="password"
			:disabled="isSaving"
			:value.sync="password"
			autocomplete="new-password"
			@submit="handleSetNewPassword">
			{{ t('spreed', 'Enter a password') }}
		</ActionInput>
		<ActionButton
			v-if="isSharedPublicly"
			:disabled="isSaving"
			icon="icon-clippy"
			:close-after-click="true"
			@click="handleCopyLink">
			{{ t('spreed', 'Copy public link') }}
		</ActionButton>
	</ul>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants'
import {
	setConversationPassword,
} from '../../services/conversationsService'
import { generateUrl } from '@nextcloud/router'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'LinkShareSettings',

	components: {
		ActionCheckbox,
		ActionInput,
		ActionButton,
	},

	data() {
		return {
			// The conversation's password
			password: '',
			// Switch for the password-editing operation
			showPasswordField: false,
			isSaving: false,
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
		async setConversationPassword(newPassword) {
			this.isSaving = true
			try {
				await setConversationPassword(this.token, newPassword)
				if (newPassword !== '') {
					showSuccess(t('spreed', 'Conversation password has been saved'))
				} else {
					showSuccess(t('spreed', 'Conversation password has been removed'))
				}
			} catch (e) {
				console.error('Error saving conversation password', e)
				showError(t('spreed', 'Error occurred while saving conversation password'))
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
		},
	},
}
</script>

<style lang="scss" scoped>
.app-settings-section__hint {
	color: var(--color-text-lighter);
	padding: 8px 0;
}
</style>
