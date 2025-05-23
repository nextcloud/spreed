/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DeepReadonly, Ref } from 'vue'

import { createSharedComposable } from '@vueuse/core'
import { onBeforeMount, onBeforeUnmount, readonly, ref } from 'vue'

/**
 * Composable to check whether the page is displayed at fullscreen
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is displayed at fullscreen
 */
function useDocumentFullscreenComposable() {
	const isFullscreen = ref<boolean>(document.fullscreenElement !== null)

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
export async function enableFullscreen() {
	if (document.body.requestFullscreen) {
		await document.body.requestFullscreen()
	} else if (document.body.webkitRequestFullscreen) {
		await document.body.webkitRequestFullscreen()
	}
}

/**
 * Disable a fullscreen
 */
export async function disableFullscreen() {
	if (document.exitFullscreen) {
		await document.exitFullscreen()
	} else if (document.webkitExitFullscreen) {
		await document.webkitExitFullscreen()
	}
}

/**
 * Shared composable to check whether the page is displayed at fullscreen
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is displayed at fullscreen
 */
export const useDocumentFullscreen = createSharedComposable(useDocumentFullscreenComposable)
