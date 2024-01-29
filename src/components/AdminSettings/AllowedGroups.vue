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
	<section id="allowed_groups" class="videocalls section">
		<h2>{{ t('spreed', 'Limit to groups') }}</h2>
		<p class="settings-hint">
			{{ t('spreed', 'When at least one group is selected, only people of the listed groups can be part of conversations.') }}
		</p>
		<p class="settings-hint">
			{{ t('spreed', 'Guests can still join public conversations.') }}
		</p>
		<p class="settings-hint">
			{{ t('spreed', 'Users that cannot use Talk anymore will still be listed as participants in their previous conversations and also their chat messages will be kept.') }}
		</p>

		<div class="form">
			<NcSelect v-model="allowedGroups"
				input-id="allow_groups_use_talk"
				:input-label="t('spreed', 'Limit using Talk')"
				name="allow_groups_use_talk"
				class="form__select"
				:options="groups"
				:placeholder="t('spreed', 'Limit using Talk')"
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

			<NcButton type="primary"
				:disabled="loading"
				@click="saveAllowedGroups">
				{{ saveLabelAllowedGroups }}
			</NcButton>
		</div>
		<div class="form">
			<NcSelect v-model="canStartConversations"
				input-id="allow_groups_start_conversation"
				:input-label="t('spreed', 'Limit creating a public and group conversation')"
				name="allow_groups_start_conversation"
				class="form__select"
				:options="groups"
				:placeholder="t('spreed', 'Limit creating conversations')"
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

			<NcButton type="primary"
				:disabled="loading"
				@click="saveStartConversationsGroups">
				{{ saveLabelStartConversations }}
			</NcButton>
		</div>

		<div class="form">
			<NcSelect v-model="startCalls"
				input-id="start_calls"
				:input-label="t('spreed', 'Limit starting a call')"
				name="allow_groups_start_calls"
				class="form__select"
				:options="startCallOptions"
				:placeholder="t('spreed', 'Limit starting calls')"
				label="label"
				track-by="value"
				:clearable="false"
				no-wrap
				:disabled="loading || loadingStartCalls"
				@input="saveStartCalls" />
		</div>
		<p>
			<em>{{ t('spreed', 'When a call has started, everyone with access to the conversation can join the call.') }}</em>
		</p>
	</section>
</template>

<script>
import debounce from 'debounce'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

const startCallOptions = [
	{ value: 0, label: t('spreed', 'Everyone') },
	{ value: 1, label: t('spreed', 'Users and moderators') },
	{ value: 2, label: t('spreed', 'Moderators only') },
	{ value: 3, label: t('spreed', 'Disable calls') },
]

export default {
	name: 'AllowedGroups',

	components: {
		NcButton,
		NcSelect,
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
		this.allowedGroups = loadState('spreed', 'allowed_groups').sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.canStartConversations = loadState('spreed', 'start_conversations').sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.startCalls = startCallOptions[parseInt(loadState('spreed', 'start_calls'))]

		// Make a unique list with the groups we know from allowedGroups and canStartConversations
		// Unique checking is done by turning the group objects (with id and name)
		// into json strings and afterwards back again
		const mergedGroups = Array.from(
			new Set(
				this.allowedGroups.concat(this.canStartConversations)
					.map(g => JSON.stringify(g)),
			),
		).map(g => JSON.parse(g))

		this.groups = mergedGroups.sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.loading = false

		this.searchGroup('')
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

		saveAllowedGroups() {
			this.loading = true
			this.loadingGroups = true
			this.saveLabelAllowedGroups = t('spreed', 'Saving …')

			const groups = this.allowedGroups.map(group => {
				return group.id
			})

			OCP.AppConfig.setValue('spreed', 'allowed_groups', JSON.stringify(groups), {
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

			const groups = this.canStartConversations.map(group => {
				return group.id
			})

			OCP.AppConfig.setValue('spreed', 'start_conversations', JSON.stringify(groups), {
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
.form {
	display: flex;
	align-items: flex-end;
	gap: 10px;
	padding-top: 5px;

	&__select {
		min-width: 300px !important;
	}
}
</style>
