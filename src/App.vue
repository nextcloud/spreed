<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
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
	<Content :class="{'icon-loading': loading}" app-name="Talk">
		<Navigation />
		<AppContent>
			<router-view />
		</AppContent>
		<AppSidebar
			v-show="show"
			title="christmas-image-2018-12-25-00:01:12.jpg"
			subtitle="4,3 MB, last edited 41 days ago"
			:starred.sync="starred"
			@close="show=false">
			<template #action>
				<button class="primary">
					Button 1
				</button>
				<input id="link-checkbox"
					name="link-checkbox"
					class="checkbox link-checkbox"
					type="checkbox">
				<label for="link-checkbox" class="link-checkbox-label">Do something</label>
			</template>
			<AppSidebarTab name="Participants" icon="icon-talk">
				Participants
			</AppSidebarTab>
			<AppSidebarTab name="Projects" icon="icon-activity">
				Projects
			</AppSidebarTab>
		</AppSidebar>
	</Content>
</template>

<script>
import Content from 'nextcloud-vue/dist/Components/Content'
import AppContent from 'nextcloud-vue/dist/Components/AppContent'
import AppSidebar from 'nextcloud-vue/dist/Components/AppSidebar'
import AppSidebarTab from 'nextcloud-vue/dist/Components/AppSidebarTab'
import Navigation from './components/Navigation/Navigation'
import Router from './router/router'
import { EventBus } from './services/EventBus'

export default {
	name: 'App',
	components: {
		Content,
		AppContent,
		AppSidebar,
		AppSidebarTab,
		Navigation
	},
	data: function() {
		return {
			loading: false,
			date: Date.now() + 86400000 * 3,
			date2: Date.now() + 86400000 * 3 + Math.floor(Math.random() * 86400000 / 2),
			show: false,
			starred: false,
			windowHeight: 0
		}
	},

	computed: {
		conversations() {
			return this.$store.getters.conversations
		},
		/**
		 * The current conversation token
		 * @returns {string} The token.
		 */
		token() {
			return this.$route.params.token
		}
	},

	beforeMount() {
		window.addEventListener('resize', this.onResize)
		this.onResize()
		/**
		 * Listens to the conversationsReceived globalevent, emitted by the conversationsList
		 * component each time a new batch of conversations is received and processed in
		 * the store.
		 */
		EventBus.$once('conversationsReceived', () => {
			if (this.$route.name === 'conversation') {
				const CURRENT_CONVERSATION_NAME = this.getConversationName(this.token)
				this.setPageTitle(CURRENT_CONVERSATION_NAME)
			}
		})
		/**
		 * Global before guard, this is called whenever a navigation is triggered.
		*/
		Router.beforeEach((to, from, next) => {
			/**
			 * This runs whenever the new route is a conversation.
			 */
			if (to.name === 'conversation') {
				const NEXT_CONVERSATION_NAME = this.getConversationName(to.params.token)
				this.setPageTitle(NEXT_CONVERSATION_NAME)
			}
			next()
		})
	},

	methods: {
		/**
		 * Set the page title to the conversation name
		 * @param {string} title Prefix for the page title e.g. conversation name
		 */
		setPageTitle(title) {
			window.document.title = `${title} - ${t('spreed', 'Talk')}`
		},

		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},
		newButtonAction(e) {
			console.debug(e)
		},
		log(e) {
			console.debug(e)
		},
		/**
		 * Get a conversation's name.
		 * @param {string} token The conversation's token
		 * @returns {string} The conversation's name
		 */
		getConversationName(token) {
			return this.$store.getters.conversations[token].displayName
		}
	}
}
</script>

<style lang="scss" scoped>
#content {
	height: 100%;
}
</style>
