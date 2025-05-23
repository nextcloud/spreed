<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="app-settings-subsection">
		<h4 class="app-settings-section__subtitle">
			{{ t('spreed', 'Recording Consent') }}
		</h4>
		<div v-if="disabled && !loading" class="app-settings-section__hint">
			{{ t('spreed', 'Recording consent cannot be changed once a call or breakout session has started.') }}
		</div>
		<NcCheckboxRadioSwitch v-if="canModerate && !isGlobalConsent"
			v-model="recordingConsentSelected"
			type="switch"
			:disabled="disabled"
			@update:model-value="setRecordingConsent">
			{{ t('spreed', 'Require recording consent before joining call in this conversation') }}
		</NcCheckboxRadioSwitch>
		<p v-else-if="isGlobalConsent">
			{{ t('spreed', 'Recording consent is required for all calls') }}
		</p>
		<p v-else>
			{{ summaryLabel }}
		</p>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { CALL, CONFIG, CONVERSATION } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'

export default {
	name: 'RecordingConsentSettings',

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
			default: true,
		},
	},

	data() {
		return {
			loading: false,
			recordingConsentSelected: !!CALL.RECORDING_CONSENT.DISABLED,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isGlobalConsent() {
			return getTalkConfig(this.token, 'call', 'recording-consent') === CONFIG.RECORDING_CONSENT.REQUIRED
		},

		isBreakoutRoomStarted() {
			return this.conversation.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
		},

		disabled() {
			return this.loading || this.conversation.hasCall || this.isBreakoutRoomStarted
		},

		summaryLabel() {
			return this.conversation.recordingConsent === CALL.RECORDING_CONSENT.ENABLED
				? t('spreed', 'Recording consent is required for calls in this conversation')
				: t('spreed', 'Recording consent is not required for calls in this conversation')
		},
	},

	mounted() {
		this.recordingConsentSelected = !!this.conversation.recordingConsent
	},

	methods: {
		t,
		async setRecordingConsent(value) {
			this.loading = true
			try {
				await this.$store.dispatch('setRecordingConsent', {
					token: this.token,
					state: value ? CALL.RECORDING_CONSENT.ENABLED : CALL.RECORDING_CONSENT.DISABLED,
				})
				showSuccess(t('spreed', 'Recording consent requirement was updated'))
			} catch (error) {
				showError(t('spreed', 'Error occurred while updating recording consent'))
				console.error(error)
			}
			this.loading = false
		},
	},
}
</script>
