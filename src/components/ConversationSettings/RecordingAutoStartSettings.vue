<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Automatic recording') }}
		</h4>
		<div v-if="disabled && !loading" class="app-settings-section__hint">
			{{ t('spreed', 'Automatic recording can only be changed before the call starts.') }}
		</div>
		<NcCheckboxRadioSwitch
			v-if="canModerate"
			v-model="recordingAutoStart"
			type="switch"
			:disabled="disabled"
			@update:modelValue="setRecordingAutoStart">
			{{ t('spreed', 'Start recording automatically when the call starts') }}
		</NcCheckboxRadioSwitch>
		<p v-else class="app-settings-section__hint">
			{{ t('spreed', 'Only moderators can change automatic recording for this conversation.') }}
		</p>
		<p class="app-settings-section__hint">
			{{ t('spreed', 'Any participant can start the call. The recording is owned by the conversation owner, even when they are not in the call.') }}
		</p>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { CONVERSATION } from '../../constants.ts'

export default {
	name: 'RecordingAutoStartSettings',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		token: {
			type: String,
			default: null,
		},

		canModerate: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loading: false,
			recordingAutoStart: false,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		disabled() {
			return this.loading || this.conversation.hasCall
		},
	},

	mounted() {
		this.recordingAutoStart = (this.conversation.attributes & CONVERSATION.ATTRIBUTE.RECORDING_AUTO_START) !== 0
	},

	methods: {
		t,

		async setRecordingAutoStart(value) {
			this.loading = true
			try {
				await this.$store.dispatch('setRecordingAutoStart', {
					token: this.token,
					state: value,
				})
				showSuccess(t('spreed', 'Automatic recording setting was updated'))
			} catch (error) {
				this.recordingAutoStart = !value
				showError(t('spreed', 'Error occurred while updating automatic recording'))
				console.error(error)
			}
			this.loading = false
		},
	},
}
</script>
