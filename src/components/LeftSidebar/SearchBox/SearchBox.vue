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
			ref="searchConversations"
			v-model="localValue"
			class="app-navigation-search__input"
			type="text"
			:placeHolder="placeholderText">
	</form>
</template>

<script>
import { EventBus } from '../../../services/EventBus'

export default {
	name: 'SearchBox',
	props: {
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
	mounted() {
		this.focusInput()
		/**
		 * Listen to routeChange global events and focus on the
		 */
		EventBus.$on('routeChange', () => {
			this.focusInput()
		})
	},
	methods: {
		// Focus the input field of the current componnent.
		focusInput() {
			if (this.$route.name === 'root') {
				this.$refs.searchConversations.focus()
			}
		},
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
@import '../../../assets/variables.scss';

.app-navigation-search {
	flex: 1 0 auto;
	position: sticky;
	top: 0;
	background-color: var(--color-main-background);
	z-index: 1;
	display: flex;
	justify-content: center;
	&__input {
		align-self: center;
		width: 100%;
		margin: 0px;
	}
}
</style>
