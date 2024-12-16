<!--
	- SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Security') }}
		</h4>

		<div>
			<NcCheckboxRadioSwitch :checked="!hasEncryptionEnabled"
				type="switch"
				aria-describedby="encryption_settings_hint"
				:disabled="isEncryptionLoading"
				@update:checked="toggleSetting()">
				{{ t('spreed', 'Disable end-to-end encryption to allow legacy clients.') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'SecuritySettings',

	components: {
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			isEncryptionLoading: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasEncryptionEnabled() {
			return this.conversation.encrypted || false
		},
	},

	methods: {
		t,
		async toggleSetting() {
			const enabled = !this.conversation.encrypted
			try {
				await this.$store.dispatch('setEncryptionEnabled', {
					token: this.token,
					enabled,
				})
				if (this.conversation.encrypted) {
					showSuccess(t('spreed', 'End-to-end encryption is now enabled'))
				} else {
					showSuccess(t('spreed', 'End-to-end encryption is now disabled'))
				}
			} catch (e) {
				// TODO check "precondition failed"
				if (!this.conversation.encrypted) {
					console.error('Error occurred when enabling end-to-end encryption', e)
					showError(t('spreed', 'Error occurred when enabling end-to-end encryption'))
				} else {
					console.error('Error occurred when disabling end-to-end encryption', e)
					showError(t('spreed', 'Error occurred when disabling end-to-end encryption'))
				}
			}
		},
	},
}
</script>
