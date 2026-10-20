<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { InitialState } from '../../types/index.ts'

import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { onBeforeUnmount, ref } from 'vue'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxButton from '@nextcloud/vue/components/NcFormBoxButton'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcFormGroup from '@nextcloud/vue/components/NcFormGroup'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconContentSaveOutline from 'vue-material-design-icons/ContentSaveOutline.vue'
import { useActionStatus } from '../../composables/useActionStatus.ts'
import { setAppConfig } from '../../services/appConfigService.ts'

type Group = InitialState['spreed']['federation_allowed_groups'][number]

const { getActionStatus, runAction } = useActionStatus()

const trustedServersUrl = generateUrl('/settings/admin/sharing#federation-admin-settings')

const loading = ref(false)
const isFederationEnabled = ref(loadState<InitialState['spreed']['federation_enabled']>('spreed', 'federation_enabled', false))
const isFederationIncomingEnabled = ref(loadState<InitialState['spreed']['federation_incoming_enabled']>('spreed', 'federation_incoming_enabled', true))
const isFederationOutgoingEnabled = ref(loadState<InitialState['spreed']['federation_outgoing_enabled']>('spreed', 'federation_outgoing_enabled', true))
const isFederationOnlyTrustedServersEnabled = ref(loadState<InitialState['spreed']['federation_only_trusted_servers']>('spreed', 'federation_only_trusted_servers', false))
const loadingGroups = ref(false)
const allowedGroups = ref<Group[]>(loadState<InitialState['spreed']['federation_allowed_groups']>('spreed', 'federation_allowed_groups', []))
const groups = ref<Group[]>([...allowedGroups.value])

const debounceSearchGroup = debounce(searchGroup, 500)

onBeforeUnmount(() => {
	debounceSearchGroup.clear()
})

/**
 * Enable or disable federation
 *
 * @param value - new value
 */
async function saveFederationEnabled(value: boolean) {
	loading.value = true
	try {
		await setAppConfig('spreed', 'federation_enabled', value ? 'yes' : 'no')
		isFederationEnabled.value = value
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
	} finally {
		loading.value = false
	}
}

/**
 * Allow or disallow users to be invited to federated conversations
 *
 * @param value - new value
 */
async function saveFederationIncomingEnabled(value: boolean) {
	loading.value = true
	try {
		await setAppConfig('spreed', 'federation_incoming_enabled', value ? '1' : '0')
		isFederationIncomingEnabled.value = value
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
	} finally {
		loading.value = false
	}
}

/**
 * Allow or disallow users to invite federated users
 *
 * @param value - new value
 */
async function saveFederationOutgoingEnabled(value: boolean) {
	loading.value = true
	try {
		await setAppConfig('spreed', 'federation_outgoing_enabled', value ? '1' : '0')
		isFederationOutgoingEnabled.value = value
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
	} finally {
		loading.value = false
	}
}

/**
 * Allow or disallow federation with untrusted servers
 *
 * @param value - new value
 */
async function saveFederationOnlyTrustedServersEnabled(value: boolean) {
	loading.value = true
	try {
		await setAppConfig('spreed', 'federation_only_trusted_servers', value ? '1' : '0')
		isFederationOnlyTrustedServersEnabled.value = value
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
	} finally {
		loading.value = false
	}
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
 * Save the groups allowed to invite federated users
 */
async function saveAllowedGroups() {
	loading.value = true
	try {
		await runAction('allowedGroups', () => setAppConfig('spreed', 'federation_allowed_groups', JSON.stringify(allowedGroups.value.map((group) => group.id))))
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save allowed groups'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<section id="federation_settings" class="federation section">
		<h2>
			{{ t('spreed', 'Federation') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Federated chats and calls work already. Attachment handling is coming in a future version.') }}
		</p>

		<div class="federation__form">
			<NcFormBox>
				<NcFormBoxSwitch
					:modelValue="isFederationEnabled"
					:label="t('spreed', 'Enable federation in Talk')"
					:disabled="loading"
					@update:modelValue="saveFederationEnabled" />
			</NcFormBox>

			<template v-if="isFederationEnabled">
				<NcFormGroup
					:label="t('spreed', 'Limit to groups')"
					:description="t('spreed', 'By default, everyone can invite federated users. When at least one group is selected, only members of the selected groups can.')">
					<NcFormBox>
						<NcSelect
							v-model="allowedGroups"
							inputId="federation_allowed_groups"
							:inputLabel="t('spreed', 'Groups allowed to invite federated users')"
							name="federation_allowed_groups"
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

				<NcFormGroup :label="t('spreed', 'Permissions')">
					<NcFormBox>
						<NcFormBoxSwitch
							:modelValue="isFederationIncomingEnabled"
							:label="t('spreed', 'Allow people to be invited to federated conversations')"
							:disabled="loading"
							@update:modelValue="saveFederationIncomingEnabled" />
						<NcFormBoxSwitch
							:modelValue="isFederationOutgoingEnabled"
							:label="t('spreed', 'Allow people to invite federated users to conversations')"
							:disabled="loading"
							@update:modelValue="saveFederationOutgoingEnabled" />
						<NcFormBoxSwitch
							:modelValue="isFederationOnlyTrustedServersEnabled"
							:label="t('spreed', 'Only federate with trusted servers')"
							:disabled="loading"
							@update:modelValue="saveFederationOnlyTrustedServersEnabled" />
						<NcFormBoxButton
							:label="t('spreed', 'Configure trusted servers')"
							:href="trustedServersUrl"
							target="_blank" />
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
.federation h2 {
	display: block;
}

// TODO apply the 600px width limit to all admin settings sections
.federation {
	// Same as in NcFormGroup, it is not defined globally
	--form-element-label-offset: calc(var(--border-radius-element) + var(--default-grid-baseline));

	h2,
	.settings-hint {
		max-width: 600px;
		padding-inline: var(--form-element-label-offset);
	}
}

.federation__form {
	display: flex;
	flex-direction: column;
	gap: calc(2.5 * var(--default-grid-baseline));
	max-width: 600px;
}
</style>
