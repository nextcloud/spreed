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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div>
		<h2>
			{{ t('spreed', '{n}: Mattermost', { n: num }) }}
			<button @click="$emit('deletePart')">
				{{ t('spreed', 'Delete') }}
			</button>
		</h2>
		<a class="icon icon-link" />
		<label
			:for="'server-' + num"
			class="hidden-visually">
			{{ serverPH }}
		</label>
		<input v-model="part.server"
			type="url"
			:id="'server-' + num"
			:placeholder="serverPH">
		<a class="icon icon-user" />
		<label
			:for="'login-' + num"
			class="hidden-visually">
			{{ loginPH }}
		</label>
		<input v-model="part.login"
			type="text"
			:id="'login-' + num"
			:placeholder="loginPH"
			:readonly="readonly"
			@focus="readonly = false">
		<a class="icon icon-category-auth" />
		<label
			:for="'password-' + num"
			class="hidden-visually">
			{{ passwordPH }}
		</label>
		<input v-model="part.password"
			type="password"
			:id="'password-' + num"
			:placeholder="passwordPH"
			:readonly="readonly"
			@focus="readonly = false">
		<a class="icon icon-group" />
		<label
			:for="'team-' + num"
			class="hidden-visually">
			{{ teamPH }}
		</label>
		<input v-model="part.team"
			type="text"
			:id="'team-' + num"
			:placeholder="teamPH">
		<a class="icon icon-group" />
		<label
			:for="'channel-' + num"
			class="hidden-visually">
			{{ channelPH }}
		</label>
		<input v-model="part.channel"
			type="text"
			:id="'channel-' + num"
			:placeholder="channelPH">
	</div>
</template>

<script>

export default {
	name: 'MattermostBridgePart',
	components: {
	},

	mixins: [
	],

	props: {
		num: {
			type: Number,
			required: true,
		},
		part: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			serverPH: t('spreed', 'Mattermost server URL'),
			loginPH: t('spreed', 'Mattermost user name'),
			teamPH: t('spreed', 'Team name'),
			passwordPH: t('spreed', 'User password'),
			channelPH: t('spreed', 'Channel name'),
			readonly: true,
		}
	},

	computed: {
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
	},
}
</script>

<style scoped>
button {
	display: inline-block;
}

h2 {
	text-align: center;
}

input {
	width: 88%;
}

.icon {
	display: inline-block;
	width: 8%;
}
</style>
