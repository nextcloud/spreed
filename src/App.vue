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
	<Content :class="{'icon-loading': loading}" app-name="vueexample">
		<AppNavigation>
			<ConversationsList />
			<AppNavigationSettings>
				Example settings
			</AppNavigationSettings>
		</AppNavigation>
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
				<input id="link-checkbox" name="link-checkbox" class="checkbox link-checkbox"
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
import AppNavigation from 'nextcloud-vue/dist/Components/AppNavigation'
import AppNavigationSettings from 'nextcloud-vue/dist/Components/AppNavigationSettings'
import AppSidebar from 'nextcloud-vue/dist/Components/AppSidebar'
import AppSidebarTab from 'nextcloud-vue/dist/Components/AppSidebarTab'
import ConversationsList from './components/ConversationsList/ConversationsList'

export default {
	name: 'App',
	components: {
		Content,
		AppContent,
		AppNavigation,
		AppNavigationSettings,
		AppSidebar,
		AppSidebarTab,
		ConversationsList
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

	beforeMount() {
		window.addEventListener('resize', this.onResize)
		this.onResize()
	},

	methods: {
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
