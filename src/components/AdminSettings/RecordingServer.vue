<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 - @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license AGPL-3.0-or-later
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
	<li class="recording-server">
		<NcTextField ref="recording_server"
			class="recording-server__textfield"
			name="recording_server"
			placeholder="https://recording.example.org"
			:value="server"
			:disabled="loading"
			:label="t('spreed', 'Recording backend URL')"
			@update:value="updateServer" />

		<NcCheckboxRadioSwitch :checked="verify"
			class="recording-server__checkbox"
			@update:checked="updateVerify">
			{{ t('spreed', 'Validate SSL certificate') }}
		</NcCheckboxRadioSwitch>

		<NcButton v-show="!loading"
			type="tertiary-no-background"
			:aria-label="t('spreed', 'Delete this server')"
			@click="removeServer">
			<template #icon>
				<Delete :size="20" />
			</template>
		</NcButton>

		<span v-if="server" class="test-connection">
			<NcLoadingIcon v-if="!checked" :size="20" />
			<AlertCircle v-else-if="errorMessage" :size="20" :fill-color="'#E9322D'" />
			<Check v-else :size="20" :fill-color="'#46BA61'" />
			{{ connectionState }}
		</span>
	</li>
</template>

<script>
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { getWelcomeMessage } from '../../services/recordingService.js'

export default {
	name: 'RecordingServer',

	components: {
		AlertCircle,
		Check,
		Delete,
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		NcTextField,
	},

	props: {
		server: {
			type: String,
			default: '',
			required: true,
		},
		verify: {
			type: Boolean,
			default: false,
			required: true,
		},
		index: {
			type: Number,
			default: -1,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['remove-server', 'update:server', 'update:verify'],

	data() {
		return {
			checked: false,
			errorMessage: '',
			versionFound: '',
		}
	},

	computed: {
		connectionState() {
			if (!this.checked) {
				return t('spreed', 'Status: Checking connection')
			}
			if (this.errorMessage) {
				return this.errorMessage
			}
			return t('spreed', 'OK: Running version: {version}', {
				version: this.versionFound,
			})
		},
	},

	watch: {
		loading(isLoading) {
			if (!isLoading) {
				this.checkServerVersion()
			}
		},
	},

	mounted() {
		if (this.server) {
			this.checkServerVersion()
		}
	},

	methods: {
		removeServer() {
			this.$emit('remove-server', this.index)
		},
		updateServer(value) {
			this.$emit('update:server', value)
		},
		updateVerify(checked) {
			this.$emit('update:verify', checked)
		},

		async checkServerVersion() {
			this.checked = false

			this.errorMessage = ''
			this.versionFound = ''

			try {
				const response = await getWelcomeMessage(this.index)
				this.checked = true
				this.versionFound = response.data.ocs.data.version
			} catch (exception) {
				this.checked = true
				const data = exception.response.data.ocs.data
				const error = data.error

				if (error === 'CAN_NOT_CONNECT') {
					this.errorMessage = t('spreed', 'Error: Cannot connect to server')
				} else if (error === 'IS_SIGNALING_SERVER') {
					this.errorMessage = t('spreed', 'Error: Server seems to be a Signaling server')
				} else if (error === 'JSON_INVALID') {
					this.errorMessage = t('spreed', 'Error: Server did not respond with proper JSON')
				} else if (error === 'CERTIFICATE_EXPIRED') {
					this.errorMessage = t('spreed', 'Error: Certificate expired')
				} else if (error) {
					this.errorMessage = t('spreed', 'Error: Server responded with: {error}', data)
				} else {
					this.errorMessage = t('spreed', 'Error: Unknown error occurred')
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.recording-server {
	display: flex;
	align-items: center;

	& &__textfield {
		width: 300px;
		flex-shrink: 0;
	}

	&__checkbox {
		margin: 0 18px;
	}
}

.test-connection {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	height: 44px;
}
</style>
