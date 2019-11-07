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
	<form
		class="app-navigation-search"
		@submit.prevent="handleSubmit">
		<input
			v-model="localValue"
			class="app-navigation-search__input"
			type="text"
			:autofocus="autoFocused"
			:placeHolder="placeholderText">
	</form>
</template>

<script>

export default {
	name: 'SearchBox',
	props: {
		/**
		 * Refers to the focused state of the input search box when loading the page.
		 */
		autoFocused: {
			type: Boolean,
			default: true,
		},
		/**
		 * The placeholder for the input field
		 */
		placeholderText: {
			type: String,
			default: t('spreed', 'Search conversations or contacts'),
		},
		/**
		 * The value of the input field, when receiving it as a prop the localValue
		 * is updated.
		 */
		value: {
			type: String,
			required: true,
		},
	},
	data: function() {
		return {
			localValue: '',
		}
	},
	watch: {
		localValue(localValue) {
			this.$emit('update:value', localValue)
			this.$emit('input', localValue)
		},
		value(value) {
			this.localValue = value
		},
	},
	methods: {
		/**
		 * When the form is submitted we send this event up in order to allow for example
		 * to select the first search result and trigger a route change in the main view.
		 */
		handleSubmit() {
			this.$emit('submit')
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables.scss';

.app-navigation-search {
	height: $top-bar-height !important;
	position: sticky;
	top: 0;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border-dark);
	z-index: 1;
	display: flex;
	justify-content: center;
	&__input {
		align-self: center;
		width: $navigation-width - 20px;
	}
}
</style>
