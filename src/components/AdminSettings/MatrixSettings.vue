<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="matrix_settings" class="matrix section">
		<h2>
			{{ t('spreed', 'Matrix rooms') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<p class="settings-hint additional-top-margin">
			{{ t('spreed', 'Let people link a Matrix account and use their Matrix rooms as conversations in Talk. Rooms are synced by the background job, so make sure cron runs regularly.') }}
		</p>

		<NcCheckboxRadioSwitch
			:modelValue="enabled"
			:disabled="loading"
			type="switch"
			@update:modelValue="saveEnabled">
			{{ t('spreed', 'Enable Matrix rooms in Talk') }}
		</NcCheckboxRadioSwitch>

		<template v-if="enabled">
			<h3>{{ t('spreed', 'Homeservers') }}</h3>
			<p class="settings-hint">
				{{ t('spreed', 'People can only link accounts on the homeservers listed here.') }}
			</p>

			<ul v-if="homeservers.length" class="matrix__list">
				<li v-for="homeserver in homeservers" :key="homeserver.id" class="matrix__homeserver">
					<div class="matrix__homeserver-info">
						<strong>{{ homeserver.name }}</strong>
						<span class="matrix__muted">{{ homeserver.serverName }} · {{ homeserver.baseUrl }}</span>
						<span v-if="homeserver.specVersions.length" class="matrix__muted">
							{{ t('spreed', 'Matrix spec {version}', { version: homeserver.specVersions[homeserver.specVersions.length - 1] }) }}
						</span>
					</div>
					<div class="matrix__homeserver-toggles">
						<NcCheckboxRadioSwitch
							:modelValue="homeserver.enabled"
							:disabled="loading"
							type="switch"
							@update:modelValue="updateHomeserver(homeserver, { enabled: $event })">
							{{ t('spreed', 'Enabled') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							:modelValue="homeserver.allowE2ee"
							:disabled="loading"
							type="switch"
							@update:modelValue="updateHomeserver(homeserver, { allowE2ee: $event })">
							{{ t('spreed', 'Allow encrypted rooms') }}
						</NcCheckboxRadioSwitch>
						<NcCheckboxRadioSwitch
							:modelValue="homeserver.allowUpload"
							:disabled="loading"
							type="switch"
							@update:modelValue="updateHomeserver(homeserver, { allowUpload: $event })">
							{{ t('spreed', 'Allow file uploads') }}
						</NcCheckboxRadioSwitch>
					</div>
					<div class="matrix__homeserver-actions">
						<NcButton :disabled="loading" @click="testHomeserver(homeserver)">
							{{ t('spreed', 'Test connection') }}
						</NcButton>
						<NcButton :disabled="loading" variant="tertiary" @click="removeHomeserver(homeserver)">
							<template #icon>
								<IconDeleteOutline :size="20" />
							</template>
							{{ t('spreed', 'Remove') }}
						</NcButton>
					</div>
				</li>
			</ul>

			<div class="matrix__add">
				<NcTextField
					v-model="newServerName"
					:label="t('spreed', 'Server name (e.g. matrix.org)')"
					:disabled="loading" />
				<NcTextField
					v-model="newName"
					:label="t('spreed', 'Label shown to people (optional)')"
					:disabled="loading" />
				<NcTextField
					v-model="newBaseUrl"
					:label="t('spreed', 'Client API URL (optional, skips .well-known discovery)')"
					:disabled="loading" />
				<NcButton :disabled="loading || !newServerName" variant="primary" @click="addHomeserver">
					{{ t('spreed', 'Add homeserver') }}
				</NcButton>
			</div>

			<h3>{{ t('spreed', 'Limit to groups (optional)') }}</h3>
			<p class="settings-hint additional-top-margin">
				{{ t('spreed', 'By default everyone can link a Matrix account. When at least one group is selected, only members of the listed groups can.') }}
			</p>
			<div class="form">
				<NcSelect
					v-model="allowedGroups"
					inputId="matrix_allowed_groups"
					:inputLabel="t('spreed', 'Groups allowed to link Matrix accounts')"
					name="matrix_allowed_groups"
					class="form__select"
					:options="groups"
					:placeholder="t('spreed', 'Select groups …')"
					:disabled="loading"
					multiple
					searchable
					:tagWidth="60"
					:loading="loadingGroups"
					:showNoOptions="false"
					keepOpen
					trackBy="id"
					label="displayname"
					noWrap
					@search="debounceSearchGroup($event)" />
				<NcButton
					variant="primary"
					:disabled="loading"
					@click="saveAllowedGroups">
					{{ t('spreed', 'Save changes') }}
				</NcButton>
			</div>

			<h3>{{ t('spreed', 'Synchronisation') }}</h3>
			<div class="matrix__settings-grid">
				<NcTextField
					v-model="settings.syncInterval"
					type="number"
					:label="t('spreed', 'Sync interval for active accounts (seconds, 10–300)')"
					:disabled="loading" />
				<NcTextField
					v-model="settings.idleSyncInterval"
					type="number"
					:label="t('spreed', 'Sync interval for idle accounts (seconds)')"
					:disabled="loading" />
				<NcTextField
					v-model="settings.maxParallelSyncs"
					type="number"
					:label="t('spreed', 'Accounts synced in parallel')"
					:disabled="loading" />
				<NcTextField
					v-model="settings.foregroundSyncAge"
					type="number"
					:label="t('spreed', 'Sync inline when a viewed conversation is older than (seconds)')"
					:disabled="loading" />
				<NcTextField
					v-model="settings.historyEvents"
					type="number"
					:label="t('spreed', 'History imported per room (events)')"
					:disabled="loading" />
				<NcTextField
					v-model="settings.historyDays"
					type="number"
					:label="t('spreed', 'History imported per room (days)')"
					:disabled="loading" />
			</div>
			<NcButton variant="primary" :disabled="loading" @click="saveSettings">
				{{ t('spreed', 'Save synchronisation settings') }}
			</NcButton>

			<h3>{{ t('spreed', 'Health') }}</h3>
			<NcButton :disabled="loading" @click="loadStatus">
				{{ t('spreed', 'Refresh status') }}
			</NcButton>
			<div v-if="status" class="matrix__status">
				<p>
					{{ t('spreed', '{active} active accounts, {error} needing a re-login, {rooms} Matrix rooms', {
						active: status.accounts.active,
						error: status.accounts.error,
						rooms: status.rooms,
					}) }}
					<span v-if="status.accounts.medianSyncAge !== null">
						· {{ t('spreed', 'median time since last sync: {seconds} s', { seconds: status.accounts.medianSyncAge }) }}
					</span>
				</p>
				<ul v-if="status.errors.length" class="matrix__list">
					<li v-for="entry in status.errors" :key="entry.mxid">
						<strong>{{ entry.mxid }}</strong> ({{ entry.userId }}): {{ entry.error || t('spreed', 'Re-login required') }}
					</li>
				</ul>
			</div>
		</template>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconDeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import {
	addMatrixHomeserver,
	getMatrixStatus,
	removeMatrixHomeserver,
	testMatrixHomeserver,
	updateMatrixHomeserver,
	updateMatrixSettings,
} from '../../services/matrixService.ts'

export default {
	name: 'MatrixSettings',

	components: {
		IconDeleteOutline,
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcTextField,
	},

	setup() {
		return { t }
	},

	data() {
		return {
			loading: false,
			loadingGroups: false,
			enabled: loadState('spreed', 'matrix_enabled', false),
			homeservers: loadState('spreed', 'matrix_homeservers', []),
			allowedGroups: loadState('spreed', 'matrix_allowed_groups', []),
			settings: loadState('spreed', 'matrix_settings', {}),
			groups: [],
			newServerName: '',
			newName: '',
			newBaseUrl: '',
			status: null,
		}
	},

	mounted() {
		this.groups = [...this.allowedGroups]
		this.debounceSearchGroup = debounce(this.searchGroup, 500)
		this.debounceSearchGroup('')
	},

	beforeUnmount() {
		this.debounceSearchGroup.clear?.()
	},

	methods: {
		async searchGroup(query) {
			this.loadingGroups = true
			try {
				const response = await axios.get(generateOcsUrl('cloud/groups/details'), { params: { search: query, limit: 20, offset: 0 } })
				this.groups = response.data.ocs.data.groups.sort((a, b) => a.displayname.localeCompare(b.displayname))
				const allowedGroupIds = this.allowedGroups.map((group) => typeof group === 'object' ? group.id : group)
				this.allowedGroups = this.groups.filter((group) => allowedGroupIds.includes(group.id))
			} catch (error) {
				console.error('Could not fetch groups', error)
			} finally {
				this.loadingGroups = false
			}
		},

		async saveEnabled(value) {
			this.loading = true
			try {
				await updateMatrixSettings({ enabled: value })
				this.enabled = value
				showSuccess(value ? t('spreed', 'Matrix rooms enabled') : t('spreed', 'Matrix rooms disabled'))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not save the setting'))
			} finally {
				this.loading = false
			}
		},

		async saveAllowedGroups() {
			this.loading = true
			try {
				await updateMatrixSettings({ allowedGroups: this.allowedGroups.map((group) => group.id) })
				showSuccess(t('spreed', 'Groups saved'))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not save the groups'))
			} finally {
				this.loading = false
			}
		},

		async saveSettings() {
			this.loading = true
			try {
				const settings = Object.fromEntries(Object.entries(this.settings).map(([key, value]) => [key, typeof value === 'boolean' ? value : parseInt(value, 10)]))
				await updateMatrixSettings({ settings })
				showSuccess(t('spreed', 'Synchronisation settings saved'))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not save the settings'))
			} finally {
				this.loading = false
			}
		},

		async addHomeserver() {
			this.loading = true
			try {
				const response = await addMatrixHomeserver(this.newServerName, this.newName, this.newBaseUrl)
				this.homeservers.push(response.data.ocs.data)
				this.newServerName = ''
				this.newName = ''
				this.newBaseUrl = ''
				showSuccess(t('spreed', 'Homeserver added'))
			} catch (error) {
				console.error(error)
				const reason = error?.response?.data?.ocs?.data?.error
				showError(reason === 'exists'
					? t('spreed', 'This homeserver is already configured')
					: t('spreed', 'Could not add the homeserver: {reason}', { reason: reason ?? t('spreed', 'unreachable or not a Matrix homeserver') }))
			} finally {
				this.loading = false
			}
		},

		async updateHomeserver(homeserver, changes) {
			this.loading = true
			try {
				const response = await updateMatrixHomeserver(homeserver.id, changes)
				Object.assign(homeserver, response.data.ocs.data)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not update the homeserver'))
			} finally {
				this.loading = false
			}
		},

		async testHomeserver(homeserver) {
			this.loading = true
			try {
				const response = await testMatrixHomeserver(homeserver.id)
				Object.assign(homeserver, response.data.ocs.data)
				showSuccess(t('spreed', 'Connection to {server} works', { server: homeserver.serverName }))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not reach {server}', { server: homeserver.serverName }))
			} finally {
				this.loading = false
			}
		},

		async removeHomeserver(homeserver) {
			this.loading = true
			try {
				await removeMatrixHomeserver(homeserver.id)
				this.homeservers = this.homeservers.filter((entry) => entry.id !== homeserver.id)
			} catch (error) {
				console.error(error)
				showError(error?.response?.status === 409
					? t('spreed', 'Accounts are still linked to this homeserver')
					: t('spreed', 'Could not remove the homeserver'))
			} finally {
				this.loading = false
			}
		},

		async loadStatus() {
			this.loading = true
			try {
				const response = await getMatrixStatus()
				this.status = response.data.ocs.data
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not load the status'))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
small {
	color: var(--color-favorite);
	border: 1px solid var(--color-favorite);
	border-radius: 16px;
	padding: 0 9px;
}

h3 {
	margin-top: 24px;
	font-weight: bold;
}

.additional-top-margin {
	margin-top: 10px;
}

.matrix {
	&__list {
		margin: calc(var(--default-grid-baseline) * 2) 0;
	}

	&__homeserver {
		display: flex;
		flex-wrap: wrap;
		gap: calc(var(--default-grid-baseline) * 2);
		align-items: center;
		padding: calc(var(--default-grid-baseline) * 2) 0;
		border-bottom: 1px solid var(--color-border);
	}

	&__homeserver-info {
		display: flex;
		flex-direction: column;
		min-width: 240px;
		flex: 1;
	}

	&__homeserver-toggles {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
	}

	&__homeserver-actions {
		display: flex;
		gap: var(--default-grid-baseline);
	}

	&__muted {
		color: var(--color-text-maxcontrast);
	}

	&__add,
	&__settings-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
		gap: calc(var(--default-grid-baseline) * 2);
		align-items: end;
		margin: calc(var(--default-grid-baseline) * 2) 0;
	}

	&__status {
		margin-top: calc(var(--default-grid-baseline) * 2);
	}
}

.form {
	display: flex;
	align-items: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);

	&__select {
		min-width: 300px !important;
	}
}
</style>
