<!--
 - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 -
 - @author Maksim Sukharev <antreesy.web@gmail.com>
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
	<section id="general_settings" class="federation section">
		<h2>
			{{ t('spreed', 'Federation') }}
			<small>{{ t('spreed', 'Beta') }}</small>
		</h2>

		<NcCheckboxRadioSwitch :checked="isFederationEnabled"
			:disabled="loading"
			type="switch"
			@update:checked="saveFederationEnabled">
			{{ t('spreed', 'Enable Federation in Talk app') }}
		</NcCheckboxRadioSwitch>
	</section>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'Federation',

	components: {
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			loading: false,
			isFederationEnabled: loadState('spreed', 'federation_enabled') === 'yes',
		}
	},

	methods: {
		saveFederationEnabled(value) {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'federation_enabled', value ? 'yes' : 'no', {
				success: function() {
					this.loading = false
					this.isFederationEnabled = value
				}.bind(this),
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
</style>
