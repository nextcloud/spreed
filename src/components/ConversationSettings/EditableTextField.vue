<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div ref="editable-text-field"
		:key="forceReRenderKey"
		class="editable-text-field">
		<NcRichContenteditable ref="richContenteditable"
			:value.sync="text"
			:auto-complete="()=>{}"
			:maxlength="maxLength"
			:contenteditable="editing && !loading"
			:placeholder="placeholder"
			@submit="handleSubmitText"
			@keydown.esc="handleCancelEditing" />
		<template v-if="!loading">
			<template v-if="editing">
				<NcButton type="tertiary"
					:aria-label="t('spreed', 'Cancel editing')"
					@click="handleCancelEditing">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
				<NcButton type="primary"
					:aria-label="t('spreed', 'Submit')"
					:disabled="!canSubmit"
					@click="handleSubmitText">
					<template #icon>
						<Check :size="20" />
					</template>
				</NcButton>
				<div v-if="showCountDown"
					v-tooltip.auto="countDownWarningText"
					class="counter"
					tabindex="0"
					aria-label="countDownWarningText">
					<span>{{ charactersCountDown }}</span>
				</div>
			</template>
			<NcButton v-if="!editing && editable"
				type="tertiary"
				class="editable-text-field__edit"
				:aria-label="editButtonAriaLabel"
				@click="handleEditText">
				<template #icon>
					<Pencil :size="20" />
				</template>
			</NcButton>
		</template>
		<div v-if="loading" class="icon-loading-small spinner" />
	</div>
</template>

<script>
import Check from 'vue-material-design-icons/Check.vue'
import Close from 'vue-material-design-icons/Close.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

export default {
	name: 'EditableTextField',
	components: {
		Pencil,
		Check,
		Close,
		NcRichContenteditable,
		NcButton,
	},

	directives: {
		Tooltip,
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
	},

	emits: ['update:editing', 'submit-text'],

	data() {
		return {
			text: this.initialText,
			forceReRenderKey: 0,
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
		handleEditText() {
			this.$emit('update:editing', true)
			this.$nextTick(() => {
				// Focus and select rich text
				this.$refs.richContenteditable.focus()
				document.execCommand('selectAll', false, null)
			})
		},

		handleSubmitText() {
			if (!this.canSubmit) {
				return
			}
			// Remove leading/trailing whitespaces.
			// FIXME: remove after issue is resolved: https://github.com/nextcloud/nextcloud-vue/issues/3264
			const temp = document.createElement('textarea')
			temp.innerHTML = this.text
			this.text = temp.value.replace(/\r\n|\n|\r/gm, '\n').trim()

			// Submit text
			this.$emit('submit-text', this.text)
			/**
			 * Change the NcRichContenteditable key in order to trigger a re-render
			 * without this all the trimmed new lines and whitespaces would
			 * still be present in the contenteditable element.
			 */
			this.forceReRenderKey += 1
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

.editable-text-field {
	display: flex;
	width: 100%;
	overflow: hidden;
	position: relative;
	min-height: $clickable-area;
	align-items: flex-end;

	&__edit {
		margin-left: var(--default-clickable-area);
	}
}

.spinner {
	width: $clickable-area;
	height: $clickable-area;
	margin: 0 0 0 44px;
}

.counter {
	background-color: var(--color-background-dark);
	height: 44px;
	width: 44px;
	border-radius: var(--border-radius-pill);
	position: absolute;
	top: 0;
	right: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

// Restyle NcRichContenteditable component from our library.
:deep(.rich-contenteditable__input) {
	align-self: flex-start;
	min-height: var(--default-line-height);
	max-height: unset;
	margin: 12px 0 4px 0;
	padding: 0 0 4px 0;
	overflow: visible;
	width: 100% !important;
	background-color: transparent;
	transition: $fade-transition;
	&::before {
		position: relative;
	}
	&[contenteditable='false'] {
		background-color: transparent;
		color: var(--color-main-text);
		border-color: transparent;
		opacity: 1;
		border-radius: 0;
		user-select: text;
	}
}

</style>
