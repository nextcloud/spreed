<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div v-if="!loading && active">
		<SharedItems v-for="type in Object.keys(sharedItems)"
			:key="type"
			:type="type"
			:items="sharedItems[type]" />
		<CollectionList v-if="getUserId && token"
			:id="token"
			type="room"
			:name="conversation.displayName" />
	</div>
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'
import SharedItems from './SharedItems'

export default {

	name: 'SharedItemsTab',

	components: {
		SharedItems,
		CollectionList,
	},

	props: {

		active: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		getUserId() {
			return this.$store.getters.getUserId()
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		loading() {
			return !this.sharedItems
		},

		sharedItems() {
			return this.$store.getters.sharedItems(this.token)
		},
	},

	watch: {
		active(newValue) {
			if (newValue) {
				this.getSharedItemsOverview()
			}
		},
	},

	methods: {
		getSharedItemsOverview() {
			this.$store.dispatch('getSharedItemsOverview', { token: this.token })
		},
	},
}
</script>

<style scoped lang="scss">
</style>
