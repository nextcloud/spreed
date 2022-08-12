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
		<span class="scheme">stun:</span>
		<input ref="stun_server"
			type="text"
			name="stun_server"
			placeholder="stunserver:port"
			:value="server"
			:disabled="loading"
			:aria-label="t('spreed', 'STUN server URL')"
			@input="update">
		<ButtonVue v-show="!isValidServer"
			type="tertiary-no-background"
			:aria-label="t('spreed', 'The server address is invalid')">
			<template #icon>
				<AlertCircle />
			</template>
		</ButtonVue>
		<ButtonVue v-show="!loading"
			type="tertiary-no-background"
			:aria-label="t('spreed', 'Delete this server')"
			@click="removeServer">
			<template #icon>
				<Delete :size="20" />
			</template>
		</ButtonVue>
	</div>
</template>

<script>
import ButtonVue from '@nextcloud/vue/dist/Components/ButtonVue'
import AlertCircle from 'vue-material-design-icons/AlertCircle'
import Delete from 'vue-material-design-icons/Delete'

export default {
	name: 'StunServer',

	components: {
		ButtonVue,
		AlertCircle,
		Delete,
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
				server = server.slice(8)
			} else if (server.startsWith('http://')) {
				server = server.slice(7)
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
