<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
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
	<div id="general_settings" class="videocalls section">
		<h2>{{ t('spreed', 'General settings') }}</h2>

		<p>
			<label for="start_calls">{{ t('spreed', 'Start calls') }}</label>
			<Multiselect id="start_calls"
				v-model="startCalls"
				:options="startCallOptions"
				:placeholder="t('spreed', 'Who can start a call?')"
				label="label"
				track-by="value"
				@input="saveChanges" />
		</p>
		<p>
			<em>{{ t('spreed', 'When a call has started, everyone with access to the conversation can join the call.') }}</em>
		</p>
	</div>
</template>

<script>
import { Multiselect } from 'nextcloud-vue'

const startCallOptions = [
	{ value: 0, label: t('spreed', 'Everyone') },
	{ value: 1, label: t('spreed', 'Users and moderators') },
	{ value: 2, label: t('spreed', 'Moderators only') }
]
export default {
	name: 'GeneralSettings',

	components: {
		Multiselect
	},

	data() {
		return {
			loading: false,
			startCallOptions,
			startCalls: startCallOptions[0]
		}
	},

	mounted() {
		this.loading = true
		this.startCalls = startCallOptions[parseInt(OCP.InitialState.loadState('talk', 'start_calls'))]
		this.loading = false
	},

	methods: {
		saveChanges() {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'start_calls', this.startCalls.value, {
				success: function() {
					this.loading = false
				}.bind(this)
			})
		}
	}
}
</script>
<style scoped lang="scss">
p {
	display: flex;
	align-items: center;

	label {
		display: block;
		margin-right: 10px;
	}
}

.multiselect {
	flex-grow: 1;
	max-width: 300px;
}
</style>
