<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 - @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
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
	<section id="recording_server" class="videocalls section">
		<h2>
			{{ t('spreed', 'Recording backend') }}
		</h2>

		<NcNoteCard v-if="showUploadLimitWarning" type="warning">
			{{ uploadLimitWarning }}
		</NcNoteCard>

		<transition-group name="fade" tag="ul">
			<RecordingServer v-for="(server, index) in servers"
				:key="`server${index}`"
				:server.sync="servers[index].server"
				:verify.sync="servers[index].verify"
				:index="index"
				:loading="loading"
				@remove-server="removeServer"
				@update:server="debounceUpdateServers"
				@update:verify="debounceUpdateServers" />
		</transition-group>

		<NcTextField class="form__textfield additional-top-margin"
			:value="secret"
			name="recording_secret"
			:disabled="loading"
			:placeholder="t('spreed', 'Shared secret')"
			:label="t('spreed', 'Shared secret')"
			label-visible
			@update:value="updateSecret" />

		<NcButton v-if="showAddServerButton"
			class="additional-top-margin"
			:disabled="loading"
			@click="newServer">
			<template #icon>
				<span v-if="loading" class="icon icon-loading-small" />
				<Plus v-else :size="20" />
			</template>
			{{ t('spreed', 'Add a new recording backend server') }}
		</NcButton>
	</section>
</template>

<script>
import debounce from 'debounce'

import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { formatFileSize } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import RecordingServer from '../../components/AdminSettings/RecordingServer.vue'

export default {
	name: 'RecordingServers',

	components: {
		NcButton,
		NcNoteCard,
		NcTextField,
		Plus,
		RecordingServer,
	},

	data() {
		return {
			servers: [],
			secret: '',
			uploadLimit: 0,
			loading: false,
			saved: false,
		}
	},

	computed: {
		showAddServerButton() {
			return this.servers.length === 0
		},
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
		const state = loadState('spreed', 'recording_servers')
		this.servers = state.servers
		this.secret = state.secret
		this.uploadLimit = parseInt(state.uploadLimit, 10)
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

		debounceUpdateServers: debounce(function() {
			this.updateServers()
		}, 1000),

		async updateServers() {
			this.loading = true

			this.servers = this.servers.filter(server => server.server.trim() !== '')

			const self = this
			OCP.AppConfig.setValue('spreed', 'recording_servers', JSON.stringify({
				servers: this.servers,
				secret: this.secret,
			}), {
				success() {
					showSuccess(t('spreed', 'Recording backend settings saved'))
					self.loading = false
					self.toggleSave()
				},
			})
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
.form__textfield {
	width: 300px;
}

.additional-top-margin {
	margin-top: 10px;
}
</style>
