<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="recording-server">
		<NcTextField ref="recording_server"
			class="recording-server__textfield"
			name="recording_server"
			placeholder="https://recording.example.org"
			:model-value="server"
			:disabled="loading"
			:label="t('spreed', 'Recording backend URL')"
			@update:modelValue="updateServer" />

		<NcCheckboxRadioSwitch :model-value="verify"
			class="recording-server__checkbox"
			@update:modelValue="updateVerify">
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
