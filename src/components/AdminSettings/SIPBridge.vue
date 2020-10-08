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
	<div id="sip_bridge" class="videocalls section">
		<h2>{{ t('spreed', 'SIP configuration') }}</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Only users of the following groups can enable SIP in conversations they moderate') }}
		</p>

		<p class="sip_bridge__groups-settings-content">
			<Multiselect v-model="sipGroups"
				class="allowed-groups-select"
				:options="groups"
				:placeholder="t('spreed', 'Enable SIP configuration')"
				:disabled="loading"
				:multiple="true"
				:searchable="true"
				:tag-width="60"
				:loading="loadingGroups"
				:show-no-options="false"
				:close-on-select="false"
				@search-change="searchGroup" />

			<button class="button primary"
				:disabled="loading"
				@click="saveSIPGroups">
				{{ saveLabelSIPGroups }}
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
			saveLabelSIPGroups: t('spreed', 'Save changes'),
		}
	},

	mounted() {
		this.loading = true
		this.sipGroups = loadState('talk', 'sip_bridge_groups')
		this.groups = [...new Set(this.sipGroups.concat(this.canStartConversations))].sort(function(a, b) {
			return a.localeCompare(b)
		})
		this.loading = false
	},

	methods: {
		searchGroup: debounce(async function(query) {
			this.loadingGroups = true
			try {
				const res = await axios.get(generateOcsUrl('cloud', 2) + 'groups', {
					search: query,
					limit: 20,
					offset: 0,
				})
				// remove duplicates and sort
				this.groups = [...new Set(res.data.ocs.data.groups)].sort(function(a, b) {
					return a.localeCompare(b)
				})
			} catch (err) {
				console.error('Could not fetch groups', err)
			} finally {
				this.loadingGroups = false
			}
		}, 500),

		saveSIPGroups() {
			this.loading = true
			this.loadingGroups = true
			this.saveLabelSIPGroups = t('spreed', 'Saving …')

			OCP.AppConfig.setValue('spreed', 'sip_bridge_groups', JSON.stringify(this.sipGroups), {
				success: function() {
					this.loading = false
					this.loadingGroups = false
					this.saveLabelSIPGroups = t('spreed', 'Saved!')
					setTimeout(function() {
						this.saveLabelSIPGroups = t('spreed', 'Save changes')
					}.bind(this), 5000)
				}.bind(this),
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.sip_bridge {
	&__groups-settings-content {
		display: flex;
		align-items: center;

		.allowed-groups-select {
			width: 300px;
		}
		button {
			margin-left: 10px;
		}
	}

	.multiselect {
		flex-grow: 1;
		max-width: 300px;
	}
}

</style>
