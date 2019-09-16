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
		class="new-message-form__input"
		@input="onInput" />
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
	watch: {
		value: {
			immediate: true,
			handler: (newValue) => {
				this.text = newValue
			}
		}
	},
	data: function() {
		return {
			active: true,
			text: ''
		}
	},
	methods: {
		onInput(event) {
			this.updateValue()
		},
		onBlur() {
			return 0
		},
		updateValue() {
			this.$emit('input', this.text)
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
