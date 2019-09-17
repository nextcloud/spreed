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
	<div v-contenteditable:text="active"
		:placeHolder="placeholderText"
		class="new-message-form__input"
		@keydown.enter="handleKeydown" />
</template>

<script>

export default {
	name: 'AdvancedInput',
	props: {
		placeholderText: {
			type: String,
			default: 'Type something'
		},
		activeInput: {
			type: Boolean,
			default: true
		},
		value: {
			type: String,
			required: true
		}
	},
	data: function() {
		return {
			active: true,
			text: ''
		}
	},
	watch: {
		text(text) {
			this.$emit('update:value', text)
			this.$emit('input', text)
			this.$emit('change', text)
		},
		value(value) {
			this.text = value
		}
	},
	methods: {
		onBlur() {
			return 0
		},
		handleKeydown(event) {
			// TODO: add support for CTRL+ENTER new line
			if (!(event.shiftKey)) {
				event.preventDefault()
				this.$emit('submit', event)
			}
		}
	}
}
</script>

<style lang="scss" scoped>
//Support for the placehoder text in the div contenteditable
[contenteditable]:empty:before{
    content: attr(placeholder);
    display: block;
    color: gray;
}
</style>
