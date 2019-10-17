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
			defaultPageTitle: false,
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
		currentToken() {
			return this.$route.params.token
		}
	},

	watch: {
		currentToken() {
			this.onChangeConversation()
		}
	},

	beforeMount() {
		window.addEventListener('resize', this.onResize)
		this.onResize()
	},

	mounted() {
		setTimeout(this.onChangeConversation, 2500)
	},

	methods: {
		async onChangeConversation() {
			if (Object.keys(this.conversations).indexOf(this.currentToken) !== -1) {
				const currentConversation = this.conversations[this.currentToken]
				this.setPageTitle(currentConversation.displayName)
			} else {
				this.setPageTitle('')
			}
		},
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

			if (title) {
				title += ' - '
			} else {
				title = ''
			}

			title += this.defaultPageTitle
			window.document.title = title
		},
		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},
		newButtonAction(e) {
			console.debug(e)
		},
		log(e) {
			console.debug(e)
		}
	}
}
</script>

<style lang="scss" scoped>
#content {
	height: 100%;
}
</style>
