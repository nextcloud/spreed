/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { computed } from 'vue'
import { useTokenStore } from '../stores/token.ts'

/**
 * FIXME: if router is available (main app), rely on it.
 * otherwise mock route object and expose controls
 *
 * TODO: move tokenStore.updateToken from router change to here
 *
 * const route = useRouter() ? useRoute() : undefined
 * return computed<string>(() => route?.params?.token ?? tokenStore.token)
 */

/**
 * Shared composable to get token of current conversation
 */
export const useGetToken = createSharedComposable(function() {
	const tokenStore = useTokenStore()

	return computed<string>(() => tokenStore.token)
})
