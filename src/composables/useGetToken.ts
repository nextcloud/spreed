/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { useRouteParams } from '@vueuse/router'

/**
 * Shared composable to get token of current conversation
 */
export const useGetToken = createSharedComposable(function() {
	return useRouteParams<string>('token', '', {
		transform: (value: string | undefined) => value ?? '',
	})
})
