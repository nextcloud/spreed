<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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

		<div class="grid">
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
				keep-open
				track-by="id"
				label="displayname"
				no-wrap
				@search-change="debounceSearchGroup" />
			<NcButton variant="primary"
				:disabled="loading"
				@click="saveAllowedGroups">
				{{ saveLabelAllowedGroups }}
			</NcButton>

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
				keep-open
				track-by="id"
				label="displayname"
				no-wrap
				@search-change="debounceSearchGroup" />
			<NcButton variant="primary"
				:disabled="loading"
				@click="saveStartConversationsGroups">
				{{ saveLabelStartConversations }}
			</NcButton>

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
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'

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

			debounceSearchGroup: () => {},
		}
	},

	mounted() {
		this.loading = true
		this.allowedGroups = loadState('spreed', 'allowed_groups', []).sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.canStartConversations = loadState('spreed', 'start_conversations', []).sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.startCalls = startCallOptions[parseInt(loadState('spreed', 'start_calls'))]

		// Make a unique list with the groups we know from allowedGroups and canStartConversations
		// Unique checking is done by turning the group objects (with id and name)
		// into json strings and afterwards back again
		const mergedGroups = Array.from(new Set(this.allowedGroups.concat(this.canStartConversations)
			.map((g) => JSON.stringify(g)))).map((g) => JSON.parse(g))

		this.groups = mergedGroups.sort(function(a, b) {
			return a.displayname.localeCompare(b.displayname)
		})
		this.loading = false

		this.debounceSearchGroup = debounce(this.searchGroup, 500)
		this.debounceSearchGroup('')
	},

	beforeDestroy() {
		this.debounceSearchGroup.clear?.()
	},

	methods: {
		t,
		async searchGroup(query) {
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
		},

		saveAllowedGroups() {
			this.loading = true
			this.loadingGroups = true
			this.saveLabelAllowedGroups = t('spreed', 'Saving …')

			const groups = this.allowedGroups.map((group) => {
				return group.id
			})

			OCP.AppConfig.setValue('spreed', 'allowed_groups', JSON.stringify(groups), {
				success: () => {
					this.loading = false
					this.loadingGroups = false
					this.saveLabelAllowedGroups = t('spreed', 'Saved!')
					setTimeout(() => {
						this.saveLabelAllowedGroups = t('spreed', 'Save changes')
					}, 5000)
				},
			})
		},

		saveStartConversationsGroups() {
			this.loading = true
			this.loadingGroups = true
			this.saveLabelStartConversations = t('spreed', 'Saving …')

			const groups = this.canStartConversations.map((group) => {
				return group.id
			})

			OCP.AppConfig.setValue('spreed', 'start_conversations', JSON.stringify(groups), {
				success: () => {
					this.loading = false
					this.loadingGroups = false
					this.saveLabelStartConversations = t('spreed', 'Saved!')
					setTimeout(() => {
						this.saveLabelStartConversations = t('spreed', 'Save changes')
					}, 5000)
				},
			})
		},

		saveStartCalls() {
			this.loadingStartCalls = true

			OCP.AppConfig.setValue('spreed', 'start_calls', this.startCalls.value, {
				success: () => {
					this.loadingStartCalls = false
				},
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.grid {
	display: grid;
	grid-template-columns: 3fr 1fr;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	width: fit-content;

	&__select {
		min-width: 300px !important;
	}
}
</style>
