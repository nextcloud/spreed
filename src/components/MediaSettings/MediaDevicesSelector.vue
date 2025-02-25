<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="media-devices-selector">
		<component :is="deviceIcon"
			class="media-devices-selector__icon"
			title=""
			:size="16" />

		<NcSelect v-model="deviceSelectedOption"
			:input-id="deviceSelectorId"
			:options="deviceOptions"
			label="label"
			:aria-label-combobox="t('spreed', 'Select a device')"
			:clearable="false"
			:placeholder="deviceSelectorPlaceholder"
			:disabled="!enabled || !deviceOptionsAvailable" />

		<NcButton type="tertiary"
			:title="t('spreed', 'Refresh devices list')"
			:aria-lebel="t('spreed', 'Refresh devices list')"
			@click="$emit('refresh')">
			<IconRefresh :size="20" />
		</NcButton>
	</div>
</template>

<script>
import IconMicrophone from 'vue-material-design-icons/Microphone.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'
import IconVolumeHigh from 'vue-material-design-icons/VolumeHigh.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

export default {

	name: 'MediaDevicesSelector',

	components: {
		NcButton,
		NcSelect,
		IconMicrophone,
		IconRefresh,
		IconVideo,
		IconVolumeHigh,
	},

	props: {
		kind: {
			validator(value) {
				return ['audioinput', 'audiooutput', 'videoinput'].includes(value)
			},
			required: true,
		},
		devices: {
			type: Array,
			required: true,
		},
		deviceId: {
			type: String,
			default: undefined,
		},
		enabled: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['refresh', 'update:deviceId'],

	data() {
		return {
			deviceSelectedOption: null,
		}
	},

	computed: {
		deviceSelectorId() {
			return 'device-selector-' + this.kind
		},

		deviceIcon() {
			switch (this.kind) {
			case 'audioinput': return IconMicrophone
			case 'audiooutput': return IconVolumeHigh
			case 'videoinput': return IconVideo
			default: return null
			}
		},

		deviceOptionsAvailable() {
			return this.deviceOptions.length > 1
		},

		deviceSelectorPlaceholder() {
			switch (this.kind) {
			case 'audioinput': return this.audioInputSelectorPlaceholder
			case 'audiooutput': return this.audioOutputSelectorPlaceholder
			case 'videoinput': return this.videoInputSelectorPlaceholder
			default: return null
			}
		},

		audioInputSelectorPlaceholder() {
			if (!this.deviceOptionsAvailable) {
				return t('spreed', 'No microphone available')
			}

			return t('spreed', 'Select microphone')
		},

		audioOutputSelectorPlaceholder() {
			if (!this.deviceOptionsAvailable) {
				return t('spreed', 'No speaker available')
			}

			return t('spreed', 'Select speaker')
		},

		videoInputSelectorPlaceholder() {
			if (!this.deviceOptionsAvailable) {
				return t('spreed', 'No camera available')
			}

			return t('spreed', 'Select camera')
		},

		deviceOptions() {
			const options = this.devices.filter(device => device.kind === this.kind).map(device => {
				return {
					id: device.deviceId,
					label: device.label ? device.label : device.fallbackLabel,
				}
			})

			options.push({
				id: null,
				label: t('spreed', 'None'),
			})

			return options
		},

		deviceSelectedOptionFromDeviceId() {
			return this.deviceOptions.find(option => option.id === this.deviceId)
		},
	},

	watch: {
		// The watcher needs to be set as "immediate" to ensure that
		// "deviceSelectedOption" will be set when mounted.
		deviceSelectedOptionFromDeviceId: {
			handler(deviceSelectedOptionFromDeviceId) {
				this.deviceSelectedOption = deviceSelectedOptionFromDeviceId
			},
			immediate: true,
		},

		// The watcher should not be set as "immediate" to prevent
		// "update:deviceId" from being emitted when mounted with the same value
		// initially passed to the component.
		deviceSelectedOption(deviceSelectedOption, previousSelectedOption) {
			// The deviceSelectedOption may be the same as before yet a change
			// could be triggered if media permissions are granted, which would
			// update the label.
			if (deviceSelectedOption && previousSelectedOption && deviceSelectedOption.id === previousSelectedOption.id) {
				return
			}

			// The previous selected option changed due to the device being
			// disconnected, so ignore it as it was not explicitly changed by
			// the user.
			if (previousSelectedOption && previousSelectedOption.id && !this.deviceOptions.find(option => option.id === previousSelectedOption.id)) {
				return
			}

			// Ignore device change on initial loading of the settings dialog.
			if (typeof previousSelectedOption?.id === 'undefined') {
				return
			}

			if (deviceSelectedOption && deviceSelectedOption.id === null) {
				this.$emit('update:deviceId', null)
				return
			}
			this.$emit('update:deviceId', deviceSelectedOption ? deviceSelectedOption.id : undefined)
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.media-devices-selector {
	display: flex;
	gap: var(--default-grid-baseline);
	margin: calc(3 * var(--default-grid-baseline)) 0;

	&__icon {
		display: flex;
		justify-content: center;
		align-items: center;
		width: var(--default-clickable-area);
		flex-shrink: 0;
	}

	:deep(.v-select.select) {
		width: 100%;
	}
}
</style>
