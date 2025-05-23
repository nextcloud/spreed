<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="recording-server">
		<NcTextField ref="recording_server"
			v-model="recordingServer"
			class="recording-server__textfield"
			name="recording_server"
			placeholder="https://recording.example.org"
			:disabled="loading"
			:label="t('spreed', 'Recording backend URL')" />

		<NcCheckboxRadioSwitch :model-value="verify"
			class="recording-server__checkbox"
			@update:model-value="updateVerify">
			{{ t('spreed', 'Validate SSL certificate') }}
		</NcCheckboxRadioSwitch>

		<NcButton v-show="!loading"
			type="tertiary"
			:title="t('spreed', 'Delete this server')"
			:aria-label="t('spreed', 'Delete this server')"
			@click="removeServer">
			<template #icon>
				<IconDelete :size="20" />
			</template>
		</NcButton>

		<span v-if="server" class="test-connection">
			<NcLoadingIcon v-if="!checked" :size="20" />
			<IconAlertCircle v-else-if="errorMessage" :size="20" fill-color="var(--color-error)" />
			<IconCheck v-else :size="20" fill-color="var(--color-success)" />
			{{ connectionState }}
		</span>

		<NcButton v-if="server && checked"
			type="tertiary"
			:title="t('spreed', 'Test this server')"
			:aria-label="t('spreed', 'Test this server')"
			@click="checkServerVersion">
			<template #icon>
				<IconReload :size="20" />
			</template>
		</NcButton>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconAlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import { getWelcomeMessage } from '../../services/recordingService.js'

export default {
	name: 'RecordingServer',

	components: {
		IconAlertCircle,
		IconCheck,
		IconDelete,
		IconReload,
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

		recordingServer: {
			get() {
				return this.server
			},

			set(value) {
				this.$emit('update:server', value)
			},
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
		t,
		removeServer() {
			this.$emit('remove-server', this.index)
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
				} else if (error === 'TIME_OUT_OF_SYNC') {
					this.errorMessage = t('spreed', 'Error: System times of Nextcloud server and Recording backend server are out of sync. Please make sure that both servers are connected to a time-server or manually synchronize their time.')
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
}
</style>
