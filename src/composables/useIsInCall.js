/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import { computed } from 'vue'
import { useStore } from 'vuex'
import { useCallViewStore } from '../stores/callView.ts'
import { useGetToken } from './useGetToken.ts'
import { useJoinedConversation } from './useJoinedConversation.ts'

/**
 * Check whether the user joined the call of the current token in this PHP session or not
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
function useIsInCallComposable() {
	const store = useStore()
	const callViewStore = useCallViewStore()
	const token = useGetToken()
	const joinedConversationToken = useJoinedConversation()

	return computed(() => {
		if (callViewStore.forceCallView) {
			return true
		}
		return joinedConversationToken.value === token.value && store.getters.isInCall(token.value)
	})
}

/**
 * Shared composable to check whether the user joined the call of the current token in this PHP session or not
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export const useIsInCall = createSharedComposable(useIsInCallComposable)
