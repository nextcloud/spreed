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

		<label for="start_calls">{{ t('spreed', 'Start calls') }}</label>
		<select id="start_calls" v-model="startCalls" @change="saveChanges">
			<option value="0">{{ t('spreed', 'Everyone') }}</option>
			<option value="1">{{ t('spreed', 'Users and moderators') }}</option>
			<option value="2">{{ t('spreed', 'Moderators only') }}</option>
		</select>
	</div>
</template>

<script>
export default {
	name: 'GeneralSettings',

	data() {
		return {
			loading: false,
			startCalls: '0'
		}
	},

	mounted() {
		this.loading = true
		this.startCalls = OCP.InitialState.loadState('talk', 'start_calls')
		console.info(this.startCalls)
		this.startCalls = parseInt(this.startCalls)
		console.info(this.startCalls)
		this.loading = false
	},

	methods: {
		saveChanges() {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'start_calls', this.startCalls, {
				success: function() {
					this.loading = false
				}.bind(this)
			})
		}
	}
}
</script>
