<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<input ref="password"
		v-observe-visibility="visibilityChanged"
		v-tooltip.bottom="reason"
		type="password"
		autocomplete="new-password"
		:value="value"
		class="password-protect"
		:class="{'weak-password': validPassword === false}"
		:placeholder="t('spreed', 'Choose a password')"
		:aria-label="reason"
		@input="handleInput">
</template>

<script>

import { validatePassword } from '../../../../services/conversationsService.js'
import debounce from 'debounce'

export default {
	name: 'PasswordProtect',

	props: {
		value: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			validPassword: null,
			reason: '',
		}
	},

	methods: {
		handleInput(event) {
			this.$emit('input', event.target.value)
			if (event.target.value !== '') {
				this.debounceValidatePassword()
			}
		},

		debounceValidatePassword: debounce(function() {
			this.validatePassword()
		}, 250),

		async validatePassword() {
			try {
				const response = await validatePassword(this.value)
				this.validPassword = response.data.ocs.data.passed
				if (!this.validPassword) {
					this.reason = response.data.ocs.data.reason
				} else {
					this.reason = ''
				}
			} catch (e) {
				console.debug('Password policy app seems not enabled')
				this.validPassword = null
				this.reason = ''
			}
		},

		visibilityChanged(isVisible) {
			if (isVisible) {
				// Focus the input field of the current component.
				this.$refs.password.focus()
			}
		},
	},

}

</script>

<style lang="scss" scoped>

.password-protect {
	width: calc(100% - 18px);
	margin-left: 18px;

	&.weak-password {
		background-color: var(--color-error) !important;
		border-color: var(--color-error) !important;
		color: #fff !important;
	}
}
</style>
