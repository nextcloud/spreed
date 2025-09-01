<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcPopover
		id="dial-popover"
		ref="popover"
		popover-base-class="dial-popover"
		:container="container"
		@show="prefillCode">
		<template #trigger>
			<NcButton
				:disabled="disabled"
				:aria-label="t('spreed', 'Open dialpad')"
				:title="t('spreed', 'Open dialpad')">
				<template #icon>
					<IconDialpad :size="20" />
				</template>
			</NcButton>
		</template>

		<div
			ref="panel"
			class="dial-panel"
			tabindex="0"
			@keydown.capture="handleKeyDown">
			<NcSelect
				v-if="!dialing"
				ref="regionSelect"
				v-model="region"
				class="dial-panel__select"
				:options="options"
				:append-to-body="false"
				:clearable="false"
				:aria-label-combobox="t('spreed', 'Select a region')"
				:placeholder="t('spreed', 'Select a region')"
				label="dial_and_name"
				@option:selected="dialCode">
				<template #option="{ dial_code, name }">
					<span class="dial-panel__select-option">
						<b>{{ dial_code }}</b> <em>{{ name }}</em>
					</span>
				</template>
				<template #selected-option="{ dial_code, name }">
					<span class="dial-panel__select-option">
						<b>{{ dial_code }}</b> <em>{{ name }}</em>
					</span>
				</template>
			</NcSelect>

			<NcButton
				v-for="button in buttons"
				:key="button.value"
				class="dial-panel__button"
				@click="handlePad(button.value)">
				{{ button.label }}
			</NcButton>

			<NcButton
				v-if="!dialing"
				class="dial-panel__button"
				variant="tertiary"
				:aria-label="t('spreed', 'Delete')"
				@click="handleBackspace">
				<template #icon>
					<IconBackspaceOutline :size="20" />
				</template>
			</NcButton>
		</div>
	</NcPopover>
</template>

<script>
import { getLocale, t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconBackspaceOutline from 'vue-material-design-icons/BackspaceOutline.vue'
import IconDialpad from 'vue-material-design-icons/Dialpad.vue'
import { regionCodes } from '../../regionCodes.js'

const allowedKeys = /^[0-9+\-()*#\s]+$/

const buttonsDigits = [
	{ value: '1', label: '1' },
	{ value: '2', label: '2' },
	{ value: '3', label: '3' },
	{ value: '4', label: '4' },
	{ value: '5', label: '5' },
	{ value: '6', label: '6' },
	{ value: '7', label: '7' },
	{ value: '8', label: '8' },
	{ value: '9', label: '9' },
]

const buttonsNumberOption = [
	{ value: '+', label: '+' },
	{ value: '0', label: '0' },
]

const buttonsDialOption = [
	{ value: '*', label: '*' },
	{ value: '0', label: '0' },
	{ value: '#', label: '#' },
]

export default {
	name: 'DialpadPanel',

	components: {
		IconBackspaceOutline,
		IconDialpad,
		NcButton,
		NcPopover,
		NcSelect,
	},

	props: {
		/**
		 * The conversation token of the message to be replied to.
		 */
		token: {
			type: String,
			default: '',
		},

		/**
		 * The conversation token of the message to be replied to.
		 */
		value: {
			type: String,
			default: '',
		},

		/**
		 * The CSS selector to attach popover to.
		 */
		container: {
			type: String,
			default: '#dial-popover',
		},

		/**
		 * Whether special symbols * and # will be used to dial
		 */
		dialing: {
			type: Boolean,
			default: false,
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['dialType', 'update:value', 'submit'],

	setup(props) {
		const options = Object.values(regionCodes).map((region) => ({ ...region, dial_and_name: region.dial_code + ' ' + region.name }))

		const buttons = props.dialing
			? buttonsDigits.concat(buttonsDialOption)
			: buttonsDigits.concat(buttonsNumberOption)

		return {
			buttons,
			options,
		}
	},

	data() {
		return {
			region: null,
		}
	},

	methods: {
		t,
		handlePad(value) {
			if (this.dialing) {
				this.$emit('dialType', value)
			} else {
				this.$emit('update:value', this.value + value)
			}
		},

		dialCode(value) {
			this.$emit('update:value', value.dial_code)

			this.$nextTick(() => {
				this.$refs.panel?.focus()
			})
		},

		prefillCode() {
			if (this.dialing) {
				return
			}
			// Attempt to parse user's defined locale and return dial code based on it
			const locale = getLocale().toUpperCase()
			this.region = regionCodes[locale]
				|| regionCodes[locale.split('_').pop()]
				|| regionCodes[locale.split('_').shift()]

			// Pre-fill input if the value
			if (this.region && this.value === '') {
				this.dialCode(this.region)
			}
		},

		handleKeyDown(event) {
			if (!this.dialing && this.$refs.regionSelect.$el.contains(event.target)) {
				// Ignore events from NcSelect
				return
			}
			if (allowedKeys.test(event.key)) {
				this.handlePad(event.key)
			} else if (event.key === 'Backspace') {
				this.handleBackspace()
			} else if (event.key === 'Enter') {
				this.$emit('submit', event)
				this.$refs.popover.$refs.popover.hide()
			}
		},

		handleBackspace() {
			this.$emit('update:value', this.value.slice(0, -1))
		},
	},
}
</script>

<style lang="scss">
.dial-popover .v-popper__inner {
	overflow: visible !important;
}
</style>

<style lang="scss" scoped>
.dial-panel {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	grid-template-rows: repeat(4, 1fr);
	gap: calc(var(--default-grid-baseline) * 2);
	padding: calc(var(--default-grid-baseline) * 3);
	background-color: var(--color-main-background);
	border-radius: var(--border-radius-large);

	.dial-panel__button {
		width: calc(var(--default-clickable-area) * 1.5) !important;
		border-radius: var(--border-radius-large) !important;
	}

	.dial-panel__select {
		grid-column: span 3;
		min-width: calc(var(--default-clickable-area) * 4.5 + var(--default-grid-baseline) * 4);
		max-width: calc(var(--default-clickable-area) * 4.5 + var(--default-grid-baseline) * 4);

		&-option {
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;
		}

		// To not ellipsize results too early
		:deep(.vs__selected, .vs__selected + .vs__search) {
			padding: 0;
		}
	}
}
</style>
