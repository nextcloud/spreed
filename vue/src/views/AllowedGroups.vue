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
	<div id="allowed_groups" class="videocalls section">
		<h2>{{ t('spreed', 'Limit to groups') }}</h2>
		<p class="settings-hint">
			{{ t('spreed', 'When at least one group is selected, only people of the listed groups can be part of conversations.') }}
		</p>
		<p class="settings-hint">
			{{ t('spreed', 'Guests can still join public conversations.') }}
		</p>
		<p class="settings-hint">
			{{ t('spreed', 'Users that can not use Talk anymore will still be listed as participants in their previous conversations and also their chat messages will be kept.') }}
		</p>

		<p class="allowed-groups-settings-content">
			<multiselect v-model="allowedGroups"
				class="allowed-groups-select"
				:options="groups"
				:placeholder="t('spreed', 'Limit app usage to groups.')"
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
				@click="saveChanges">
				{{ saveButtonText }}
			</button>
		</p>
	</div>
</template>

<script>
import Axios from 'nextcloud-axios'
import { Multiselect } from 'nextcloud-vue'
import _ from 'lodash'

export default {
	name: 'AllowedGroups',

	components: {
		Multiselect
	},

	data() {
		return {
			loading: false,
			loadingGroups: false,
			groups: [],
			allowedGroups: [],
			saveButtonText: t('spreed', 'Save changes')
		}
	},

	mounted() {
		this.loading = true
		this.allowedGroups = OCP.InitialState.loadState('talk', 'allowed_groups')
		this.groups = this.allowedGroups
		this.loading = false

		this.searchGroup('')
	},

	methods: {
		searchGroup: _.debounce(function(query) {
			this.loadingGroups = true
			Axios.get(OC.linkToOCS(`cloud/groups?offset=0&search=${encodeURIComponent(query)}&limit=20`, 2))
				.then(res => res.data.ocs)
				.then(ocs => ocs.data.groups)
				.then(groups => {
					this.groups = _.sortedUniq(_.uniq(this.groups.concat(groups)))
				})
				.catch(err => {
					console.error('could not search groups', err)
				})
				.then(() => {
					this.loadingGroups = false
				})
		}, 500),

		saveChanges() {
			this.loading = true
			this.loadingGroups = true
			this.saveButtonText = t('spreed', 'Saving …')

			OCP.AppConfig.setValue('spreed', 'allowed_groups', JSON.stringify(this.allowedGroups), {
				success: function() {
					this.loading = false
					this.loadingGroups = false
					this.saveButtonText = t('spreed', 'Saved!')
					setTimeout(function() {
						this.saveButtonText = t('spreed', 'Save changes')
					}.bind(this), 5000)
				}.bind(this)
			})
		}
	}
}
</script>

<style lang="scss" scoped>
.allowed-groups-settings-content {
	display: flex;
	align-items: center;

	.allowed-groups-select {
		width: 300px;
	}
	button {
		margin-left: 10px;
	}
}
</style>
