/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/**
 * Shared composable to get threadId of current thread in conversation
 */
export const useGetThreadId = createSharedComposable(function() {
	const router = useRouter()
	const route = useRoute()

	if (router) {
		return computed<number>({
			get: () => route.query.threadId ? Number(route.query.threadId) : 0,
			set: (value: number) => {
				router.push({ query: { ...route.query, threadId: value !== 0 ? value : undefined } })
			},
		})
	} else {
		return ref(0)
	}
})
