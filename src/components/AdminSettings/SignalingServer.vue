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
	<div class="signaling-server">
		<input ref="signaling_server"
			type="text"
			name="signaling_server"
			placeholder="wss://signaling.example.org"
			:value="server"
			:disabled="loading"
			:aria-label="t('spreed', 'TURN server URL')"
			@input="updateServer">
		<input :id="'verify' + index"
			type="checkbox"
			:name="'verify' + index"
			class="checkbox verify"
			:checked="verify"
			@change="updateVerify">
		<label :for="'verify' + index">{{ t('spreed', 'Validate SSL certificate') }}</label>

		<a v-show="!loading"
			v-tooltip.auto="t('spreed', 'Delete this server')"
			class="icon icon-delete"
			@click="removeServer" />
	</div>
</template>

<script>
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

export default {
	name: 'SignalingServer',

	directives: {
		tooltip: Tooltip,
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
			this.$emit('removeServer', this.index)
		},
		updateServer(event) {
			this.$emit('update:server', event.target.value)
		},
		updateVerify(event) {
			this.$emit('update:verify', event.target.checked)
		},
	},
}
</script>

<style lang="scss" scoped>
.signaling-server {
	height: 44px;
	display: flex;
	align-items: center;
}
</style>
