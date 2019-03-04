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
		<input type="text" name="stun_server" placeholder="stunserver:port" v-model="server" :aria-label="t('spreed', 'STUN server URL')" />
		<a class="icon icon-delete" @click="deleteServer" :title="t('spreed', 'Delete servers')"></a>
		<a class="icon icon-add" @click="addServer" :title="t('spreed', 'Add new servers')"></a>
		<span class="icon icon-checkmark-color hidden" :title="t('spreed', 'Saved') "></span>
	</div>

</template>

<script>
	export default {
		name: 'stun-server',

		// props: [
		// 	'server'
		// ],

		data () {
			return {
				server: 's'
			}
		},

		methods: {
			deleteServer () {
				console.log(this.server);
				this.$emit('removeServer');
			},

			addServer () {
				this.$emit('newServer');
			},

			saveServers () {

			}
		},

		// components: {
		// 	Command
		// },

		mounted () {
		}
	}
</script>
