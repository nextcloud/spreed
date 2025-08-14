<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import CallView from '../components/CallView/CallView.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { useHashCheck } from '../composables/useHashCheck.js'
import { useSessionIssueHandler } from '../composables/useSessionIssueHandler.ts'

useHashCheck()

const isLeavingAfterSessionIssue = useSessionIssueHandler()
const token = useGetToken()

window.addEventListener('beforeunload', preventUnload)
replaceSidebarHeaderContentsWithCallView()

onBeforeUnmount(() => {
	window.removeEventListener('beforeunload', preventUnload)
	restoreSidebarHeaderContents()
})

/**
 * Prevent unloading the page if the user is in ongoing call
 *
 * @param event
 */
function preventUnload(event: BeforeUnloadEvent) {
	if (isLeavingAfterSessionIssue.value) {
		return
	}

	event.preventDefault()
}

/**
 * Hides the original sidebar header content (except the close button) and shows the call view instead.
 */
function replaceSidebarHeaderContentsWithCallView() {
	const header = document.querySelector('header.app-sidebar-header')
	if (!header) {
		return
	}
	header.classList.add('hidden-by-call')

	const sidebarCloseButton = document.querySelector('.app-sidebar__close')
	sidebarCloseButton?.setAttribute('data-theme-dark', 'true')
	sidebarCloseButton?.setAttribute('disabled', 'true')
}

/**
 * Restores visibility of the original sidebar header content.
 */
function restoreSidebarHeaderContents() {
	const header = document.querySelector('header.app-sidebar-header')
	if (!header) {
		return
	}
	header.classList.remove('hidden-by-call')

	const sidebarCloseButton = document.querySelector('.app-sidebar__close')
	sidebarCloseButton?.removeAttribute('data-theme-dark')
	sidebarCloseButton?.removeAttribute('disabled')
}
</script>

<template>
	<Teleport to="header.app-sidebar-header">
		<div class="talk-sidebar-callview">
			<TopBar is-in-call is-sidebar />
			<CallView :token="token" is-sidebar />
		</div>
	</Teleport>
</template>

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
