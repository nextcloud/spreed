<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license AGPL-3.0-or-later
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
	<section id="general_settings" class="videocalls section">
		<h2>{{ t('spreed', 'General settings') }}</h2>

		<h3>{{ t('spreed', 'Default notification settings') }}</h3>

		<NcSelect v-model="defaultGroupNotification"
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
			@input="saveDefaultGroupNotification" />

		<h3>{{ t('spreed', 'Integration into other apps') }}</h3>

		<NcCheckboxRadioSwitch :checked="isConversationsFilesChecked"
			:disabled="loading || loadingConversationsFiles"
			@update:checked="saveConversationsFiles">
			{{ t('spreed', 'Allow conversations on files') }}
		</NcCheckboxRadioSwitch>

		<NcCheckboxRadioSwitch :checked="isConversationsFilesPublicSharesChecked"
			:disabled="loading || loadingConversationsFiles || !isConversationsFilesChecked"
			@update:checked="saveConversationsFilesPublicShares">
			{{ t('spreed', 'Allow conversations on public shares for files') }}
		</NcCheckboxRadioSwitch>
	</section>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

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

.default-group-notification {
	min-width: 300px !important;
}
</style>
