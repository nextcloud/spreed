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
			variant="tertiary"
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

			<NcButton v-if="server && checked"
				variant="tertiary"
				:title="t('spreed', 'Test this server')"
				:aria-label="t('spreed', 'Test this server')"
				@click="checkServerVersion">
				<template #icon>
					<IconReload :size="20" />
				</template>
			</NcButton>
		</span>

		<ul v-if="signalingTestInfo.length" class="test-connection-data">
			<li v-for="(row, idx) in signalingTestInfo"
				:key="idx"
				class="test-connection-data__item">
				<span class="test-connection-data__caption">
					{{ row.caption }}
				</span>
				<span>
					{{ row.description }}
				</span>
			</li>
		</ul>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { getBaseUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconAlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import { EventBus } from '../../services/EventBus.ts'
import { fetchSignalingSettings, getWelcomeMessage } from '../../services/signalingService.js'
import { createConnection } from '../../utils/SignalingStandaloneTest.js'

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
			signalingTestInfo: [],
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
			this.signalingTestInfo = []

			this.errorMessage = ''
			this.warningMessage = ''
			this.versionFound = ''

			try {
				const response = await getWelcomeMessage(this.index)
				const data = response.data.ocs.data
				this.versionFound = data.version
				if (data.warning === 'UPDATE_OPTIONAL') {
					this.warningMessage = t('spreed', 'Warning: Running version: {version}; Server does not support all features of this Talk version, missing features: {features}', {
						version: this.versionFound,
						features: data.features.join(', '),
					})
				}

				await this.testWebSocketConnection(this.server)
			} catch (exception) {
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
			} finally {
				this.checked = true
			}
		},

		async testWebSocketConnection(url) {
			try {
				const response = await fetchSignalingSettings({ token: '' }, {})
				const settings = response.data.ocs.data
				const signalingTest = createConnection(settings, url)
				await signalingTest.connect()
				this.signalingTestInfo = [
					{ caption: t('spreed', 'Nextcloud base URL'), description: getBaseUrl() },
					{ caption: t('spreed', 'Talk Backend URL'), description: signalingTest.getBackendUrl() },
					{ caption: t('spreed', 'WebSocket URL'), description: signalingTest.url },
					{ caption: t('spreed', 'Available features'), description: signalingTest.features.join(', ') },
				]
				EventBus.emit('signaling-server-connected', signalingTest)
			} catch (exception) {
				console.error(exception)
				this.errorMessage = t('spreed', 'Error: Websocket connection failed. Check browser console')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.signaling-server {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--default-grid-baseline);
	margin-bottom: 10px;

	& &__textfield {
		width: 300px;
		flex-shrink: 0;
	}

	&__checkbox {
		margin: 0 18px;
	}
}

.test-connection {
	flex-basis: fit-content;
	display: inline-flex;
	align-items: center;
	gap: var(--default-grid-baseline);
}

.test-connection-data {
	flex-basis: 100%;
	display: inline-grid;
	grid-template-columns: auto auto;
	gap: var(--default-grid-baseline);

	&__item {
		display: contents;
	}

	&__caption {
		font-weight: bold;
		margin-inline-end: var(--default-grid-baseline);
	}
}
</style>
