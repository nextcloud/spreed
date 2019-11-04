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
		<Sidebar />
	</Content>
</template>

<script>
import Content from 'nextcloud-vue/dist/Components/Content'
import AppContent from 'nextcloud-vue/dist/Components/AppContent'
import Navigation from './components/Navigation/Navigation'
import Router from './router/router'
import Sidebar from './components/Sidebar/Sidebar'
import { EventBus } from './services/EventBus'

export default {
	name: 'App',
	components: {
		Content,
		AppContent,
		Sidebar,
		Navigation,
	},
	data: function() {
		return {
			defaultPageTitle: false,
			loading: false,
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
		},
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
				// Page title
				const NEXT_CONVERSATION_NAME = this.getConversationName(to.params.token)
				this.setPageTitle(NEXT_CONVERSATION_NAME)
			}
			/**
			 * Fires a global event that tells the whole app that the route has changed. The event
			 * carries the from and to objects as payload
			 */
			EventBus.$emit('routeChange', { from, to })

			next()
		})
	},

	methods: {
		/**
		 * Set the page title to the conversation name
		 * @param {string} title Prefix for the page title e.g. conversation name
		 */
		setPageTitle(title) {
			if (this.defaultPageTitle === false) {
				// On the first load we store the current page title "Talk - Nextcloud",
				// so we can append it every time again
				this.defaultPageTitle = window.document.title
				// When a conversation is opened directly, the "Talk - " part is
				// missing from the title
				if (this.defaultPageTitle.indexOf(t('spreed', 'Talk') + ' - ') !== 0) {
					this.defaultPageTitle = t('spreed', 'Talk') + ' - ' + this.defaultPageTitle
				}
			}

			if (title !== '') {
				window.document.title = `${title} - ${this.defaultPageTitle}`
			} else {
				window.document.title = this.defaultPageTitle
			}
		},

		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},

		/**
		 * Get a conversation's name.
		 * @param {string} token The conversation's token
		 * @returns {string} The conversation's name
		 */
		getConversationName(token) {
			if (!this.$store.getters.conversations[token]) {
				return ''
			}

			return this.$store.getters.conversations[token].displayName
		},
	},
}
</script>

<style lang="scss" scoped>
#content {
	height: 100%;
}
</style>
