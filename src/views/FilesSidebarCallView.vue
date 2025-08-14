<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Teleport to="header.app-sidebar-header">
		<div class="talk-sidebar-callview">
			<TopBar is-in-call is-sidebar />
			<CallView :token="token" is-sidebar />
		</div>
	</Teleport>
</template>

<script>
import CallView from '../components/CallView/CallView.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { useHashCheck } from '../composables/useHashCheck.js'
import { useSessionIssueHandler } from '../composables/useSessionIssueHandler.ts'

export default {
	name: 'FilesSidebarCallView',

	components: {
		CallView,
		TopBar,
	},

	setup() {
		useHashCheck()

		return {
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			token: useGetToken(),
		}
	},

	created() {
		window.addEventListener('beforeunload', this.preventUnload)
		this.replaceSidebarHeaderContentsWithCallView()
	},

	beforeUnmount() {
		window.removeEventListener('beforeunload', this.preventUnload)
		this.restoreSidebarHeaderContents()
	},

	methods: {
		preventUnload(event) {
			if (this.isLeavingAfterSessionIssue) {
				return
			}

			event.preventDefault()
		},

		/**
		 * Hides the sidebar header contents (except the close button) and shows
		 * the call view instead.
		 */
		replaceSidebarHeaderContentsWithCallView() {
			const header = document.querySelector('header.app-sidebar-header')
			if (!header) {
				return
			}
			header.classList.add('hidden-by-call')

			const sidebarCloseButton = document.querySelector('.app-sidebar__close')
			sidebarCloseButton?.setAttribute('data-theme-dark', 'true')
		},

		/**
		 * Shows the sidebar header contents and moves the call view back to the
		 * description.
		 */
		restoreSidebarHeaderContents() {
			const header = document.querySelector('header.app-sidebar-header')
			if (!header) {
				return
			}
			header.classList.remove('hidden-by-call')

			const sidebarCloseButton = document.querySelector('.app-sidebar__close')
			sidebarCloseButton?.removeAttribute('data-theme-dark')
		},
	},
}
</script>

<style lang="scss">
header.app-sidebar-header.hidden-by-call > div:not(.talk-sidebar-callview), {
	display: none !important;
}
</style>

<style lang="scss" scoped>
@import '../assets/variables';

#call-container {
	position: relative;

	/* Prevent shadows of videos from leaking on other elements. */
	overflow: hidden;

	/* Show the call container in a 16/9 proportion based on the sidebar
	 * width. */
	padding-bottom: 56.25%;
	max-height: 56.25%;
}

.call-loading{
	padding-bottom: 56.25%;
	max-height: 56.25%;
	background-color: $color-call-background;
}
</style>
