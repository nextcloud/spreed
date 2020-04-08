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
			<Multiselect v-model="allowedGroups"
				class="allowed-groups-select"
				:options="groups"
				:placeholder="t('spreed', 'Limit using Talk')"
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
				@click="saveAllowedGroups">
				{{ saveLabelAllowedGroups }}
			</button>
		</p>

		<h3>{{ t('spreed', 'Limit creating a public and group conversation') }}</h3>
		<p class="allowed-groups-settings-content">
			<Multiselect v-model="canStartConversations"
				class="allowed-groups-select"
				:options="groups"
				:placeholder="t('spreed', 'Limit creating conversations')"
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
				@click="saveStartConversationsGroups">
				{{ saveLabelStartConversations }}
			</button>
		</p>

		<h3>{{ t('spreed', 'Limit starting a call') }}</h3>
		<p>
			<Multiselect id="start_calls"
				v-model="startCalls"
				:options="startCallOptions"
				:placeholder="t('spreed', 'Limit starting calls')"
				label="label"
				track-by="value"
				:disabled="loading || loadingStartCalls"
				@input="saveStartCalls" />
		</p>
		<p>
			<em>{{ t('spreed', 'When a call has started, everyone with access to the conversation can join the call.') }}</em>
		</p>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'

const startCallOptions = [
	{ value: 0, label: t('spreed', 'Everyone') },
	{ value: 1, label: t('spreed', 'Users and moderators') },
	{ value: 2, label: t('spreed', 'Moderators only') },
]

export default {
	name: 'AllowedGroups',

	components: {
		Multiselect,
	},

	data() {
		return {
			loading: false,
			loadingGroups: false,
			loadingStartCalls: false,
			groups: [],
			allowedGroups: [],
			canStartConversations: [],
			saveLabelAllowedGroups: t('spreed', 'Save changes'),
			saveLabelStartConversations: t('spreed', 'Save changes'),

			startCallOptions,
			startCalls: startCallOptions[0],
		}
	},

	mounted() {
		this.loading = true
		this.allowedGroups = loadState('talk', 'allowed_groups')
		this.canStartConversations = loadState('talk', 'start_conversations')
		this.startCalls = startCallOptions[parseInt(loadState('talk', 'start_calls'))]
		this.groups = [...new Set(this.allowedGroups.concat(this.canStartConversations))].sort(function(a, b) {
			return a.localeCompare(b)
		})
		this.loading = false

		this.searchGroup('')
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

		saveAllowedGroups() {
			this.loading = true
			this.loadingGroups = true
			this.saveLabelAllowedGroups = t('spreed', 'Saving …')

			OCP.AppConfig.setValue('spreed', 'allowed_groups', JSON.stringify(this.allowedGroups), {
				success: function() {
					this.loading = false
					this.loadingGroups = false
					this.saveLabelAllowedGroups = t('spreed', 'Saved!')
					setTimeout(function() {
						this.saveLabelAllowedGroups = t('spreed', 'Save changes')
					}.bind(this), 5000)
				}.bind(this),
			})
		},

		saveStartConversationsGroups() {
			this.loading = true
			this.loadingGroups = true
			this.saveLabelStartConversations = t('spreed', 'Saving …')

			OCP.AppConfig.setValue('spreed', 'start_conversations', JSON.stringify(this.canStartConversations), {
				success: function() {
					this.loading = false
					this.loadingGroups = false
					this.saveLabelStartConversations = t('spreed', 'Saved!')
					setTimeout(function() {
						this.saveLabelStartConversations = t('spreed', 'Save changes')
					}.bind(this), 5000)
				}.bind(this),
			})
		},

		saveStartCalls() {
			this.loadingStartCalls = true

			OCP.AppConfig.setValue('spreed', 'start_calls', this.startCalls.value, {
				success: function() {
					this.loadingStartCalls = false
				}.bind(this),
			})
		},
	},
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

.multiselect {
	flex-grow: 1;
	max-width: 300px;
}
</style>
