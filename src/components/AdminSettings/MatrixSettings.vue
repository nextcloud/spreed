<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { addMatrixHomeserverParams, InitialState, MatrixHomeserver, updateMatrixHomeserverParams } from '../../types/index.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import debounce from 'debounce'
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import IconDeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import IconLanConnect from 'vue-material-design-icons/LanConnect.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import ConfirmDialog from '../UIShared/ConfirmDialog.vue'
import { useActionStatus } from '../../composables/useActionStatus.ts'
import {
	addMatrixHomeserver,
	getMatrixHomeservers,
	removeMatrixHomeserver,
	testMatrixHomeserver,
	updateMatrixHomeserver,
	updateMatrixSettings,
} from '../../services/matrixService.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'

type Group = InitialState['spreed']['matrix_allowed_groups'][number]

const { getActionStatus, runAction } = useActionStatus()

const loading = ref(false)
const loadingGroups = ref(false)
const enabled = ref(loadState<InitialState['spreed']['matrix_enabled']>('spreed', 'matrix_enabled', false))
const homeservers = ref<MatrixHomeserver[]>([])
const allowedGroups = ref<Group[]>(loadState<InitialState['spreed']['matrix_allowed_groups']>('spreed', 'matrix_allowed_groups', []))
const groups = ref<Group[]>([...allowedGroups.value])
const newHomeserver = reactive<Required<addMatrixHomeserverParams>>({ serverName: '', name: '', baseUrl: '' })

const debounceSearchGroup = debounce(searchGroup, 500)

onMounted(async () => {
	await loadHomeservers()
})

onBeforeUnmount(() => {
	debounceSearchGroup.clear()
})

/**
 * Build the description line of a homeserver
 *
 * @param homeserver - homeserver to describe
 */
function getHomeserverDescription(homeserver: MatrixHomeserver) {
	const parts = [homeserver.serverName, homeserver.baseUrl]
	if (homeserver.specVersions.length) {
		parts.push(t('spreed', 'Matrix spec {version}', { version: homeserver.specVersions.at(-1)! }))
	}
	return parts.join(' · ')
}

/**
 * Search groups to show as options
 *
 * @param query - search query
 */
async function searchGroup(query: string) {
	loadingGroups.value = true
	try {
		const response = await axios.get(generateOcsUrl('cloud/groups/details'), { params: { search: query, limit: 20, offset: 0 } })
		// Keep selected groups as options, even if they are not in the search results
		const options = new Map([...allowedGroups.value, ...response.data.ocs.data.groups as Group[]].map((group) => [group.id, group]))
		groups.value = [...options.values()].sort((a, b) => a.displayname.localeCompare(b.displayname))
	} catch (error) {
		console.error('Could not fetch groups', error)
	} finally {
		loadingGroups.value = false
	}
}

/**
 * Enable or disable Matrix rooms
 *
 * @param value - new value
 */
async function saveEnabled(value: boolean) {
	loading.value = true
	try {
		await updateMatrixSettings({ enabled: value })
		enabled.value = value
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
	} finally {
		loading.value = false
	}
}

/**
 * Save the groups allowed to link Matrix accounts
 */
async function saveAllowedGroups() {
	loading.value = true
	try {
		await runAction('allowedGroups', () => updateMatrixSettings({ allowedGroups: allowedGroups.value.map((group) => group.id) }))
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save allowed groups'))
	} finally {
		loading.value = false
	}
}

/**
 * Load the configured homeservers
 */
async function loadHomeservers() {
	loading.value = true
	try {
		const response = await getMatrixHomeservers()
		homeservers.value = response.data.ocs.data
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not load homeservers'))
	} finally {
		loading.value = false
	}
}

/**
 * Add a homeserver from the form fields
 */
async function addHomeserver() {
	loading.value = true
	try {
		const response = await addMatrixHomeserver({ ...newHomeserver })
		homeservers.value.push(response.data.ocs.data)
		Object.assign(newHomeserver, { serverName: '', name: '', baseUrl: '' })
	} catch (error) {
		console.error(error)
		const reason = isAxiosErrorResponse<{ error: string }>(error) ? error.response?.data?.ocs?.data?.error : undefined
		showError(reason === 'exists'
			? t('spreed', 'This homeserver is already configured')
			: t('spreed', 'Could not add the homeserver: {reason}', { reason: reason ?? t('spreed', 'unreachable or not a Matrix homeserver') }))
	} finally {
		loading.value = false
	}
}

/**
 * Update a homeserver
 *
 * @param homeserver - homeserver to update
 * @param changes - properties to change
 */
async function updateHomeserver(homeserver: MatrixHomeserver, changes: updateMatrixHomeserverParams) {
	loading.value = true
	try {
		const response = await updateMatrixHomeserver(homeserver.id, changes)
		Object.assign(homeserver, response.data.ocs.data)
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not update the homeserver'))
	} finally {
		loading.value = false
	}
}

/**
 * Ask for a new name and rename a homeserver
 *
 * @param homeserver - homeserver to rename
 */
async function renameHomeserver(homeserver: MatrixHomeserver) {
	const name = await spawnDialog(ConfirmDialog, {
		// TRANSLATORS: Dialog title and button to change the name of a Matrix homeserver that people see
		name: t('spreed', 'Edit label'),
		isForm: true,
		inputProps: { label: t('spreed', 'Label shown to people (optional)'), value: homeserver.name },
		buttons: [
			{ label: t('spreed', 'Cancel'), variant: 'tertiary', callback: () => undefined },
			{ label: t('spreed', 'Save'), variant: 'primary', type: 'submit', callback: () => true },
		],
	})

	if (typeof name !== 'string' || name === homeserver.name) {
		return
	}

	await updateHomeserver(homeserver, { name })
}

/**
 * Test the connection to a homeserver
 *
 * @param homeserver - homeserver to test
 */
async function testHomeserver(homeserver: MatrixHomeserver) {
	loading.value = true
	try {
		const response = await runAction(homeserver.id, () => testMatrixHomeserver(homeserver.id))
		Object.assign(homeserver, response.data.ocs.data)
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not reach {server}', { server: homeserver.serverName }))
	} finally {
		loading.value = false
	}
}

/**
 * Remove a homeserver after confirmation
 *
 * @param homeserver - homeserver to remove
 */
async function removeHomeserver(homeserver: MatrixHomeserver) {
	const confirmRemoveHomeserver = await spawnDialog(ConfirmDialog, {
		// TRANSLATORS: Dialog title to confirm removing a Matrix homeserver
		name: t('spreed', 'Remove homeserver'),
		message: t('spreed', 'Do you really want to remove "{server}"? People will no longer be able to link accounts on this homeserver.', {
			server: homeserver.name,
		}, { escape: false, sanitize: false }),
		buttons: [
			{ label: t('spreed', 'No'), variant: 'tertiary', callback: () => undefined },
			{ label: t('spreed', 'Yes'), variant: 'error', callback: () => true },
		],
	})

	if (!confirmRemoveHomeserver) {
		return
	}

	loading.value = true
	try {
		await removeMatrixHomeserver(homeserver.id)
		homeservers.value = homeservers.value.filter((entry) => entry.id !== homeserver.id)
	} catch (error) {
		console.error(error)
		if (isAxiosErrorResponse<{ error: string }>(error) && error.response?.data?.ocs?.data?.error === 'accounts') {
			showError(t('spreed', 'The homeserver cannot be removed while people have accounts linked to it'))
		} else {
			showError(t('spreed', 'Could not remove the homeserver'))
		}
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<section id="matrix_settings" class="matrix section">
		<h2>
			<!-- TRANSLATORS: Admin settings section header for Matrix (chat protocol) rooms integration -->
			{{ t('spreed', 'Matrix rooms') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Let people link a Matrix account and use their Matrix rooms as conversations in Talk.') }}
		</p>

		<div class="matrix__form">
			<NcFormBox>
				<NcFormBoxSwitch
					:modelValue="enabled"
					:label="t('spreed', 'Enable Matrix rooms in Talk')"
					:disabled="loading"
					@update:modelValue="saveEnabled" />
			</NcFormBox>

			<template v-if="enabled">
				<NcFormGroup
					:label="t('spreed', 'Limit to groups')"
					:description="t('spreed', 'By default, everyone can link a Matrix account. When at least one group is selected, only members of the selected groups can.')">
					<NcFormBox>
						<NcSelect
							v-model="allowedGroups"
							inputId="matrix_allowed_groups"
							:inputLabel="t('spreed', 'Groups allowed to link Matrix accounts')"
							name="matrix_allowed_groups"
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
							@open="searchGroup('')"
							@search="debounceSearchGroup($event)" />
						<NcFormBoxButton
							:label="getActionStatus('allowedGroups') === 'success' ? t('spreed', 'Saved!') : t('spreed', 'Save changes')"
							:disabled="loading"
							@click="saveAllowedGroups">
							<template #icon>
								<NcLoadingIcon v-if="getActionStatus('allowedGroups') === 'pending'" :size="20" />
								<IconCheck v-else-if="getActionStatus('allowedGroups') === 'success'" :size="20" fillColor="var(--color-border-success)" />
								<IconAlertCircleOutline v-else-if="getActionStatus('allowedGroups') === 'error'" :size="20" fillColor="var(--color-border-error)" />
								<IconContentSaveOutline v-else :size="20" />
							</template>
						</NcFormBoxButton>
					</NcFormBox>
				</NcFormGroup>

				<div>
					<!-- TRANSLATORS: Header for the list of Matrix servers, where people can have accounts -->
					<h3>{{ t('spreed', 'Homeservers') }}</h3>
					<p class="settings-hint">
						{{ t('spreed', 'People can only link accounts on the homeservers listed here.') }}
					</p>
				</div>

				<NcFormGroup
					v-for="homeserver in homeservers"
					:key="homeserver.id"
					:label="homeserver.name"
					:description="getHomeserverDescription(homeserver)">
					<NcFormBox>
						<NcFormBoxSwitch
							:modelValue="homeserver.enabled"
							:disabled="loading"
							@update:modelValue="updateHomeserver(homeserver, { enabled: $event })">
							<!-- TRANSLATORS: Switch to allow people to link accounts on this Matrix homeserver -->
							{{ t('spreed', 'Enabled') }}
						</NcFormBoxSwitch>
						<NcFormBoxButton
							:disabled="loading"
							@click="renameHomeserver(homeserver)">
							<!-- TRANSLATORS: Dialog title and button to change the name of a Matrix homeserver that people see -->
							{{ t('spreed', 'Edit label') }}
							<template #icon>
								<IconPencilOutline :size="20" />
							</template>
						</NcFormBoxButton>
						<NcFormBoxButton
							:label="t('spreed', 'Test connection')"
							:disabled="loading"
							@click="testHomeserver(homeserver)">
							<template #icon>
								<NcLoadingIcon v-if="getActionStatus(homeserver.id) === 'pending'" :size="20" />
								<IconCheck v-else-if="getActionStatus(homeserver.id) === 'success'" :size="20" fillColor="var(--color-border-success)" />
								<IconAlertCircleOutline v-else-if="getActionStatus(homeserver.id) === 'error'" :size="20" fillColor="var(--color-border-error)" />
								<IconLanConnect v-else :size="20" />
							</template>
						</NcFormBoxButton>
						<NcButton
							wide
							variant="error"
							:disabled="loading"
							@click="removeHomeserver(homeserver)">
							<template #icon>
								<IconDeleteOutline :size="20" />
							</template>
							<!-- TRANSLATORS: Button to remove this Matrix homeserver from the list -->
							{{ t('spreed', 'Remove') }}
						</NcButton>
					</NcFormBox>
				</NcFormGroup>

				<NcFormGroup>
					<template #label>
						<!-- TRANSLATORS: Header for the form to add a Matrix homeserver -->
						{{ t('spreed', 'Add homeserver') }}
					</template>
					<NcFormBox>
						<NcTextField
							v-model="newHomeserver.serverName"
							:label="t('spreed', 'Server name (e.g. matrix.org)')"
							:disabled="loading" />
						<NcTextField
							v-model="newHomeserver.name"
							:label="t('spreed', 'Label shown to people (optional)')"
							:disabled="loading" />
						<NcTextField
							v-model="newHomeserver.baseUrl"
							:label="t('spreed', 'Client API URL (optional, skips .well-known discovery)')"
							:disabled="loading" />
						<NcButton
							wide
							:disabled="loading || !newHomeserver.serverName"
							@click="addHomeserver">
							<template #icon>
								<IconPlus :size="20" />
							</template>
							<!-- TRANSLATORS: Button to add a Matrix homeserver to the list -->
							{{ t('spreed', 'Add homeserver') }}
						</NcButton>
					</NcFormBox>
				</NcFormGroup>
			</template>
		</div>
	</section>
</template>

<style lang="scss" scoped>
small {
	color: var(--color-favorite);
	border: 1px solid var(--color-favorite);
	border-radius: 16px;
	padding: 0 9px;
}

// Keep the whitespace before the badge, like h3 in GeneralSettings
.matrix h2 {
	display: block;
}

// TODO apply the 600px width limit to all admin settings sections
.matrix {
	// Same as in NcFormGroup, it is not defined globally
	--form-element-label-offset: calc(var(--border-radius-element) + var(--default-grid-baseline));

	h2,
	h3,
	.settings-hint {
		max-width: 600px;
		padding-inline: var(--form-element-label-offset);
	}

	// Core h3 is larger than the section h2
	h3 {
		font-size: 18px;
		font-weight: 600;
		margin-bottom: 0;
	}
}

.matrix__form {
	display: flex;
	flex-direction: column;
	gap: calc(2.5 * var(--default-grid-baseline));
	max-width: 600px;
}
</style>
