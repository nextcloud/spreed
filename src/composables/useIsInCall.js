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
		// When viewing the conversation that holds the active call, show the
		// full call view even if the joined-conversation session marker points
		// to another conversation we browsed to while the call was minimized.
		// Guard against the empty token (no conversation open / no active call),
		// otherwise '' === '' would short-circuit into isInCall('').
		if (callViewStore.activeCallToken !== ''
			&& callViewStore.activeCallToken === token.value
			&& store.getters.isInCall(token.value)) {
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

/**
 * Check whether the user is connected to a call of a conversation other than
 * the one currently shown. In that case the call should be rendered as a
 * minimized in-call bar so the user can browse other conversations while the
 * call keeps running.
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
function useCallMinimizedComposable() {
	const store = useStore()
	const callViewStore = useCallViewStore()
	const token = useGetToken()

	return computed(() => {
		const activeCallToken = callViewStore.activeCallToken
		return activeCallToken !== ''
			&& activeCallToken !== token.value
			&& store.getters.isInCall(activeCallToken)
	})
}

/**
 * Shared composable to check whether the active call should be shown minimized.
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export const useCallMinimized = createSharedComposable(useCallMinimizedComposable)
