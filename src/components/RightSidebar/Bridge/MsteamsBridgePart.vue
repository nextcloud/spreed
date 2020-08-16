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
			{{ t('spreed', '{n}: Microsoft Teams', { n: num }) }}
			<button @click="$emit('deletePart')">
				{{ t('spreed', 'Delete') }}
			</button>
		</h2>
		<a class="icon icon-link" />
		<label
			:for="'tenant-' + num"
			class="hidden-visually">
			{{ tenantPH }}
		</label>
		<input v-model="part.tenantid"
			type="url"
			:id="'tenant-' + num"
			:placeholder="tenantPH">
		<a class="icon icon-user" />
		<label
			:for="'client-' + num"
			class="hidden-visually">
			{{ clientPH }}
		</label>
		<input v-model="part.clientid"
			type="text"
			:id="'login-' + num"
			:placeholder="clientPH"
			:readonly="readonly"
			@focus="readonly = false">
		<a class="icon icon-category-auth" />
		<label
			:for="'team-' + num"
			class="hidden-visually">
			{{ teamPH }}
		</label>
		<input v-model="part.teamid"
			type="password"
			:id="'team-' + num"
			:placeholder="teamPH"
			:readonly="readonly"
			@focus="readonly = false">
		<a class="icon icon-group" />
		<label
			:for="'thread-' + num"
			class="hidden-visually">
			{{ threadPH }}
		</label>
		<input v-model="part.threadid"
			type="text"
			:id="'thread-' + num"
			:placeholder="threadPH">
	</div>
</template>

<script>

export default {
	name: 'MsteamsBridgePart',
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
			tenantPH: t('spreed', 'Tenant ID'),
			clientPH: t('spreed', 'Client ID'),
			teamPH: t('spreed', 'Team ID'),
			threadPH: t('spreed', 'Thread ID'),
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
