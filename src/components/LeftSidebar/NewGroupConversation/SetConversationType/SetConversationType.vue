<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="conversation-type">
		<input id="checkbox"
			type="checkbox"
			class="checkbox"
			:checked="value"
			@change="handleInput">
		<label for="checkbox" class="conversation-type__label">{{ t('spreed', 'Allow guests to join via link') }}</label>
	</div>
</template>

<script>

export default {
	name: 'SetConversationType',
	// The value of the checkbox
	props: {
		value: {
			type: Boolean,
			required: true,
		},
	},
	methods: {
		/**
		 * Emits the input event with the checked bulean as a value
		 *
		 * @param {object} event The checkbox click event object.
		 */
		handleInput(event) {
			this.$emit('input', event.target.checked)
		},
	},

}

</script>

<style lang="scss" scoped>

.conversation-type {
	margin: 20px 0;
	&__hint {
		padding: 10px 0;
		color: var(--color-text-light)
	}
}
</style>
