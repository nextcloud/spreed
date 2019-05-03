<template>
	<app-sidebar v-show="activeRoomToken" title="christmas-image-2018-12-25-00:01:12.jpg" subtitle="4,3 MB, last edited 41 days ago"
		:starred.sync="activeRoom.isFavorite"
		@close="show=false">
		<template #action>
			<button class="primary">
				Button 1
			</button>
			<input id="link-checkbox" name="link-checkbox" class="checkbox link-checkbox"
				type="checkbox">
			<label for="link-checkbox" class="link-checkbox-label">Do something</label>
		</template>
		<app-sidebar-tab :name="t('spreed', 'Chat')" icon="icon-comment">
			this is the Chat tab
		</app-sidebar-tab>
		<app-sidebar-tab :name="t('spreed', 'Participants')" icon="icon-contacts-dark">
			this is the Participants tab
		</app-sidebar-tab>
		<app-sidebar-tab :name="t('spreed', 'Projects')" icon="icon-projects">
			this is the Projects tab
		</app-sidebar-tab>
	</app-sidebar>
</template>

<script>

import {
	AppSidebar,
	AppSidebarTab
} from 'nextcloud-vue'
import _ from 'lodash'

export default {
	name: 'Sidebar',

	components: {
		AppSidebar,
		AppSidebarTab
	},

	data() {
		return {
			loading: {
				type: Boolean,
				default: true
			},
			show: {
				type: Boolean,
				default: true
			},
			starred: {
				type: Boolean,
				default: true
			}
		}
	},

	computed: {
		activeRoomToken() {
			const t = this.$route.params.token
			return _.isUndefined(t) ? '' : t
		},
		activeRoom() {
			return this.$store.getConversations[this.activeRoomToken]
		}
	}
}
</script>

<style scoped>

</style>
