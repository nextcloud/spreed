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
			<div v-if="sharedItems[type]" :key="type">
				<AppNavigationCaption :title="getTitle(type)" />
				<SharedItems :type="type"
					:limit="limit(type)"
					:items="sharedItems[type]" />
				<Button v-if="hasMore(sharedItems[type])"
					type="tertiary-no-background"
					class="more"
					:wide="true"
					@click="showMore(type)">
					<template #icon>
						<DotsHorizontal :size="20"
							decorative
							title="" />
					</template>
					{{ getButtonTitle(type) }}
				</Button>
			</div>
		</template>
		<AppNavigationCaption :title="t('spreed', 'Projects')" />
		<CollectionList v-if="getUserId && token"
			:id="token"
			type="room"
			:name="conversation.displayName"
			:is-active="active" />
		<SharedItemsBrowser v-if="showSharedItemsBrowser"
			:shared-items="sharedItems"
			:active-tab.sync="browserActiveTab"
			@close="showSharedItemsBrowser = false" />
	</div>
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'
import SharedItems from './SharedItems'
import { SHARED_ITEM } from '../../../constants'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import SharedItemsBrowser from './SharedItemsBrowser/SharedItemsBrowser.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Button from '@nextcloud/vue/dist/Components/Button'
import sharedItems from '../../../mixins/sharedItems'

export default {

	name: 'SharedItemsTab',

	components: {
		SharedItems,
		CollectionList,
		AppNavigationCaption,
		SharedItemsBrowser,
		DotsHorizontal,
		Button,
	},

	mixins: [sharedItems],

	props: {

		active: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			showSharedItemsBrowser: false,
			browserActiveTab: '',
		}
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

		hasMore(items) {
			return Object.values(items).length > 6
		},

		showMore(type) {
			this.browserActiveTab = type
			this.showSharedItemsBrowser = true
		},

		limit(type) {
			if (type === SHARED_ITEM.TYPES.DECK_CARD || type === SHARED_ITEM.TYPES.LOCATION) {
				return 2
			} else {
				return 6
			}
		},

		getButtonTitle(type) {
			switch (type) {
			case SHARED_ITEM.TYPES.MEDIA:
				return t('spreed', 'Show all media')
			case SHARED_ITEM.TYPES.FILE:
				return t('spreed', 'Show all files')
			case SHARED_ITEM.TYPES.DECK_CARD:
				return t('spreed', 'Show all deck cards')
			case SHARED_ITEM.TYPES.VOICE:
				return t('spreed', 'Show all voice messages')
			case SHARED_ITEM.TYPES.LOCATION:
				return t('spreed', 'Show all locations')
			case SHARED_ITEM.TYPES.AUDIO:
				return t('spreed', 'Show all audio')
			case SHARED_ITEM.TYPES.OTHER:
			default:
				return t('spreed', 'Show all other')
			}
		},
	},
}
</script>

<style lang="scss" scoped>

.more {
	margin-top: 8px;
}

</style>
