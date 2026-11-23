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
			inputId="default_group_notification_input"
			:inputLabel="t('spreed', 'Default group notification')"
			name="default_group_notification"
			:options="defaultGroupNotificationOptions"
			:clearable="false"
			:placeholder="t('spreed', 'Default group notification for new groups')"
			label="label"
			trackBy="value"
			:disabled="loading || loadingDefaultGroupNotification"
			@update:modelValue="saveDefaultGroupNotification" />

		<h3>{{ t('spreed', 'Integration into other apps') }}</h3>

		<NcCheckboxRadioSwitch
			:modelValue="isConversationsFilesChecked"
			:disabled="loading || loadingConversationsFiles"
			type="switch"
			@update:modelValue="saveConversationsFiles">
			{{ t('spreed', 'Allow conversations on files') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch
			:modelValue="isConversationsFilesPublicSharesChecked"
			:disabled="loading || loadingConversationsFiles || !isConversationsFilesChecked"
			type="switch"
			@update:modelValue="saveConversationsFilesPublicShares">
			{{ t('spreed', 'Allow conversations on public shares for files') }}
		</NcCheckboxRadioSwitch>
	</section>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'

const defaultGroupNotificationOptions = [
	{ value: 1, label: t('spreed', 'All messages') },
	{ value: 2, label: t('spreed', '@-mentions only') },
	{ value: 3, label: t('spreed', 'Off') },
]
export default {
	name: 'GeneralSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcSelect,
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
		}
	},

	computed: {
		isConversationsFilesChecked() {
			return this.conversationsFiles
		},

		isConversationsFilesPublicSharesChecked() {
			return this.conversationsFilesPublicShares
		},
	},

	mounted() {
		this.loading = true
		this.defaultGroupNotification = defaultGroupNotificationOptions[parseInt(loadState('spreed', 'default_group_notification')) - 1]
		this.loading = false
	},

	methods: {
		t,

		saveDefaultGroupNotification() {
			this.loadingDefaultGroupNotification = true

			OCP.AppConfig.setValue('spreed', 'default_group_notification', String(this.defaultGroupNotification.value), {
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

.default-group-notification {
	min-width: 300px !important;
}
</style>
