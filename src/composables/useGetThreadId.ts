/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { RouteParamValueRaw } from 'vue-router'

import { createSharedComposable } from '@vueuse/core'
import { useRouteQuery } from '@vueuse/router'

/**
 * Shared composable to get threadId of current thread in conversation
 */
export const useGetThreadId = createSharedComposable(function() {
	return useRouteQuery<RouteParamValueRaw, number>('threadId', '0', {
		transform: {
			get: (value: RouteParamValueRaw | undefined) => value ? Number(value) : 0,
			set: (value: number) => value !== 0 ? String(value) : undefined,
		},
	})
})
