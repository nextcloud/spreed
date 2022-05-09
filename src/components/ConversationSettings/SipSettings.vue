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
		<div id="sip_settings_hint" class="app-settings-section__hint">
			{{ t('spreed', 'Allow participants to join from a phone.') }}
		</div>
		<div>
			<input id="sip_settings_checkbox"
				aria-describedby="sip_settings_hint"
				type="checkbox"
				class="checkbox"
				name="sip_settings_checkbox"
				:checked="hasSIPEnabled"
				:disabled="isSipLoading"
				@change="toggleSetting('enable')">
			<label for="sip_settings_checkbox">{{ t('spreed', 'Enable SIP dial-in') }}</label>
		</div>
		<div v-if="hasSIPEnabled">
			<input id="sip_nopin_checkbox"
				aria-describedby="sip_nopin_checkbox"
				type="checkbox"
				class="checkbox"
				name="sip_nopin_checkbox"
				:checked="noPinRequired"
				:disabled="isSipLoading || !hasSIPEnabled"
				@change="toggleSetting('nopin')">
			<label for="sip_nopin_checkbox">{{ t('spreed', 'Allow to dial-in without a PIN') }}</label>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { WEBINAR } from '../../constants'

export default {
	name: 'SipSettings',

	data() {
		return {
			isSipLoading: false,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasSIPEnabled() {
			return this.conversation.sipEnabled !== WEBINAR.SIP.DISABLED
		},

		noPinRequired() {
			return this.conversation.sipEnabled === WEBINAR.SIP.ENABLED_NO_PIN
		},
	},

	methods: {
		async toggleSetting(setting) {
			let state = WEBINAR.SIP.DISABLED
			if (setting === 'enable') {
				state = this.conversation.sipEnabled === WEBINAR.SIP.DISABLED ? WEBINAR.SIP.ENABLED : WEBINAR.SIP.DISABLED
			} else if (setting === 'nopin') {
				state = this.conversation.sipEnabled === WEBINAR.SIP.ENABLED ? WEBINAR.SIP.ENABLED_NO_PIN : WEBINAR.SIP.ENABLED
			}

			try {
				await this.$store.dispatch('setSIPEnabled', {
					token: this.token,
					state,
				})
				if (this.conversation.sipEnabled === WEBINAR.SIP.ENABLED_NO_PIN) {
					showSuccess(t('spreed', 'SIP dial-in is now possible without PIN requirement'))
				} else if (this.conversation.sipEnabled === WEBINAR.SIP.ENABLED) {
					showSuccess(t('spreed', 'SIP dial-in is now enabled'))
				} else {
					showSuccess(t('spreed', 'SIP dial-in is now disabled'))
				}
			} catch (e) {
				// TODO check "precondition failed"
				if (!this.conversation.sipEnabled) {
					console.error('Error occurred when enabling SIP dial-in', e)
					showError(t('spreed', 'Error occurred when enabling SIP dial-in'))
				} else {
					console.error('Error occurred when disabling SIP dial-in', e)
					showError(t('spreed', 'Error occurred when disabling SIP dial-in'))
				}
			}
		},
	},
}
</script>
