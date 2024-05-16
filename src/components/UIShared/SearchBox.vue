<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcTextField ref="searchConversations"
		:model-value="value"
		:aria-label="placeholderText"
		:placeholder="placeholderText"
		:show-trailing-button="isFocused"
		class="search-box"
		label-outside
		pill
		@focus="handleFocus"
		@blur="handleBlur"
		@update:model-value="updateValue"
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
		isSearching() {
			return this.value !== ''
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
					this.setTrailingTabIndex()
				})
			} else {
				this.getTrailingButton()?.removeEventListener('keydown', this.handleTrailingKeyDown)
			}
		},

		isSearching() {
			this.setTrailingTabIndex()
		},
	},

	methods: {
		updateValue(value) {
			this.$emit('update:value', value)
			this.$emit('input', value)
		},

		/**
		 * Focuses the input
		 */
		focus() {
			this.$refs.searchConversations.focus()
		},

		getTrailingButton() {
			return this.$refs.searchConversations.$el.querySelector('.input-field__trailing-button')
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
		},

		handleFocus(event) {
			this.$emit('update:is-focused', true)
			this.$emit('focus', event)
		},

		handleBlur(event) {
			// Blur triggered by clicking on the trailing button
			if (event.relatedTarget === this.getTrailingButton()) {
				event.preventDefault()
				event.relatedTarget.addEventListener('blur', (trailingEvent) => {
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
			if (!this.isSearching) {
				this.$emit('update:is-focused', false)
			}
		},

		setTrailingTabIndex() {
			if (this.isSearching) {
				this.getTrailingButton()?.removeAttribute('tabindex')
			} else {
				this.getTrailingButton()?.setAttribute('tabindex', '-1')
			}
		}

	},
}
</script>

<style lang="scss" scoped>
.search-box {
	:deep(.input-field__input) {
		&:focus, &:hover {
			box-shadow: unset !important; // Remove the outer white border which is unnecessary here
		}
	}
}

</style>
