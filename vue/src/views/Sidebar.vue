<template>
	<app-sidebar v-show="conversationToken" :title="conversation.displayName" :starred.sync="conversation.isFavorite"
		@close="show=false">
		<template #action>
			<button class="primary">
				{{ t('spreed', 'Start call') }}
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
		conversationToken() {
			const t = this.$route.params.token
			return _.isUndefined(t) ? '' : t
		},
		conversation() {
			if (!this.conversationToken) {
				return {}
			}

			if (!_.isUndefined(this.$store.getters.getConversations[this.conversationToken])) {
				return {}
			}

			return this.$store.getters.getConversations[this.conversationToken]
		},
		callButtonText() {
			if (this.conversation.participantInCall) {
				return t('spreed', 'Leave call')
			}
			if (this.conversation.hasCall) {
				return t('spreed', 'Join call')
			}
			return t('spreed', 'Start call')
		}
	}
}
</script>

<style scoped>

</style>
