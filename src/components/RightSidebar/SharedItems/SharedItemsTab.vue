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
		<template v-for="type in sharedItemsOrder">
			<SharedItems v-if="sharedItems[type]"
				:key="type"
				:type="type"
				:items="sharedItems[type]" />
		</template>
		<AppNavigationCaption :title="t('spreed', 'Projects')" />
		<CollectionList v-if="getUserId && token"
			:id="token"
			type="room"
			:name="conversation.displayName" />
	</div>
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'
import SharedItems from './SharedItems'
import { SHARED_ITEM } from '../../../constants'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'

export default {

	name: 'SharedItemsTab',

	components: {
		SharedItems,
		CollectionList,
		AppNavigationCaption,
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

		// Defines the order of the sections
		sharedItemsOrder() {
			// FIXME restore when non files work return [SHARED_ITEM.TYPES.MEDIA, SHARED_ITEM.TYPES.FILE, SHARED_ITEM.TYPES.VOICE, SHARED_ITEM.TYPES.AUDIO, SHARED_ITEM.TYPES.LOCATION, SHARED_ITEM.TYPES.DECK_CARD, SHARED_ITEM.TYPES.OTHER]
			return [SHARED_ITEM.TYPES.MEDIA, SHARED_ITEM.TYPES.FILE, SHARED_ITEM.TYPES.VOICE, SHARED_ITEM.TYPES.AUDIO]
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
