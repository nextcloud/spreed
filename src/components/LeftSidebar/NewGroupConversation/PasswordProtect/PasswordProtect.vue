<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
		type="password"
		:value="value"
		class="password-protect"
		:placeholder="t('spreed', 'Choose a password')"
		@input="handleInput">
</template>

<script>

export default {
	name: 'PasswordProtect',

	props: {
		value: {
			type: String,
			required: true,
		},
	},

	methods: {
		handleInput(event) {
			this.$emit('input', event.target.value)
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
}
</style>
