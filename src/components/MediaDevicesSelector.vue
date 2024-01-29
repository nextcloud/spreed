<!--
  - @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="media-devices-selector">
		<div class="media-devices-selector__icon">
			<Microphone v-if="deviceIcon === 'microphone'"
				title=""
				:size="16" />
			<VideoIcon v-if="deviceIcon === 'camera'"
				title=""
				:size="16" />
		</div>

		<NcSelect v-model="deviceSelectedOption"
			:input-id="deviceSelectorId"
			:options="deviceOptions"
			label="label"
			:aria-label-combobox="t('spreed', 'Select a device')"
			:clearable="false"
			:placeholder="deviceSelectorPlaceholder"
			:disabled="!enabled || !deviceOptionsAvailable" />
	</div>
</template>

<script>
import Microphone from 'vue-material-design-icons/Microphone.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

export default {

	name: 'MediaDevicesSelector',

	components: {
		NcSelect,
		Microphone,
		VideoIcon,
	},

	props: {
		kind: {
			validator(value) {
				return ['audioinput', 'videoinput'].includes(value)
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

	emits: ['update:deviceId'],

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
			if (this.kind === 'audioinput') {
				return 'microphone'
			}

			if (this.kind === 'videoinput') {
				return 'camera'
			}

			return null
		},

		deviceOptionsAvailable() {
			return this.deviceOptions.length > 1
		},

		deviceSelectorPlaceholder() {
			if (this.kind === 'audioinput') {
				return this.audioInputSelectorPlaceholder
			}

			if (this.kind === 'videoinput') {
				return this.videoInputSelectorPlaceholder
			}

			return null
		},

		audioInputSelectorPlaceholder() {
			if (!this.deviceOptionsAvailable) {
				return t('spreed', 'No microphone available')
			}

			return t('spreed', 'Select microphone')
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
}
</script>

<style lang="scss" scoped>
.media-devices-selector {
	display: flex;
	margin: 16px 0;
	&__icon {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		width: 36px;
	}
	&__heading {
		font-weight: bold;
	}

	:deep(.v-select.select) {
		width: 100%;
	}
}
</style>
