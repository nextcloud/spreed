<!--
  - @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
  -
  - @author Vincent Petry <vincent@nextcloud.com>
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
			{{ t('spreed', 'Phone and SIP dial-in') }}
		</h4>

		<div>
			<NcCheckboxRadioSwitch :checked="hasSIPEnabled"
				type="switch"
				aria-describedby="sip_settings_hint"
				:disabled="isSipLoading"
				@update:checked="toggleSetting('enable')">
				{{ t('spreed', 'Enable phone and SIP dial-in') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div v-if="hasSIPEnabled">
			<NcCheckboxRadioSwitch :checked="noPinRequired"
				type="switch"
				:disabled="isSipLoading || !hasSIPEnabled"
				@update:checked="toggleSetting('nopin')">
				{{ t('spreed', 'Allow to dial-in without a PIN') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { WEBINAR } from '../../constants.js'

export default {
	name: 'SipSettings',

	components: {
		NcCheckboxRadioSwitch,
	},

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
