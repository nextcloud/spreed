/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { MaybeRefOrGetter, WatchCallback, WatchStopHandle } from 'vue'

import { createSharedComposable, whenever } from '@vueuse/core'
import { onBeforeMount, onBeforeUnmount, readonly, ref, toValue } from 'vue'
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
 * Fires immediately if already matching, and stops after the first match
 *
 * @param token token to match against the joined conversation
 * @param callback callback triggered when the joined conversation matches the token
 */
export function watchJoinedConversation(
	token: MaybeRefOrGetter<string | null>,
	callback: WatchCallback<string, string | undefined>,
): WatchStopHandle {
	const currentJoinedConversation = useJoinedConversation()

	// Getter resolves to the matching token / undefined
	// `whenever()` only invokes the callback and stops watching on a truthy value.
	return whenever<string | undefined>(() => {
		const targetToken = toValue(token)
		if (!targetToken || currentJoinedConversation.value !== targetToken) {
			return
		}
		return targetToken
	}, callback, { immediate: true, once: true })
}
