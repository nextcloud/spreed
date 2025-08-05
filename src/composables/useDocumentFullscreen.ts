/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import { createSharedComposable } from '@vueuse/core'
import { onBeforeUnmount, readonly, ref } from 'vue'

const isFullscreen = ref<boolean>(document.fullscreenElement !== null)

/**
 * Composable to check whether the page is displayed at fullscreen
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is displayed at fullscreen
 */
function useDocumentFullscreenComposable() {
	const changeIsFullscreen = () => {
		isFullscreen.value = document.fullscreenElement !== null

		if (isFullscreen.value) {
			document.body.classList.add('talk-in-fullscreen')
		} else {
			document.body.classList.remove('talk-in-fullscreen')
		}
	}

	document.addEventListener('fullscreenchange', changeIsFullscreen)
	document.addEventListener('webkitfullscreenchange', changeIsFullscreen)

	onBeforeUnmount(() => {
		document.removeEventListener('fullscreenchange', changeIsFullscreen)
		document.removeEventListener('webkitfullscreenchange', changeIsFullscreen)
	})

	return readonly(isFullscreen)
}

/**
 * Enable a fullscreen with Fullscreen API
 */
async function enableFullscreen() {
	emit('toggle-navigation', { open: false })

	if (document.body.requestFullscreen) {
		await document.body.requestFullscreen()
	} else if (document.body.webkitRequestFullscreen) {
		await document.body.webkitRequestFullscreen()
	}
}

/**
 * Disable a fullscreen
 */
async function disableFullscreen() {
	if (document.exitFullscreen) {
		await document.exitFullscreen()
	} else if (document.webkitExitFullscreen) {
		await document.webkitExitFullscreen()
	}
}

/**
 * Toggles the full screen mode of the call view.
 * If the sidebar is open, it does nothing.
 * If there is an open modal, it shows a warning.
 */
export function toggleFullscreen() {
	if (isFullscreen.value) {
		disableFullscreen()
	} else {
		enableFullscreen()
	}
}

/**
 * Shared composable to check whether the page is displayed at fullscreen
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is displayed at fullscreen
 */
export const useDocumentFullscreen = createSharedComposable(useDocumentFullscreenComposable)
