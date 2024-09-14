/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { readonly, ref, onBeforeMount, onBeforeUnmount } from 'vue'
import type { Ref, DeepReadonly } from 'vue'

/**
 * Composable to check whether the page is visible.
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is visible
 */
function useWindowVisibilityComposable() {
	const isWindowVisible = ref<boolean>(!document.hidden)

	const changeWindowVisibility = () => {
		isWindowVisible.value = !document.hidden
	}

	onBeforeMount(() => {
		document.addEventListener('visibilitychange', changeWindowVisibility)
	})

	onBeforeUnmount(() => {
		document.removeEventListener('visibilitychange', changeWindowVisibility)
	})

	return readonly(isWindowVisible)
}

/**
 * Shared composable to check whether the page is visible.
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is visible
 */
export const useWindowVisibility = createSharedComposable(useWindowVisibilityComposable)
