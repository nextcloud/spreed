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
		<input type="text" ref="stun_server" name="stun_server" placeholder="stunserver:port" :value="server" :disabled="loading" @input="update" :aria-label="t('spreed', 'STUN server URL')" />
		<a class="icon icon-delete" @click="removeServer" v-tooltip.auto="t('spreed', 'Delete this server')" v-show="!loading"></a>
	</div>

</template>

<script>
import { Tooltip } from 'nextcloud-vue'

export default {
	name: 'stun-server',

	props: {
		server: {
			type: String,
			default: '',
			required: true
		},
		index: {
			type: Number,
			default: -1,
			required: true
		},
		loading: {
			type: Boolean,
			default: false
		}
	},

	directives: {
		tooltip: Tooltip
	},


	methods: {
		removeServer(event) {
			this.$emit('removeServer', this.index)
		},
		update(event) {
			this.$emit('update:server', event.target.value)
		}
	}
}
</script>
