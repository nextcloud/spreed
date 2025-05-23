<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
