<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 - @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
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
	<div class="recording-server">
		<input ref="recording_server"
			type="text"
			name="recording_server"
			placeholder="https://recording.example.org"
			:value="server"
			:disabled="loading"
			:aria-label="t('spreed', 'Recording backend URL')"
			@input="updateServer">

		<NcCheckboxRadioSwitch :checked="verify"
			@update:checked="updateVerify">
			{{ t('spreed', 'Validate SSL certificate') }}
		</NcCheckboxRadioSwitch>

		<NcButton v-show="!loading"
			type="tertiary-no-background"
			:aria-label="t('spreed', 'Delete this server')"
			@click="removeServer">
			<template #icon>
				<Delete :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import Delete from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'RecordingServer',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcLoadingIcon,
		Delete,
	},

	props: {
		server: {
			type: String,
			default: '',
			required: true,
		},
		verify: {
			type: Boolean,
			default: false,
			required: true,
		},
		index: {
			type: Number,
			default: -1,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
	},

	methods: {
		removeServer() {
			this.$emit('remove-server', this.index)
		},
		updateServer(event) {
			this.$emit('update:server', event.target.value)
		},
		updateVerify(checked) {
			this.$emit('update:verify', checked)
		},
	},
}
</script>

<style lang="scss" scoped>
.recording-server {
	height: 44px;
	display: flex;
	align-items: center;

	label {
		margin: 0 20px;
		display: inline-block;
	}
}
</style>
