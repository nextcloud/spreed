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
function useDocumentVisibilityComposable() {
	const isDocumentVisible = ref<boolean>(!document.hidden)

	const changeIsDocumentVisible = () => {
		isDocumentVisible.value = !document.hidden
	}

	onBeforeMount(() => {
		document.addEventListener('visibilitychange', changeIsDocumentVisible)
	})

	onBeforeUnmount(() => {
		document.removeEventListener('visibilitychange', changeIsDocumentVisible)
	})

	return readonly(isDocumentVisible)
}

/**
 * Shared composable to check whether the page is visible.
 * @return {DeepReadonly<Ref<boolean>>} - computed boolean whether the page is visible
 */
export const useDocumentVisibility = createSharedComposable(useDocumentVisibilityComposable)
