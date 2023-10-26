<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcTextField ref="searchConversations"
		:value="value"
		:label="placeholderText"
		:show-trailing-button="isFocused"
		class="search-box"
		trailing-button-icon="close"
		v-on="listeners"
		@update:value="updateValue"
		@trailing-button-click="abortSearch"
		@keydown.esc="abortSearch">
		<Magnify :size="16" />
	</NcTextField>
</template>

<script>
import Magnify from 'vue-material-design-icons/Magnify.vue'

import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'SearchBox',
	components: {
		NcTextField,
		Magnify,
	},
	props: {
		/**
		 * The placeholder for the input field
		 */
		placeholderText: {
			type: String,
			default: t('spreed', 'Search …'),
		},
		/**
		 * The value of the input field.
		 */
		value: {
			type: String,
			required: true,
		},
		/**
		 * If true, this component displays an 'x' button to abort the search
		 */
		isFocused: {
			type: Boolean,
			required: true,
		},
		/**
		 * Conversations list reference for handling click trigger
		 */
		 list: {
			type: HTMLElement,
			default: null,
		},
	},

	expose: ['focus'],

	emits: ['update:value', 'update:is-focused', 'input', 'abort-search', 'blur', 'focus'],

	computed: {
		listeners() {
			return Object.assign({}, this.$listeners, {
				focus: this.handleFocus,
				blur: this.handleBlur,
			})
		},

		cancelSearchLabel() {
			return t('spreed', 'Cancel search')
		},
	},

	watch: {
		isFocused(value) {
			if (value) {
				this.$nextTick(() => {
					this.getTrailingButton()?.addEventListener('keydown', this.handleTrailingKeyDown)
				})
			} else {
				this.getTrailingButton()?.removeEventListener('keydown', this.handleTrailingKeyDown)
			}
		},
	},

	methods: {
		updateValue(value) {
			this.$emit('update:value', value)
			this.$emit('input', value)
		},
		// Focuses the input.
		focus() {
			this.$refs.searchConversations.focus()
		},

		getTrailingButton() {
			return this.$refs.searchConversations.$el.querySelector('.input-field__clear-button')
		},

		handleTrailingKeyDown(event) {
			if (event.key === 'Enter') {
				event.stopPropagation()
				this.abortSearch()
			}
		},

		/**
		 * Emits the abort-search event and blurs the input
		 */
		abortSearch() {
			this.updateValue('')
			this.$emit('update:is-focused', false)
			this.$emit('abort-search')

			document.activeElement.blur()
		},

		handleFocus(event) {
			this.$emit('update:is-focused', true)
			this.$emit('focus', event)
		},

		handleBlur(event) {
			// Blur triggered by clicking on the trailing button
			if (event.relatedTarget?.classList.contains('input-field__clear-button')) {
				event.preventDefault()
				this.getTrailingButton()?.addEventListener('blur', (trailingEvent) => {
					this.handleBlur(trailingEvent)
				})
				return
			}
			// Blur triggered by clicking on a conversation item
			if (this.list?.contains(event.relatedTarget)) {
				return
			}
			 // Blur in other cases
			this.$emit('blur', event)
			if (this.value === '') {
				this.$emit('update:is-focused', false)
			}
		},

	},
}
</script>

<style lang="scss" scoped>
.search-box {
	:deep(.input-field__input) {
		border-radius: var(--border-radius-pill);
	}

  :deep(.input-field__clear-button) {
    border-radius: var(--border-radius-pill) !important;
  }
}

</style>
