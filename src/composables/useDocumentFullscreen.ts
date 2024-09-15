/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { readonly, ref, onBeforeMount, onBeforeUnmount } from 'vue'
import type { Ref, DeepReadonly } from 'vue'

interface WebkitElement extends Element {
	ALLOW_KEYBOARD_INPUT: FullscreenOptions;
}

/**
 * Composable to check whether the page is displayed at fullscreen
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is displayed at fullscreen
 */
function useDocumentFullscreenComposable() {
	const isFullscreen = ref<boolean>(document.fullscreenElement !== null)

	const changeIsFullscreen = () => {
		isFullscreen.value = document.fullscreenElement !== null
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
	const element = document.getElementById('content-vue')
	if (!element) {
		return
	}

	if (element.requestFullscreen) {
		await element.requestFullscreen()
	} else if (element.webkitRequestFullscreen) {
		await element.webkitRequestFullscreen((Element as unknown as WebkitElement).ALLOW_KEYBOARD_INPUT)
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
