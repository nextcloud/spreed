<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
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
	<div ref="talkChatPreload" class="talkChatTab">
		<div class="emptycontent ui-not-ready-placeholder">
			<div class="icon icon-loading" />
		</div>
	</div>
</template>

<script>
export default {
	name: 'FilesSidebarTabLoader',

	data() {
		return {
			sidebarState: OCA.Files.Sidebar.state,
		}
	},

	computed: {
		isChatTheActiveTab() {
			// FIXME check for empty active tab is currently needed because the
			// activeTab is not set when opening the sidebar from the "Details"
			// action (which opens the first tab, which is the Chat tab).
			return !this.sidebarState.activeTab || this.sidebarState.activeTab === 'chat'
		},
	},

	watch: {
		isChatTheActiveTab: {
			immediate: true,
			handler(value) {
				if (value === true && OCA.Talk?.isFirstLoad === true) {
					OCA.Talk.isFirstLoad = false
					this.replaceAppInTab()
				}
			},
		},
	},

	methods: {
		async replaceAppInTab() {
			try {
				if (OCA.Files.Sidebar) {
					const module = await import(/* webpackChunkName: "files-sidebar-main" */ './mainFilesSidebar.js')
					module.mountSidebar(this.$refs.talkChatPreload)
				}
			} catch (error) {
				console.error(error)
			}
		},
	},
}
</script>

<style scoped>
.talkChatTab {
	height: 100%;

	display: flex;
	flex-grow: 1;
	flex-direction: column;
}

.emptycontent {
	/* Override default top margin set in server and center vertically
	 * instead. */
	margin-top: unset;

	height: 100%;

	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}
</style>
