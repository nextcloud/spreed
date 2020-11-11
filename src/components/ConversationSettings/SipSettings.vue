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
			{{ t('spreed', 'Allow participants to join from a phone.') }}
		</h3>
		<ActionCheckbox
			:checked="hasSIPEnabled"
			:disabled="isSipLoading"
			@change="toggleSIPEnabled">
			{{ t('spreed', 'Enable SIP dial-in') }}
		</ActionCheckbox>
	</ul>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { WEBINAR } from '../../constants'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'

export default {
	name: 'SipSettings',

	components: {
		ActionCheckbox,
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
			return this.conversation.sipEnabled === WEBINAR.SIP.ENABLED
		},
	},

	methods: {
		async toggleSIPEnabled(checked) {
			try {
				await this.$store.dispatch('setSIPEnabled', {
					token: this.token,
					state: checked ? WEBINAR.SIP.ENABLED : WEBINAR.SIP.DISABLED,
				})
				if (checked) {
					showSuccess(t('spreed', 'SIP dial-in is now enabled'))
				} else {
					showSuccess(t('spreed', 'SIP dial-in is now disabled'))
				}
			} catch (e) {
				// TODO check "precondition failed"
				if (checked) {
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
<style lang="scss" scoped>
.app-settings-section__hint {
	color: var(--color-text-lighter);
	padding: 8px 0;
}
</style>
