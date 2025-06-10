<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="federation_settings" class="federation section">
		<h2>
			{{ t('spreed', 'Federation') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<p class="settings-hint additional-top-margin">
			{{ t('spreed', 'Federated chats and calls work already. Attachment handling is coming in a future version.') }}
		</p>

		<NcCheckboxRadioSwitch :model-value="isFederationEnabled"
			:disabled="loading"
			type="switch"
			@update:model-value="saveFederationEnabled">
			{{ t('spreed', 'Enable Federation in Talk app') }}
		</NcCheckboxRadioSwitch>

		<template v-if="isFederationEnabled">
			<h3>{{ t('spreed', 'Permissions') }}</h3>

			<NcCheckboxRadioSwitch :model-value="isFederationIncomingEnabled"
				:disabled="loading"
				type="switch"
				@update:model-value="saveFederationIncomingEnabled">
				{{ t('spreed', 'Allow users to be invited to federated conversations') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch :model-value="isFederationOutgoingEnabled"
				:disabled="loading"
				type="switch"
				@update:model-value="saveFederationOutgoingEnabled">
				{{ t('spreed', 'Allow users to invite federated users into conversation') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch :model-value="isFederationOnlyTrustedServersEnabled"
				:disabled="loading"
				type="switch"
				@update:model-value="saveFederationOnlyTrustedServersEnabled">
				{{ t('spreed', 'Only allow to federate with trusted servers') }}
			</NcCheckboxRadioSwitch>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="settings-hint additional-top-margin" v-html="trustedServersLink" />

			<h3>{{ t('spreed', 'Limit to groups') }}</h3>

			<p class="settings-hint additional-top-margin">
				{{ t('spreed', 'When at least one group is selected, only people of the listed groups can invite federated users to conversations.') }}
			</p>

			<div class="form">
				<NcSelect v-model="allowedGroups"
					input-id="allow_groups_invite_federated"
					:input-label="t('spreed', 'Groups allowed to invite federated users')"
					name="allow_groups_invite_federated"
					class="form__select"
					:options="groups"
					:placeholder="t('spreed', 'Select groups …')"
					:disabled="loading"
					multiple
					searchable
					:tag-width="60"
					:loading="loadingGroups"
					:show-no-options="false"
					:close-on-select="false"
					track-by="id"
					label="displayname"
					no-wrap
					@search-change="debounceSearchGroup" />

				<NcButton variant="primary"
					:disabled="loading"
					@click="saveAllowedGroups">
					{{ saveLabelAllowedGroups }}
				</NcButton>
			</div>
		</template>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import debounce from 'debounce'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'

const FEDERATION_ENABLED = loadState('spreed', 'federation_enabled', false)
const FEDERATION_INCOMING_ENABLED = loadState('spreed', 'federation_incoming_enabled', true)
const FEDERATION_OUTGOING_ENABLED = loadState('spreed', 'federation_outgoing_enabled', true)
const FEDERATION_ONLY_TRUSTED_SERVERS = loadState('spreed', 'federation_only_trusted_servers', false)
const FEDERATION_ALLOWED_GROUPS = loadState('spreed', 'federation_allowed_groups', [])

export default {
	name: 'Federation',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
	},

	data() {
		return {
			loading: false,
			isFederationEnabled: FEDERATION_ENABLED,
			isFederationIncomingEnabled: FEDERATION_INCOMING_ENABLED,
			isFederationOutgoingEnabled: FEDERATION_OUTGOING_ENABLED,
			isFederationOnlyTrustedServersEnabled: FEDERATION_ONLY_TRUSTED_SERVERS,
			loadingGroups: false,
			groups: [],
			allowedGroups: [],
			saveLabelAllowedGroups: t('spreed', 'Save changes'),
			debounceSearchGroup: () => {},
		}
	},

	computed: {
		trustedServersLink() {
			const href = generateUrl('/settings/admin/sharing#ocFederationSettings')
			return t('spreed', 'Trusted servers can be configured at {linkstart}Sharing settings page{linkend}.')
				.replace('{linkstart}', `<a target="_blank" rel="noreferrer nofollow" class="external" href="${href}">`)
				.replaceAll('{linkend}', ' ↗</a>')
		},
	},

	mounted() {
		// allowed groups come as an array of string ids here
		this.allowedGroups = FEDERATION_ALLOWED_GROUPS.sort((a, b) => a.localeCompare(b))
		this.debounceSearchGroup = debounce(this.searchGroup, 500)
		this.debounceSearchGroup('')
	},

	beforeDestroy() {
		this.debounceSearchGroup.clear?.()
	},

	methods: {
		t,
		saveFederationEnabled(value) {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'federation_enabled', value ? 'yes' : 'no', {
				success: () => {
					this.loading = false
					this.isFederationEnabled = value
				},
			})
		},

		saveFederationIncomingEnabled(value) {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'federation_incoming_enabled', value ? '1' : '0', {
				success: () => {
					this.loading = false
					this.isFederationIncomingEnabled = value
				},
			})
		},

		saveFederationOutgoingEnabled(value) {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'federation_outgoing_enabled', value ? '1' : '0', {
				success: () => {
					this.loading = false
					this.isFederationOutgoingEnabled = value
				},
			})
		},

		saveFederationOnlyTrustedServersEnabled(value) {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'federation_only_trusted_servers', value ? '1' : '0', {
				success: () => {
					this.loading = false
					this.isFederationOnlyTrustedServersEnabled = value
				},
			})
		},

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

				// repopulate allowed groups with full group objects to show display name
				const allowedGroupIds = this.allowedGroups.map((group) => typeof group === 'object' ? group.id : group)
				this.allowedGroups = this.groups.filter((group) => allowedGroupIds.includes(group.id))
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

			const groups = this.allowedGroups.map((group) => typeof group === 'object' ? group.id : group)

			OCP.AppConfig.setValue('spreed', 'federation_allowed_groups', JSON.stringify(groups), {
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
	},
}
</script>

<style scoped lang="scss">
small {
	color: var(--color-warning);
	border: 1px solid var(--color-warning);
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
