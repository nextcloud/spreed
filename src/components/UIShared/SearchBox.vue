<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcTextField ref="searchConversations"
		v-model="modelValue"
		:aria-label="placeholderText"
		:aria-describedby="ariaDescribedby"
		:placeholder="placeholderText"
		:show-trailing-button="isFocused"
		:trailing-button-label="cancelSearchLabel"
		class="search-box"
		label-outside
		@focus="handleFocus"
		@blur="handleBlur"
		@trailing-button-click="abortSearch"
		@keydown.esc="abortSearch">
		<template #icon>
			<IconMagnify :size="16" />
		</template>
	</NcTextField>
</template>

<script>
import IconMagnify from 'vue-material-design-icons/Magnify.vue'

import { t } from '@nextcloud/l10n'

import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'SearchBox',
	components: {
		IconMagnify,
		NcTextField,
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
		listRef: {
			type: Array,
			default: null,
		},

		/**
		 * Input's aria-describedby attribute
		 */
		ariaDescribedby: {
			type: String,
			default: undefined,
		},
	},

	expose: ['focus'],

	emits: ['update:value', 'update:is-focused', 'input', 'abort-search', 'blur', 'focus'],

	computed: {
		modelValue: {
			get() {
				return this.value
			},

			set(value) {
				this.updateValue(value)
			}
		},

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
		t,
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
			if (this.listRef?.length && this.listRef.some(list => list?.$el?.contains(event.relatedTarget))) {
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
		&:focus:not([disabled]), &:hover:not([disabled]), &:active:not([disabled]) {
			box-shadow: unset !important; // Remove the outer white border which is unnecessary here
		}
	}
}

</style>
