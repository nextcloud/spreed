<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { InitialState } from '../../types/index.ts'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'

type Group = InitialState['spreed']['federation_allowed_groups'][number]

const trustedServersLink = t('spreed', 'Trusted servers can be configured at {linkstart}Sharing settings page{linkend}.')
	.replace('{linkstart}', `<a target="_blank" rel="noreferrer nofollow" class="external" href="${generateUrl('/settings/admin/sharing#ocFederationSettings')}">`)
	.replaceAll('{linkend}', ' ↗</a>')

const loading = ref(false)
const isFederationEnabled = ref(loadState<InitialState['spreed']['federation_enabled']>('spreed', 'federation_enabled', false))
const isFederationIncomingEnabled = ref(loadState<InitialState['spreed']['federation_incoming_enabled']>('spreed', 'federation_incoming_enabled', true))
const isFederationOutgoingEnabled = ref(loadState<InitialState['spreed']['federation_outgoing_enabled']>('spreed', 'federation_outgoing_enabled', true))
const isFederationOnlyTrustedServersEnabled = ref(loadState<InitialState['spreed']['federation_only_trusted_servers']>('spreed', 'federation_only_trusted_servers', false))
const loadingGroups = ref(false)
const allowedGroups = ref<Group[]>(loadState<InitialState['spreed']['federation_allowed_groups']>('spreed', 'federation_allowed_groups', []))
const groups = ref<Group[]>([...allowedGroups.value])

const saveLabelAllowedGroups = ref(t('spreed', 'Save changes'))

const debounceSearchGroup = debounce(searchGroup, 500)

onMounted(() => {
	debounceSearchGroup('')
})

onBeforeUnmount(() => {
	debounceSearchGroup.clear()
})

/**
 * Enable or disable federation
 *
 * @param value - new value
 */
function saveFederationEnabled(value: boolean) {
	loading.value = true

	OCP.AppConfig.setValue('spreed', 'federation_enabled', value ? 'yes' : 'no', {
		success: () => {
			loading.value = false
			isFederationEnabled.value = value
		},
	})
}

/**
 * Allow or disallow users to be invited to federated conversations
 *
 * @param value - new value
 */
function saveFederationIncomingEnabled(value: boolean) {
	loading.value = true

	OCP.AppConfig.setValue('spreed', 'federation_incoming_enabled', value ? '1' : '0', {
		success: () => {
			loading.value = false
			isFederationIncomingEnabled.value = value
		},
	})
}

/**
 * Allow or disallow users to invite federated users
 *
 * @param value - new value
 */
function saveFederationOutgoingEnabled(value: boolean) {
	loading.value = true

	OCP.AppConfig.setValue('spreed', 'federation_outgoing_enabled', value ? '1' : '0', {
		success: () => {
			loading.value = false
			isFederationOutgoingEnabled.value = value
		},
	})
}

/**
 * Allow or disallow federation with untrusted servers
 *
 * @param value - new value
 */
function saveFederationOnlyTrustedServersEnabled(value: boolean) {
	loading.value = true

	OCP.AppConfig.setValue('spreed', 'federation_only_trusted_servers', value ? '1' : '0', {
		success: () => {
			loading.value = false
			isFederationOnlyTrustedServersEnabled.value = value
		},
	})
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
function saveAllowedGroups() {
	loading.value = true
	loadingGroups.value = true
	saveLabelAllowedGroups.value = t('spreed', 'Saving …')

	OCP.AppConfig.setValue('spreed', 'federation_allowed_groups', JSON.stringify(allowedGroups.value.map((group) => group.id)), {
		success: () => {
			loading.value = false
			loadingGroups.value = false
			saveLabelAllowedGroups.value = t('spreed', 'Saved!')
			setTimeout(() => {
				saveLabelAllowedGroups.value = t('spreed', 'Save changes')
			}, 5000)
		},
	})
}
</script>

<template>
	<section id="federation_settings" class="federation section">
		<h2>
			{{ t('spreed', 'Federation') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<p class="settings-hint additional-top-margin">
			{{ t('spreed', 'Federated chats and calls work already. Attachment handling is coming in a future version.') }}
		</p>

		<NcCheckboxRadioSwitch
			:modelValue="isFederationEnabled"
			:disabled="loading"
			type="switch"
			@update:modelValue="saveFederationEnabled">
			{{ t('spreed', 'Enable Federation in Talk app') }}
		</NcCheckboxRadioSwitch>

		<template v-if="isFederationEnabled">
			<h3>{{ t('spreed', 'Permissions') }}</h3>

			<NcCheckboxRadioSwitch
				:modelValue="isFederationIncomingEnabled"
				:disabled="loading"
				type="switch"
				@update:modelValue="saveFederationIncomingEnabled">
				{{ t('spreed', 'Allow users to be invited to federated conversations') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch
				:modelValue="isFederationOutgoingEnabled"
				:disabled="loading"
				type="switch"
				@update:modelValue="saveFederationOutgoingEnabled">
				{{ t('spreed', 'Allow users to invite federated users into conversation') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch
				:modelValue="isFederationOnlyTrustedServersEnabled"
				:disabled="loading"
				type="switch"
				@update:modelValue="saveFederationOnlyTrustedServersEnabled">
				{{ t('spreed', 'Only allow to federate with trusted servers') }}
			</NcCheckboxRadioSwitch>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="settings-hint additional-top-margin" v-html="trustedServersLink" />

			<h3>{{ t('spreed', 'Limit to groups') }}</h3>

			<p class="settings-hint additional-top-margin">
				{{ t('spreed', 'When at least one group is selected, only people of the listed groups can invite federated users to conversations.') }}
			</p>

			<div class="form">
				<NcSelect
					v-model="allowedGroups"
					inputId="allow_groups_invite_federated"
					:inputLabel="t('spreed', 'Groups allowed to invite federated users')"
					name="allow_groups_invite_federated"
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
					{{ saveLabelAllowedGroups }}
				</NcButton>
			</div>
		</template>
	</section>
</template>

<style scoped lang="scss">
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
