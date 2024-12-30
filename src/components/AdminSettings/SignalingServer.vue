<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="signaling-server">
		<NcTextField ref="signaling_server"
			v-model="signalingServer"
			class="signaling-server__textfield"
			name="signaling_server"
			placeholder="wss://signaling.example.org"
			:disabled="loading"
			:label="t('spreed', 'High-performance backend URL')" />

		<NcCheckboxRadioSwitch :model-value="verify"
			class="signaling-server__checkbox"
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
			<IconAlertCircleOutline v-else-if="warningMessage" :size="20" fill-color="var(--color-warning)" />
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
import IconAlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { getWelcomeMessage } from '../../services/signalingService.js'

export default {
	name: 'SignalingServer',

	components: {
		IconAlertCircle,
		IconAlertCircleOutline,
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
			warningMessage: '',
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
			if (this.warningMessage) {
				return this.warningMessage
			}
			return t('spreed', 'OK: Running version: {version}', {
				version: this.versionFound,
			})
		},

		signalingServer: {
			get() {
				return this.server
			},
			set(value) {
				this.$emit('update:server', value)
			}
		}
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
			this.warningMessage = ''
			this.versionFound = ''

			try {
				const response = await getWelcomeMessage(this.index)
				this.checked = true
				const data = response.data.ocs.data
				this.versionFound = data.version
				if (data.warning === 'UPDATE_OPTIONAL') {
					this.warningMessage = t('spreed', 'Warning: Running version: {version}; Server does not support all features of this Talk version, missing features: {features}', {
						version: this.versionFound,
						features: data.features.join(', '),
					})
				}
			} catch (exception) {
				this.checked = true
				const data = exception.response.data.ocs.data
				const error = data.error

				if (error === 'CAN_NOT_CONNECT') {
					this.errorMessage = t('spreed', 'Error: Cannot connect to server')
				} else if (error === 'JSON_INVALID') {
					this.errorMessage = t('spreed', 'Error: Server did not respond with proper JSON')
				} else if (error === 'CERTIFICATE_EXPIRED') {
					this.errorMessage = t('spreed', 'Error: Certificate expired')
				} else if (error === 'TIME_OUT_OF_SYNC') {
					this.errorMessage = t('spreed', 'Error: System times of Nextcloud server and High-performance backend server are out of sync. Please make sure that both servers are connected to a time-server or manually synchronize their time.')
				} else if (error === 'UPDATE_REQUIRED') {
					this.versionFound = data.version || t('spreed', 'Could not get version')
					this.errorMessage = t('spreed', 'Error: Running version: {version}; Server needs to be updated to be compatible with this version of Talk', {
						version: this.versionFound,
					})
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
.signaling-server {
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
