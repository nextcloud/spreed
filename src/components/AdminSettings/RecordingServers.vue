<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="recording_server" class="recording-servers section">
		<h2>
			{{ t('spreed', 'Recording backend') }}
		</h2>

		<NcNoteCard v-if="showUploadLimitWarning" type="warning">
			{{ uploadLimitWarning }}
		</NcNoteCard>

		<TransitionWrapper v-if="servers.length"
			name="fade"
			tag="ul"
			group>
			<RecordingServer v-for="(server, index) in servers"
				:key="`server${index}`"
				:server.sync="servers[index].server"
				:verify.sync="servers[index].verify"
				:index="index"
				:loading="loading"
				@remove-server="removeServer"
				@update:server="debounceUpdateServers"
				@update:verify="debounceUpdateServers" />
		</TransitionWrapper>

		<NcButton v-else
			class="additional-top-margin"
			:disabled="loading"
			@click="newServer">
			<template #icon>
				<span v-if="loading" class="icon icon-loading-small" />
				<Plus v-else :size="20" />
			</template>
			{{ t('spreed', 'Add a new recording backend server') }}
		</NcButton>

		<NcTextField class="form__textfield additional-top-margin"
			:value="secret"
			name="recording_secret"
			:disabled="loading"
			:placeholder="t('spreed', 'Shared secret')"
			:label="t('spreed', 'Shared secret')"
			label-visible
			@update:value="updateSecret" />

		<template v-if="servers.length && recordingConsentCapability">
			<h3>{{ t('spreed', 'Recording consent') }}</h3>

			<template v-for="level in recordingConsentOptions">
				<NcCheckboxRadioSwitch :key="level.value + '_radio'"
					:value="level.value.toString()"
					:checked.sync="recordingConsentSelected"
					name="recording-consent"
					type="radio"
					:disabled="loading"
					@update:checked="setRecordingConsent">
					{{ level.label }}
				</NcCheckboxRadioSwitch>

				<p :key="level.value + '_description'" class="consent-description">
					{{ getRecordingConsentDescription(level.value) }}
				</p>
			</template>
		</template>
	</section>
</template>

<script>
import debounce from 'debounce'

import Plus from 'vue-material-design-icons/Plus.vue'

import { getCapabilities } from '@nextcloud/capabilities'
// eslint-disable-next-line
// import { showSuccess } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import RecordingServer from '../../components/AdminSettings/RecordingServer.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { CALL } from '../../constants.js'

const recordingConsentCapability = getCapabilities()?.spreed?.features?.includes('recording-consent')
const recordingConsentOptions = [
	{ value: CALL.RECORDING_CONSENT.OFF, label: t('spreed', 'Disabled for all calls') },
	{ value: CALL.RECORDING_CONSENT.REQUIRED, label: t('spreed', 'Enabled for all calls') },
	{ value: CALL.RECORDING_CONSENT.OPTIONAL, label: t('spreed', 'Configurable on conversation level by moderators') },
]

export default {
	name: 'RecordingServers',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcTextField,
		Plus,
		RecordingServer,
		TransitionWrapper,
	},

	setup() {
		return {
			recordingConsentCapability,
			recordingConsentOptions,
		}
	},

	data() {
		return {
			servers: [],
			secret: '',
			uploadLimit: 0,
			loading: false,
			saved: false,
			recordingConsentSelected: loadState('spreed', 'recording_consent').toString(),
			debounceUpdateServers: () => {},
		}
	},

	computed: {
		showUploadLimitWarning() {
			return this.uploadLimit !== 0 && this.uploadLimit < 512 * (1024 ** 2)
		},
		uploadLimitWarning() {
			return t('spreed', 'The PHP settings "upload_max_filesize" or "post_max_size" only will allow to upload files up to {maxUpload}.', {
				maxUpload: formatFileSize(this.uploadLimit, true, true),
			})
		},
	},

	beforeMount() {
		this.debounceUpdateServers = debounce(this.updateServers, 1000)
		const state = loadState('spreed', 'recording_servers')
		this.servers = state.servers
		this.secret = state.secret
		this.uploadLimit = parseInt(state.uploadLimit, 10)
	},

	beforeDestroy() {
		this.debounceUpdateServers.clear?.()
	},

	methods: {
		removeServer(index) {
			this.servers.splice(index, 1)
			this.debounceUpdateServers()
		},

		newServer() {
			this.servers.push({
				server: '',
				verify: false,
			})
		},

		updateSecret(value) {
			this.secret = value
			this.debounceUpdateServers()
		},

		async updateServers() {
			this.loading = true

			this.servers = this.servers.filter(server => server.server.trim() !== '')

			OCP.AppConfig.setValue('spreed', 'recording_servers', JSON.stringify({
				servers: this.servers,
				secret: this.secret,
			}), {
				success: () => {
					showSuccess(t('spreed', 'Recording backend settings saved'))
					this.loading = false
					this.toggleSave()
				},
			})
		},

		setRecordingConsent(value) {
			this.loading = true
			OCP.AppConfig.setValue('spreed', 'recording_consent', value, {
				success: () => {
					this.loading = false
				},
			})
		},

		getRecordingConsentDescription(value) {
			switch (value) {
			case CALL.RECORDING_CONSENT.OPTIONAL:
				return t('spreed', 'Moderators will be allowed to enable consent on conversation level. The consent to be recorded will be required for each participant before joining every call in this conversation.')
			case CALL.RECORDING_CONSENT.REQUIRED:
				return t('spreed', 'The consent to be recorded will be required for each participant before joining every call.')
			case CALL.RECORDING_CONSENT.OFF:
			default:
				return t('spreed', 'The consent to be recorded is not required.')
			}
		},

		toggleSave() {
			this.saved = true
			setTimeout(() => {
				this.saved = false
			}, 3000)
		},
	},
}
</script>

<style lang="scss" scoped>
.recording-servers {
	.form__textfield {
		width: 300px;
	}
}

.additional-top-margin {
	margin-top: 10px;
}

h3 {
	margin-top: 24px;
	font-weight: 600;
}

.consent-description {
	margin-bottom: 12px;
	opacity: 0.7;
}
</style>
