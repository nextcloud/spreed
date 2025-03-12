<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="matterbridge_settings" class="matterbridge section">
		<h2>
			{{ t('spreed', 'Matterbridge integration') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<template v-if="matterbridgeVersion">
			<p class="settings-hint">
				{{ installedVersion }}
			</p>

			<NcCheckboxRadioSwitch :model-value="isEnabled"
				@update:model-value="saveMatterbridgeEnabled">
				{{ t('spreed', 'Enable Matterbridge integration') }}
			</NcCheckboxRadioSwitch>
		</template>

		<template v-else>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="settings-hint" v-html="description" />

			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="settings-hint" v-html="customBinaryText" />

			<p v-if="errorText" class="settings-hint">
				{{ errorText }}
			</p>

			<NcButton :disabled="isInstalling"
				@click="enableMatterbridgeApp">
				<template v-if="isInstalling" #icon>
					<span class="icon icon-loading-small" />
				</template>
				{{ installButtonText }}
			</NcButton>
		</template>
	</section>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import {
	enableMatterbridgeApp,
	stopAllBridges,
	getMatterbridgeVersion,
} from '../../services/matterbridgeService.js'

export default {
	name: 'MatterbridgeIntegration',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			matterbridgeEnabled: loadState('spreed', 'matterbridge_enable'),
			matterbridgeVersion: loadState('spreed', 'matterbridge_version'),
			isInstalling: false,
			error: loadState('spreed', 'matterbridge_error'),
		}
	},

	computed: {
		isEnabled() {
			return this.matterbridgeEnabled
		},
		installedVersion() {
			return t('spreed', 'Installed version: {version}', {
				version: this.matterbridgeVersion,
			})
		},
		description() {
			return t('spreed', 'You can install the Matterbridge to link Nextcloud Talk to some other services, visit their {linkstart1}GitHub page{linkend} for more details. Downloading and installing the app can take a while. In case it times out, please install it manually from the {linkstart2}Nextcloud App Store{linkend}.')
				.replace('{linkstart1}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://github.com/42wim/matterbridge/wiki">')
				.replace('{linkstart2}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://apps.nextcloud.com/apps/talk_matterbridge">')
				.replace(/{linkend}/g, ' ↗</a>')
		},
		errorText() {
			if (this.error === 'binary_permissions') {
				return t('spreed', 'Matterbridge binary has incorrect permissions. Please make sure the Matterbridge binary file is owned by the correct user and can be executed. It can be found in "/…/nextcloud/apps/talk_matterbridge/bin/".')
			} else if (this.error === 'binary') {
				return t('spreed', 'Matterbridge binary was not found or couldn\'t be executed.')
			} else {
				return ''
			}
		},
		customBinaryText() {
			return t('spreed', 'You can also set the path to the Matterbridge binary manually via the config. Check the {linkstart}Matterbridge integration documentation{linkend} for more information.')
				.replace('{linkstart}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://nextcloud-talk.readthedocs.io/en/latest/matterbridge/">')
				.replace(/{linkend}/g, ' ↗</a>')
		},
		installButtonText() {
			return this.isInstalling
				? t('spreed', 'Downloading …')
				: t('spreed', 'Install Talk Matterbridge')
		},
	},

	methods: {
		t,
		saveMatterbridgeEnabled() {
			this.matterbridgeEnabled = !this.matterbridgeEnabled
			OCP.AppConfig.setValue('spreed', 'enable_matterbridge', this.matterbridgeEnabled ? '1' : '0', {
				success: () => {
					if (!this.matterbridgeEnabled) {
						stopAllBridges()
					}
				},
			})
		},

		async enableMatterbridgeApp() {
			if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
				OC.PasswordConfirmation.requirePasswordConfirmation(this.enableMatterbridgeAppCallback, {}, () => {
					showError(t('spreed', 'An error occurred while installing the Matterbridge app'))
				})
			}

			this.enableMatterbridgeAppCallback()
		},

		async enableMatterbridgeAppCallback() {
			this.isInstalling = true
			try {
				await enableMatterbridgeApp()
			} catch (e) {
				showError(t('spreed', 'An error occurred while installing the Talk Matterbridge. Please install it manually'), {
					onClick: () => {
						window.open('https://apps.nextcloud.com/apps/talk_matterbridge', '_blank')
					},
				})
				return
			}

			try {
				const response = await getMatterbridgeVersion()
				this.matterbridgeVersion = response.data.ocs.data.version
				this.matterbridgeEnabled = true
				this.saveMatterbridgeEnabled()
				this.error = ''
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Failed to execute Matterbridge binary.'))
				if (error?.response?.data?.ocs?.data?.error) {
					this.error = error.response.data.ocs.data.error
				} else {
					this.error = 'binary'
				}
			}
			this.isInstalling = false
		},
	},
}
</script>

<style lang="scss" scoped>
h2 {
	small {
		color: var(--color-warning);
		border: 1px solid var(--color-warning);
		border-radius: 16px;
		padding: 0 9px;
	}
}
</style>
