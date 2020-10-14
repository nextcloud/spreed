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
	<div id="sip-bridge" class="section">
		<h2>{{ t('spreed', 'SIP configuration') }}</h2>

		<h3>{{ t('spreed', 'Restrict SIP configuration') }}</h3>

		<p class="settings-hint">
			{{ t('spreed', 'Only users of the following groups can enable SIP in conversations they moderate') }}
		</p>

		<Multiselect
			v-model="sipGroups"
			class="sip-bridge__sip-groups-select"
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
			@search-change="searchGroup" />

		<h3>{{ t('spreed', 'Shared secret') }}</h3>

		<input v-model="sharedSecret"
			type="text"
			name="shared-secret"
			class="sip-bridge__shared-secret"
			:disabled="loading"
			:placeholder="t('spreed', 'Shared secret')"
			:aria-label="t('spreed', 'Shared secret')">

		<h3>{{ t('spreed', 'Dial-in information') }}</h3>

		<p class="settings-hint">
			{{ t('spreed', 'This information is sent in invitation emails as well as displayed in the sidebar to all participants.') }}
		</p>

		<textarea
			v-model="dialInInfo"
			name="message"
			class="sip-bridge__dialin-info"
			rows="4"
			:disabled="loading"
			:placeholder="t('spreed', 'Phone number (Country)')" />

		<p>
			<button class="button primary"
				:disabled="loading"
				@click="saveSIPSettings">
				{{ saveLabel }}
			</button>
		</p>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { setSIPSettings } from '../../services/settingsService'

export default {
	name: 'SIPBridge',

	components: {
		Multiselect,
	},

	data() {
		return {
			loading: false,
			loadingGroups: false,
			groups: [],
			sipGroups: [],
			saveLabel: t('spreed', 'Save changes'),
			dialInInfo: '',
			sharedSecret: '',
		}
	},

	mounted() {
		this.loading = true
		this.groups = loadState('talk', 'sip_bridge_groups').sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.sipGroups = this.groups
		this.dialInInfo = loadState('talk', 'sip_bridge_dial-in_info')
		this.sharedSecret = loadState('talk', 'sip_bridge_shared_secret')
		this.searchGroup('')
		this.loading = false
	},

	methods: {
		searchGroup: debounce(async function(query) {
			this.loadingGroups = true
			try {
				const response = await axios.get(generateOcsUrl('cloud', 2) + 'groups/details', {
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
			this.saveLabel = t('spreed', 'Saved!')
			setTimeout(() => {
				this.saveLabel = t('spreed', 'Save changes')
			}, 5000)
		},
	},
}
</script>

<style lang="scss" scoped>
.sip-bridge {
	&__sip-groups-select,
	&__shared-secret,
	&__dialin-info {
		width: 480px;
	}
}

</style>
