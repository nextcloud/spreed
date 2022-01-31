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
	<div class="stun-server">
		<!-- "stun:" scheme is untranslated -->
		<span class="scheme">{{ t('spreed', 'STUN:') }}</span>
		<input ref="stun_server"
			type="text"
			name="stun_server"
			placeholder="stunserver:port"
			:value="server"
			:disabled="loading"
			:aria-label="t('spreed', 'STUN server URL')"
			@input="update">
		<span v-show="!isValidServer" class="icon icon-error" />
		<a v-show="!loading"
			v-tooltip.auto="t('spreed', 'Delete this server')"
			class="icon icon-delete"
			@click="removeServer" />
	</div>
</template>

<script>
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

export default {
	name: 'StunServer',

	directives: {
		tooltip: Tooltip,
	},

	props: {
		server: {
			type: String,
			default: '',
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

	computed: {
		isValidServer() {
			let server = this.server

			// Remove HTTP or HTTPS protocol, if provided
			if (server.startsWith('https://')) {
				server = server.substr(8)
			} else if (server.startsWith('http://')) {
				server = server.substr(7)
			}

			const parts = server.split(':')

			return parts.length === 2
				&& parts[1].match(/^([1-9]\d{0,4})$/) !== null
				&& parseInt(parts[1]) <= Math.pow(2, 16)
		},
	},

	methods: {
		removeServer() {
			this.$emit('remove-server', this.index)
		},
		update(event) {
			this.$emit('update:server', event.target.value)
		},
	},
}
</script>

<style lang="scss" scoped>
.scheme {
	/* Same margin as inputs to keep the style. */
	margin: 3px 3px 3px 0;
}

.stun-server {
	height: 44px;
	display: flex;
	align-items: center;
}
</style>
