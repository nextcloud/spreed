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
	<div id="matterbridge_settings" class="section">
		<h2>{{ t('spreed', 'Matterbridge integration') }}</h2>

		<p class="settings-hint">
			{{ t('spreed', 'Official matterbridge binary is downloaded from Github.') }}
		</p>

		<p>
			<input id="enable_matterbridge"
				v-model="matterbridgeEnabled"
				type="checkbox"
				name="enable_matterbridge"
				class="checkbox"
				@change="saveMatterbridgeEnabled">
			<label for="enable_matterbridge">{{ t('spreed', 'Enable Matterbridge integration') }}</label>
		</p>

	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { stopAllBridges } from '../../services/bridgeService'

export default {
	name: 'MatterbridgeIntegration',

	components: {},

	data() {
		return {
			matterbridgeEnabled: false,
		}
	},

	mounted() {
		this.loading = true
		this.matterbridgeEnabled = parseInt(loadState('talk', 'enable_matterbridge')) === 1
		this.loading = false
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
	},
}
</script>
<style scoped lang="scss">

h3 {
	margin-top: 24px;
}

p {
	display: flex;
	align-items: center;

	label {
		display: block;
		margin-right: 10px;
	}
}

</style>
