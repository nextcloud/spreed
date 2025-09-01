<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="general_settings" class="videocalls section">
		<h2>{{ t('spreed', 'General settings') }}</h2>

		<h3>{{ t('spreed', 'Default notification settings') }}</h3>

		<NcSelect
			v-model="defaultGroupNotification"
			class="default-group-notification"
			input-id="default_group_notification_input"
			:input-label="t('spreed', 'Default group notification')"
			name="default_group_notification"
			:options="defaultGroupNotificationOptions"
			:clearable="false"
			:placeholder="t('spreed', 'Default group notification for new groups')"
			label="label"
			track-by="value"
			no-wrap
			:disabled="loading || loadingDefaultGroupNotification"
			@update:model-value="saveDefaultGroupNotification" />

		<h3>{{ t('spreed', 'Integration into other apps') }}</h3>

		<NcCheckboxRadioSwitch
			:model-value="isConversationsFilesChecked"
			:disabled="loading || loadingConversationsFiles"
			type="switch"
			@update:model-value="saveConversationsFiles">
			{{ t('spreed', 'Allow conversations on files') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			:model-value="isConversationsFilesPublicSharesChecked"
			:disabled="loading || loadingConversationsFiles || !isConversationsFilesChecked"
			type="switch"
			@update:model-value="saveConversationsFilesPublicShares">
			{{ t('spreed', 'Allow conversations on public shares for files') }}
		</NcCheckboxRadioSwitch>

		<template v-if="hasSignalingServers">
			<h3>
				{{ t('spreed', 'End-to-end encrypted calls') }}
				<small>{{ t('spreed', 'Beta') }}</small>
			</h3>

			<NcCheckboxRadioSwitch
				v-model="isE2EECallsEnabled"
				type="switch"
				:disabled="loading || !canEnableE2EECalls"
				@update:model-value="updateE2EECallsEnabled">
				{{ t('spreed', 'Enable encryption') }}
			</NcCheckboxRadioSwitch>

			<NcNoteCard
				v-if="!canEnableE2EECalls"
				type="warning"
				:text="t('spreed', 'End-to-end encrypted calls with a configured SIP bridge require a newer version of the High-performance backend and SIP bridge.')" />
			<NcNoteCard
				v-else
				type="warning"
				:text="t('spreed', 'Mobile clients do not support end-to-end encrypted calls at the moment.')" />
		</template>
	</section>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'

const defaultGroupNotificationOptions = [
	{ value: 1, label: t('spreed', 'All messages') },
	{ value: 2, label: t('spreed', '@-mentions only') },
	{ value: 3, label: t('spreed', 'Off') },
]
export default {
	name: 'GeneralSettings',

	components: {
		NcNoteCard,
		NcCheckboxRadioSwitch,
		NcSelect,
	},

	props: {
		hasSignalingServers: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			loading: true,
			loadingConversationsFiles: false,
			loadingDefaultGroupNotification: false,

			defaultGroupNotificationOptions,
			defaultGroupNotification: defaultGroupNotificationOptions[1],

			conversationsFiles: parseInt(loadState('spreed', 'conversations_files')) === 1,
			conversationsFilesPublicShares: parseInt(loadState('spreed', 'conversations_files_public_shares')) === 1,

			hasFeatureJoinFeatures: false,
			isE2EECallsEnabled: getTalkConfig('local', 'call', 'end-to-end-encryption'),
			hasSIPBridge: !!loadState('spreed', 'sip_bridge_shared_secret'),
		}
	},

	computed: {
		isConversationsFilesChecked() {
			return this.conversationsFiles
		},

		isConversationsFilesPublicSharesChecked() {
			return this.conversationsFilesPublicShares
		},

		canEnableE2EECalls() {
			return this.hasFeatureJoinFeatures || !this.hasSIPBridge
		},
	},

	mounted() {
		this.loading = true
		this.defaultGroupNotification = defaultGroupNotificationOptions[parseInt(loadState('spreed', 'default_group_notification')) - 1]
		this.loading = false

		EventBus.on('signaling-server-connected', this.updateSignalingDetails)
		EventBus.on('sip-settings-updated', this.updateSipDetails)
	},

	beforeUnmount() {
		EventBus.off('signaling-server-connected', this.updateSignalingDetails)
		EventBus.off('sip-settings-updated', this.updateSipDetails)
	},

	methods: {
		t,

		updateSignalingDetails(signaling) {
			this.hasFeatureJoinFeatures = signaling.hasFeature('join-features')
		},

		updateSipDetails(settings) {
			this.hasSIPBridge = !!settings.sharedSecret
		},

		updateE2EECallsEnabled(value) {
			this.loading = true
			OCP.AppConfig.setValue('spreed', 'call_end_to_end_encryption', value ? '1' : '0', {
				success: () => {
					this.loading = false
				},
			})
		},

		saveDefaultGroupNotification() {
			this.loadingDefaultGroupNotification = true

			OCP.AppConfig.setValue('spreed', 'default_group_notification', this.defaultGroupNotification.value, {
				success: () => {
					this.loadingDefaultGroupNotification = false
				},
			})
		},

		saveConversationsFiles(checked) {
			this.loadingConversationsFiles = true
			this.conversationsFiles = checked

			OCP.AppConfig.setValue('spreed', 'conversations_files', this.conversationsFiles ? '1' : '0', {
				success: () => {
					if (!this.conversationsFiles) {
						// When the file integration is disabled, the share integration is also disabled
						OCP.AppConfig.setValue('spreed', 'conversations_files_public_shares', '0', {
							success: () => {
								this.conversationsFilesPublicShares = false
								this.loadingConversationsFiles = false
							},
						})
					} else {
						this.loadingConversationsFiles = false
					}
				},
			})
		},

		saveConversationsFilesPublicShares(checked) {
			this.loadingConversationsFiles = true
			this.conversationsFilesPublicShares = checked

			OCP.AppConfig.setValue('spreed', 'conversations_files_public_shares', this.conversationsFilesPublicShares ? '1' : '0', {
				success: () => {
					this.loadingConversationsFiles = false
				},
			})
		},
	},
}
</script>

<style scoped lang="scss">

h3 {
	margin-top: 24px;
	font-weight: 600;
}

small {
	color: var(--color-favorite);
	border: 1px solid var(--color-favorite);
	border-radius: 16px;
	padding: 0 9px;
}

.default-group-notification {
	min-width: 300px !important;
}
</style>
