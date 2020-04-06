<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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
	<div class="signaling-server">
		<input ref="signaling_server"
			type="text"
			name="signaling_server"
			placeholder="wss://signaling.example.org"
			:value="server"
			:disabled="loading"
			:aria-label="t('spreed', 'Signaling server URL')"
			@input="updateServer">
		<input :id="'verify' + index"
			type="checkbox"
			:name="'verify' + index"
			class="checkbox verify"
			:checked="verify"
			@change="updateVerify">
		<label :for="'verify' + index">{{ t('spreed', 'Validate SSL certificate') }}</label>

		<a v-show="!loading"
			v-tooltip.auto="t('spreed', 'Delete this server')"
			class="icon icon-delete"
			@click="removeServer" />

		<span v-if="server">{{ connectionState }}</span>
	</div>
</template>

<script>
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { getWelcomeMessage } from '../../services/signalingService'

export default {
	name: 'SignalingServer',

	directives: {
		tooltip: Tooltip,
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

	data() {
		return {
			checked: false,
			customError: '',
			versionFound: '',
			responseNotValidJson: false,
			versionNotSupported: false,
		}
	},

	computed: {
		connectionState() {
			if (!this.checked) {
				return t('spreed', 'Status: Checking connection')
			}
			if (this.responseNotValidJson) {
				return t('spreed', 'Error: Server did not respond with proper JSON')
			}
			if (this.versionNotSupported) {
				return t('spreed', 'Error: Server version is too old')
			}
			if (this.customError) {
				return this.customError
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
			this.$emit('removeServer', this.index)
		},
		updateServer(event) {
			this.$emit('update:server', event.target.value)
		},
		updateVerify(event) {
			this.$emit('update:verify', event.target.checked)
		},

		async checkServerVersion() {
			this.checked = false

			this.customError = ''
			this.versionFound = ''
			this.responseNotValidJson = false
			this.versionNotSupported = false

			try {
				const response = await getWelcomeMessage(this.index)
				this.checked = true
				this.versionFound = response.data.ocs.data.version
			} catch (exception) {
				this.checked = true
				this.customError = t('spreed', 'Error: Unknown error occurred')
				if (exception.response.data.ocs.data.error === 'JSON_INVALID') {
					this.responseNotValidJson = true
				} else if (exception.response.data.ocs.data.error === 'VERSION_TOO_OLD') {
					this.versionNotSupported = true
				} else if (exception.response.data.ocs.data.error) {
					this.customError = t('spreed', 'Error: Server responded with: {error}', exception.response.data.ocs.data)
				}
				console.debug(exception.response.data.ocs.data.error)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.signaling-server {
	height: 44px;
	display: flex;
	align-items: center;
}
</style>
