<!--
 - @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
 -
 - @author Julien Veyssier <eneiluj@posteo.net>
 -
 - @license GNU AGPL version 3 or any later version
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
	<div id="matterbridge_settings" class="matterbridge section">
		<h2>
			{{ t('spreed', 'Matterbridge integration') }}
			<small>
				{{ t('spreed', 'Beta') }}
				<span class="icon icon-beta-feature" />
			</small>
		</h2>

		<template v-if="matterbridgeVersion">
			<p class="settings-hint">
				{{ installedVersion }}
			</p>

			<p v-if="matterbridgeVersion">
				<input id="enable_matterbridge"
					v-model="matterbridgeEnabled"
					type="checkbox"
					name="enable_matterbridge"
					class="checkbox"
					@change="saveMatterbridgeEnabled">
				<label for="enable_matterbridge">{{ t('spreed', 'Enable Matterbridge integration') }}</label>
			</p>
		</template>

		<template v-else>
			<p class="settings-hint" v-html="description" />

			<p>
				<button v-if="isInstalling">
					<span class="icon icon-loading-small" />
					{{ t('spreed', 'Downloading …') }}
				</button>
				<button v-else
					@click="enableMatterbridgeApp">
					{{ t('spreed', 'Install Talk Matterbridge') }}
				</button>
			</p>
		</template>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import {
	enableMatterbridgeApp,
	stopAllBridges,
	getMatterbridgeVersion,
} from '../../services/matterbridgeService'

export default {
	name: 'MatterbridgeIntegration',

	components: {},

	data() {
		return {
			matterbridgeEnabled: loadState('talk', 'matterbridge_enable'),
			matterbridgeVersion: loadState('talk', 'matterbridge_version'),
			isInstalling: false,
		}
	},

	computed: {
		installedVersion() {
			return t('spreed', 'Installed version: {version}', {
				version: this.matterbridgeVersion,
			})
		},
		description() {
			return t('spreed', 'You can install the Matterbridge to link Nextcloud Talk to some other services, visit their {linkstart1}GitHub page{linkend} for more details. Downloading and installing the app can take a while. In case it times out, please install it manually from the {linkstart2}appstore{linkend}.')
				.replace('{linkstart1}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://github.com/42wim/matterbridge/wiki">')
				.replace('{linkstart2}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://apps.nextcloud.com/apps/talk_matterbridge">')
				.replace(/{linkend}/g, ' ↗</a>')
		},
	},

	methods: {
		saveMatterbridgeEnabled() {
			OCP.AppConfig.setValue('spreed', 'enable_matterbridge', this.matterbridgeEnabled ? '1' : '0', {
				success: function() {
					if (!this.matterbridgeEnabled) {
						stopAllBridges()
					}
				}.bind(this),
			})
		},

		async enableMatterbridgeApp() {
			if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
				OC.PasswordConfirmation.requirePasswordConfirmation(this.enableMatterbridgeAppCallback, {}, () => {
					showError(t('spreed', 'An error occurred while installing the Matterbridge app.'))
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

				this.isInstalling = false
			} catch (e) {
				console.error(e)
				showError(t('spreed', 'Failed to execute Matterbridge binary.'))
			}
		},
	},
}
</script>

<style scoped lang="scss">
h2 {
	small {
		color: var(--color-warning);
		border: 1px solid var(--color-warning);
		border-radius: 16px;
		padding: 0 9px;

		.icon {
			width: 16px;
			height: 16px;
			margin-bottom: 4px;
		}
	}
}

p {
	display: block;
	align-items: center;

	.icon {
		width: 16px;
		height: 16px;
	}

	label {
		display: block;
		margin-right: 10px;
	}
}

</style>
