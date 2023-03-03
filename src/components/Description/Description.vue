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
	<div ref="description"
		:key="forceReRenderKey"
		class="description">
		<NcRichContentEditable ref="contenteditable"
			:value.sync="descriptionText"
			:auto-complete="()=>{}"
			:maxlength="maxLength"
			:contenteditable="editing && !loading"
			:placeholder="placeholder"
			@submit="handleSubmitDescription"
			@keydown.esc="handleCancelEditing" />
		<template v-if="!loading">
			<template v-if="editing">
				<NcButton type="tertiary"
					:aria-label="t('spreed', 'Cancel editing description')"
					@click="handleCancelEditing">
					<template #icon>
						<Close :size="20" />
					</template>
				</NcButton>
				<NcButton type="primary"
					:aria-label="t('spreed', 'Submit conversation description')"
					:disabled="!canSubmit"
					@click="handleSubmitDescription">
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
				class="description__edit"
				:aria-label="t('spreed', 'Edit conversation description')"
				@click="handleEditDescription">
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
import NcRichContentEditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

export default {
	name: 'Description',
	components: {
		Pencil,
		Check,
		Close,
		NcRichContentEditable,
		NcButton,
	},

	directives: {
		Tooltip,
	},

	props: {
		/**
		 * The description (An editable paragraph just above the sidebar tabs)
		 */
		descriptionTitle: {
			type: String,
			default: t('spreed', 'Description'),
		},

		/**
		 * A paragraph below the title.
		 */
		description: {
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
		 * Toggles the description editing state on and off.
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
		 * Maximum description length in characters
		 */
		maxLength: {
			type: Number,
			default: 500,
		},
	},

	data() {
		return {
			descriptionText: this.description,
			forceReRenderKey: 0,
			overflows: null,
		}
	},

	computed: {

		canSubmit() {
			return this.charactersCount <= this.maxLength && this.descriptionText !== this.description
		},

		charactersCount() {
			return this.descriptionText.length
		},

		charactersCountDown() {
			return this.maxLength - this.charactersCount
		},

		showCountDown() {
			return this.charactersCount >= this.maxLength - 20
		},

		countDownWarningText() {
			return t('spreed', 'The description must be less than or equal to {maxLength} characters long. Your current text is {charactersCount} characters long.', {
				maxLength: this.maxLength,
				charactersCount: this.charactersCount,
			})
		},
	},

	watch: {
		// Each time the prop changes, reflect the changes in the value stored in this component
		description(newValue) {
			this.descriptionText = newValue
		},
		editing(newValue) {
			if (!newValue) {
				this.descriptionText = this.description
			}
		},
	},

	methods: {
		handleEditDescription() {
			const contenteditable = this.$refs.contenteditable.$refs.contenteditable
			this.$emit('update:editing', true)
			this.$nextTick(() => {
				// Focus and select the text in the description
				contenteditable.focus()
				document.execCommand('selectAll', false, null)
			})
		},

		handleSubmitDescription() {
			if (!this.canSubmit) {
				return
			}
			// Remove leading/trailing whitespaces.
			this.descriptionText = this.descriptionText.replace(/\r\n|\n|\r/gm, '\n').trim()
			// Submit description
			this.$emit('submit-description', this.descriptionText)
			/**
			 * Change the richcontenteditable key in order to trigger a re-render
			 * without this all the trimmed new lines and whitespaces would
			 * still be present in the contenteditable element.
			 */
			this.forceReRenderKey += 1
		},

		handleCancelEditing() {
			this.descriptionText = this.description
			this.$emit('update:editing', false)
			// Deselect all the text that's been selected in `handleEditDescription`
			window.getSelection().removeAllRanges()
		},

		checkOverflow() {
			const descriptionHeight = this.$refs.description.clientHeight
			const contenteditableHeight = this.$refs.contenteditable.$refs.contenteditable.scrollHeight
			this.overflows = descriptionHeight < contenteditableHeight
		},
	},
}

</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.description {
	display: flex;
	width: 100%;
	overflow: hidden;
	position: relative;
	min-height: $clickable-area;
	align-items: flex-end;

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 44px;
	}
	&__title {
		color: var(--color-primary);
		font-weight: bold;
		font-size: var(--default-font-size);
		line-height: var(--default-line-height);
	}

	&__edit {
		margin-left: 44px;
	}

	&__action {
		margin: 0 0 4px 4px;
	}
}

.spinner {
	width: $clickable-area;
	height: $clickable-area;
	margin: 0 0 4px 44px;
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

// Restyle richContentEditable component from our library.
::v-deep .rich-contenteditable__input {
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
	}
}

</style>
