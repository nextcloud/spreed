<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="editable-text-field" class="editable-text-field">
		<NcRichText
			v-if="!editing"
			class="editable-text-field__output"
			dir="auto"
			:text="text"
			autolink
			:use-extended-markdown="useMarkdown" />
		<NcRichContenteditable
			v-else
			ref="richContenteditable"
			v-model="text"
			dir="auto"
			:auto-complete="() => {}"
			:maxlength="maxLength"
			:multiline="multiline"
			:contenteditable="!loading"
			:placeholder="placeholder"
			@submit="handleSubmitText"
			@keydown.esc="handleCancelEditing" />
		<template v-if="!loading">
			<template v-if="editing">
				<NcButton
					variant="tertiary"
					:aria-label="t('spreed', 'Cancel editing')"
					@click="handleCancelEditing">
					<template #icon>
						<IconClose :size="20" />
					</template>
				</NcButton>
				<NcButton
					variant="primary"
					:aria-label="t('spreed', 'Submit')"
					:disabled="!canSubmit"
					@click="handleSubmitText">
					<template #icon>
						<IconCheck :size="20" />
					</template>
				</NcButton>
				<div
					v-if="showCountDown"
					class="counter"
					tabindex="0"
					:title="countDownWarningText"
					:aria-label="countDownWarningText">
					<span class="counter__numeric">{{ charactersCountDown }}</span>
				</div>
			</template>
			<NcButton
				v-if="!editing && editable"
				variant="tertiary"
				class="editable-text-field__edit"
				:aria-label="editButtonAriaLabel"
				@click="handleEditText">
				<template #icon>
					<IconPencilOutline :size="20" />
				</template>
			</NcButton>
		</template>
		<div v-if="loading" class="icon-loading-small spinner" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import { parseSpecialSymbols } from '../../utils/textParse.ts'

export default {
	name: 'EditableTextField',
	components: {
		IconCheck,
		IconClose,
		IconPencilOutline,
		NcButton,
		NcRichContenteditable,
		NcRichText,
	},

	props: {
		/**
		 * The "outer" value of the text, coming from the store. Every time this changes,
		 * the text value in this component is overwritten.
		 */
		initialText: {
			type: String,
			default: '',
		},

		/**
		 * Shows or hides the editing buttons.
		 */
		editable: {
			type: Boolean,
			default: false,
		},

		/**
		 * Toggles the text editing state on and off.
		 */
		editing: {
			type: Boolean,
			default: false,
		},

		/**
		 * Placeholder for the contenteditable element.
		 */
		placeholder: {
			type: String,
			default: '',
		},

		/**
		 * Toggles the loading state on and off.
		 */
		loading: {
			type: Boolean,
			default: false,
		},

		/**
		 * Maximum text length in characters
		 */
		maxLength: {
			type: Number,
			default: 500,
		},

		editButtonAriaLabel: {
			type: String,
			required: true,
		},

		multiline: {
			type: Boolean,
			default: false,
		},

		useMarkdown: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:editing', 'submitText'],

	data() {
		return {
			text: this.initialText,
			overflows: null,
		}
	},

	computed: {
		canSubmit() {
			return this.charactersCount <= this.maxLength && this.text !== this.initialText
		},

		charactersCount() {
			return this.text.length
		},

		charactersCountDown() {
			return this.maxLength - this.charactersCount
		},

		showCountDown() {
			return this.charactersCount >= this.maxLength - 20
		},

		countDownWarningText() {
			return t('spreed', 'The text must be less than or equal to {maxLength} characters long. Your current text is {charactersCount} characters long.', {
				maxLength: this.maxLength,
				charactersCount: this.charactersCount,
			})
		},
	},

	watch: {
		// Each time the prop changes, reflect the changes in the value stored in this component
		initialText(newValue) {
			this.text = newValue
		},

		editing(newValue) {
			if (!newValue) {
				this.text = this.initialText
			}
		},
	},

	methods: {
		t,
		handleEditText() {
			this.$emit('update:editing', true)
			this.$nextTick(() => {
				// Focus and select rich text
				this.$refs.richContenteditable.focus()

				// TODO upstream: declare as select() method for NcRichContenteditable
				const range = document.createRange()
				range.selectNodeContents(this.$refs.richContenteditable.$refs.contenteditable)
				window.getSelection().removeAllRanges()
				window.getSelection().addRange(range)
			})
		},

		handleSubmitText() {
			if (!this.canSubmit) {
				return
			}

			// Parse special symbols
			this.text = parseSpecialSymbols(this.text)

			// Submit text
			this.$emit('submitText', this.text)
		},

		handleCancelEditing() {
			this.text = this.initialText
			this.$emit('update:editing', false)
			// Deselect all the text that's been selected in `handleEditText`
			window.getSelection().removeAllRanges()
		},
	},
}

</script>

<style lang="scss" scoped>
@import '../../assets/variables';
@import '../../assets/markdown';

.editable-text-field {
	display: flex;
	width: 100%;
	overflow: hidden;
	position: relative;
	min-height: var(--default-clickable-area);
	align-items: flex-end;

	&__edit {
		margin-inline-start: var(--default-clickable-area);
	}

	&__output {
		width: 100%;
		padding: 10px;
		line-height: var(--default-line-height) !important;
	}

	// Restyle NcRichContenteditable component from our library.
	:deep(.rich-contenteditable) {
		flex-grow: 1;
	}

	:deep(.rich-text--wrapper) {
		text-align: start;
		@include markdown;
	}
}

.spinner {
	width: var(--default-clickable-area);
	height: var(--default-clickable-area);
	margin-inline-start: var(--default-clickable-area);
	flex-shrink: 0;
}

.counter {
	background-color: var(--color-background-dark);
	height: var(--default-clickable-area);
	width: var(--default-clickable-area);
	border-radius: var(--border-radius-pill);
	position: absolute;
	top: 0;
	inset-inline-end: 0;
	display: flex;
	align-items: center;
	justify-content: center;

	&__numeric {
		direction: ltr;
	}
}
</style>
