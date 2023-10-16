<!--
 - @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
	<div id="sip-bridge" class="section">
		<h2>{{ t('spreed', 'SIP configuration') }}</h2>

		<p v-if="!showForm" class="settings-hint">
			{{ t('spreed', 'SIP configuration is only possible with a high-performance backend.') }}
		</p>
		<template v-else>
			<label for="sip-group-enabled" class="form__label">
				{{ t('spreed', 'Restrict SIP configuration') }}
			</label>
			<NcSelect v-model="sipGroups"
				input-id="sip-group-enabled"
				class="form__select"
				:options="groups"
				:placeholder="t('spreed', 'Enable SIP configuration')"
				:disabled="loading"
				:multiple="true"
				:searchable="true"
				:tag-width="60"
				:loading="loadingGroups"
				:show-no-options="false"
				:close-on-select="false"
				track-by="id"
				label="displayname"
				no-wrap
				@search-change="searchGroup" />
			<p class="settings-hint">
				{{ t('spreed', 'Only users of the following groups can enable SIP in conversations they moderate') }}
			</p>

			<NcTextField :value.sync="sharedSecret"
				name="shared-secret"
				class="form__textfield additional-top-margin"
				:disabled="loading"
				:placeholder="t('spreed', 'Shared secret')"
				:label="t('spreed', 'Shared secret')"
				label-visible />

			<label for="dial-in-info" class="form__label additional-top-margin">
				{{ t('spreed', 'Dial-in information') }}
			</label>
			<textarea id="dial-in-info"
				v-model="dialInInfo"
				name="message"
				class="form__textfield"
				rows="4"
				:disabled="loading"
				:placeholder="t('spreed', 'Phone number (Country)')" />
			<p class="settings-hint">
				{{ t('spreed', 'This information is sent in invitation emails as well as displayed in the sidebar to all participants.') }}
			</p>

			<NcButton type="primary"
				:disabled="loading"
				@click="saveSIPSettings">
				{{ t('spreed', 'Save changes') }}
			</NcButton>
		</template>
	</div>
</template>

<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { setSIPSettings } from '../../services/settingsService.js'

export default {
	name: 'SIPBridge',

	components: {
		NcButton,
		NcSelect,
		NcTextField,
	},

	data() {
		return {
			loading: false,
			loadingGroups: false,
			showForm: true,
			groups: [],
			sipGroups: [],
			dialInInfo: '',
			sharedSecret: '',
		}
	},

	mounted() {
		this.loading = true
		this.groups = loadState('spreed', 'sip_bridge_groups').sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.sipGroups = this.groups
		this.dialInInfo = loadState('spreed', 'sip_bridge_dialin_info')
		this.sharedSecret = loadState('spreed', 'sip_bridge_shared_secret')
		this.searchGroup('')
		this.loading = false

		const signaling = loadState('spreed', 'signaling_servers')
		this.showForm = signaling.servers.length > 0
	},

	methods: {
		searchGroup: debounce(async function(query) {
			this.loadingGroups = true
			try {
				const response = await axios.get(generateOcsUrl('cloud/groups/details'), {
					search: query,
					limit: 20,
					offset: 0,
				})
				this.groups = response.data.ocs.data.groups.sort(function(a, b) {
					return a.displayname.localeCompare(b.displayname)
				})
			} catch (err) {
				console.error('Could not fetch groups', err)
			} finally {
				this.loadingGroups = false
			}
		}, 500),

		async saveSIPSettings() {
			this.loading = true
			this.saveLabel = t('spreed', 'Saving …')

			const groups = this.sipGroups.map(group => {
				return group.id
			})

			await setSIPSettings(groups, this.sharedSecret, this.dialInInfo)

			this.loading = false
			showSuccess(t('spreed', 'SIP configuration saved!'))
		},
	},
}
</script>

<style lang="scss" scoped>
h3 {
	margin-top: 24px;
	font-weight: 600;
}

.form {
	&__textfield {
		width: 300px;
	}

	&__select {
		width: 300px;
		margin-bottom: 9px;
	}

	&__label {
		display: block;
		padding: 4px 0;
	}
}

.additional-top-margin {
	margin-top: 10px;
}
</style>
