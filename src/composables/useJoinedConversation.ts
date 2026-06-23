/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MaybeRefOrGetter, WatchCallback, WatchOptions, WatchStopHandle } from 'vue'

import { createSharedComposable } from '@vueuse/core'
import { onBeforeMount, onBeforeUnmount, readonly, ref, toValue, watch } from 'vue'
import { EventBus } from '../services/EventBus.ts'
import SessionStorage from '../services/SessionStorage.js'

const joinedConversationToken = ref<string | null>(null)

/**
 * Update ref from SessionStorage
 */
function readJoinedConversation() {
	joinedConversationToken.value = SessionStorage.getItem('joined_conversation')
}

/**
 * Shared composable exposing the currently joined conversation token.
 */
function useJoinedConversationComposable() {
	onBeforeMount(() => {
		EventBus.on('joined-conversation', readJoinedConversation)
		readJoinedConversation()
	})

	onBeforeUnmount(() => {
		EventBus.off('joined-conversation', readJoinedConversation)
	})

	return readonly(joinedConversationToken)
}

export const useJoinedConversation = createSharedComposable(useJoinedConversationComposable)

/**
 * Watch for the current joined conversation matching the provided token.
 *
 * @param token token to match against the joined conversation
 * @param callback callback triggered when the joined conversation matches the token
 * @param options watch options
 */
export function watchJoinedConversation(
	token: MaybeRefOrGetter<string | null>,
	callback: WatchCallback<string, string | null | undefined>,
	options?: WatchOptions,
): WatchStopHandle {
	const currentJoinedConversation = useJoinedConversation()

	return watch(currentJoinedConversation, (newToken, oldToken, onCleanup) => {
		const targetToken = toValue(token)
		if (!targetToken || newToken !== targetToken) {
			return
		}

		callback(newToken, oldToken, onCleanup)
	}, options)
}
