<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<ul class="conversations">
		<Conversation v-for="item of conversationsList"
			:key="item.id"
			:item="item"
			@click="handleConversationClick(item)" />
		<template v-if="!initialisedConversations">
			<LoadingPlaceholder type="conversations" />
		</template>
		<Hint v-else-if="searchText && !conversationsList.length"
			:hint="t('spreed', 'No matches')" />
	</ul>
</template>

<script>
import { emit } from '@nextcloud/event-bus'

import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

import Hint from '../../Hint.vue'
import LoadingPlaceholder from '../../LoadingPlaceholder.vue'
import Conversation from './Conversation.vue'

import { EventBus } from '../../../services/EventBus.js'

export default {
	name: 'ConversationsList',
	components: {
		Conversation,
		Hint,
		LoadingPlaceholder,
	},
	mixins: [isMobile],
	props: {
		searchText: {
			type: String,
			default: '',
		},

		conversationsList: {
			type: Array,
			required: true,
		},

		initialisedConversations: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			isFetchingConversations: false,
		}
	},

	mounted() {
		EventBus.$on('route-change', this.onRouteChange)
		EventBus.$once('joined-conversation', ({ token }) => {
			this.scrollToConversation(token)
		})
	},

	beforeDestroy() {
		EventBus.$off('route-change', this.onRouteChange)
	},

	methods: {
		scrollToConversation(token) {
			// FIXME: not sure why we can't scroll earlier even when the element exists already
			// when too early, Firefox only scrolls a few pixels towards the element but
			// not enough to make it visible
			setTimeout(() => {
				const conversation = document.getElementById(`conversation_${token}`)
				if (!conversation) {
					return
				}
				this.$nextTick(() => {
					conversation.scrollIntoView({
						behavior: 'smooth',
						block: 'start',
						inline: 'nearest',
					})
				})
			}, 500)
		},
		onRouteChange({ from, to }) {
			if (from.name === 'conversation'
				&& to.name === 'conversation'
				&& from.params.token === to.params.token) {
				// this is triggered when the hash in the URL changes
				return
			}
			if (from.name === 'conversation') {
				this.$store.dispatch('leaveConversation', { token: from.params.token })
				if (to.name !== 'conversation') {
					this.$store.dispatch('updateToken', '')
				}
			}
			if (to.name === 'conversation') {
				this.$store.dispatch('joinConversation', { token: to.params.token })
			}
		},

		// Emit the click event so the search text in the leftsidebar can be reset.
		handleConversationClick(item) {
			this.$emit('click-search-result', item.token)
			if (this.isMobile) {
				emit('toggle-navigation', {
					open: false,
				})
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// Override vue overflow rules for <ul> elements within app-navigation
.conversations {
	overflow: visible !important;
	margin-top: 4px;
}
</style>
