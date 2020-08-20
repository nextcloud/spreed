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
		<h3 :for="deviceSelectorId">
			{{ deviceSelectorLabel }}
		</h3>
		<Multiselect :id="deviceSelectorId"
			v-model="deviceSelectedOption"
			:options="deviceOptions"
			track-by="id"
			label="label"
			:allow-empty="false"
			:placeholder="deviceSelectorPlaceholder"
			:disabled="!enabled || !deviceOptionsAvailable" />
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

export default {

	name: 'MediaDevicesSelector',

	components: {
		Multiselect,
	},

	props: {
		kind: {
			validator(value) {
				return ['audioinput', 'videoinput'].indexOf(value) !== -1
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

	data() {
		return {
			deviceSelectedOption: null,
		}
	},

	computed: {
		deviceSelectorId() {
			return 'device-selector-' + this.kind
		},

		deviceSelectorLabel() {
			if (this.kind === 'audioinput') {
				return t('spreed', 'Microphone:')
			}

			if (this.kind === 'videoinput') {
				return t('spreed', 'Camera:')
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
		deviceSelectedOptionFromDeviceId(deviceSelectedOptionFromDeviceId) {
			this.deviceSelectedOption = deviceSelectedOptionFromDeviceId
		},

		deviceSelectedOption(deviceSelectedOption) {
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
	margin-top: 28px;
	margin-bottom: 8px;

	.multiselect {
		width: 100%;
	}
}
</style>
