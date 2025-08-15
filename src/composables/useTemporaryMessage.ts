/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Store } from 'vuex'
import type { PrepareTemporaryMessagePayload } from '../utils/prepareTemporaryMessage.ts'

import { useStore } from 'vuex'
import { useActorStore } from '../stores/actor.ts'
import { useChatExtrasStore } from '../stores/chatExtras.ts'
import { prepareTemporaryMessage } from '../utils/prepareTemporaryMessage.ts'
import { useGetThreadId } from './useGetThreadId.ts'

/**
 * Composable to generate temporary messages using defined in store information
 * @param context Vuex Store (to be used inside Vuex modules)
 */
export function useTemporaryMessage(context: Store<unknown>) {
	const store = context ?? useStore()
	const chatExtrasStore = useChatExtrasStore()
	const actorStore = useActorStore()
	const threadId = useGetThreadId()

	/**
	 * @param payload payload for generating a temporary message
	 */
	function createTemporaryMessage(payload: PrepareTemporaryMessagePayload) {
		const parentId = chatExtrasStore.getParentIdToReply(payload.token)
		const parent = parentId
			? store.getters.message(payload.token, parentId)
			: (threadId.value ? chatExtrasStore.getThread(payload.token, threadId.value)?.first : undefined)

		return prepareTemporaryMessage({
			...payload,
			actorId: actorStore.actorId ?? '',
			actorType: actorStore.actorType ?? '',
			actorDisplayName: actorStore.displayName,
			parent,
			threadId: threadId.value ? threadId.value : undefined,
			isThread: threadId.value ? true : undefined,
		})
	}

	return {
		createTemporaryMessage,
	}
}
