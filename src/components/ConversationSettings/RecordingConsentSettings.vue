<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
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
			{{ t('spreed', 'Recording Consent') }}
		</h4>
		<div v-if="disabled && !loading" class="app-settings-section__hint">
			{{ t('spreed', 'Recording consent cannot be changed once a call or breakout session has started.') }}
		</div>
		<NcCheckboxRadioSwitch v-if="canFullModerate && !isGlobalConsent"
			type="switch"
			:checked.sync="recordingConsentSelected"
			:disabled="disabled"
			@update:checked="setRecordingConsent">
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
import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess } from '@nextcloud/dialogs'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { CALL, CONVERSATION } from '../../constants.js'

const recordingConsent = getCapabilities()?.spreed?.config?.call?.['recording-consent']

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

		canFullModerate: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			loading: false,
			recordingConsentSelected: !!CALL.RECORDING_CONSENT.OFF,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isGlobalConsent() {
			return recordingConsent === CALL.RECORDING_CONSENT.REQUIRED
		},

		isBreakoutRoomStarted() {
			return this.conversation.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
		},

		disabled() {
			return this.loading || this.conversation.hasCall || this.isBreakoutRoomStarted
		},

		summaryLabel() {
			return this.conversation.recordingConsent === CALL.RECORDING_CONSENT.REQUIRED
				? t('spreed', 'Recording consent is required for calls in this conversation')
				: t('spreed', 'Recording consent is not required for calls in this conversation')
		},
	},

	mounted() {
		this.recordingConsentSelected = !!this.conversation.recordingConsent
	},

	methods: {
		async setRecordingConsent(value) {
			this.loading = true
			try {
				await this.$store.dispatch('setRecordingConsent', {
					token: this.token,
					state: value ? CALL.RECORDING_CONSENT.REQUIRED : CALL.RECORDING_CONSENT.OFF,
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

<style lang="scss" scoped>
</style>
