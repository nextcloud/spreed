/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PrepareTemporaryMessagePayload } from '../utils/prepareTemporaryMessage.ts'

import { useChatExtrasStore } from '../stores/chatExtras.js'
import { prepareTemporaryMessage } from '../utils/prepareTemporaryMessage.ts'
import { useStore } from './useStore.js'

/**
 * Composable to generate temporary messages using defined in store information
 * @param context Vuex Store (to be used inside Vuex modules)
 */
export function useTemporaryMessage(context: unknown) {
	const store = context ?? useStore()
	const chatExtrasStore = useChatExtrasStore()

	/**
	 * @param payload payload for generating a temporary message
	 */
	function createTemporaryMessage(payload: PrepareTemporaryMessagePayload) {
		const parentId = chatExtrasStore.getParentIdToReply(payload.token)

		return prepareTemporaryMessage({
			...payload,
			actorId: store.getters.getActorId(),
			actorType: store.getters.getActorType(),
			actorDisplayName: store.getters.getDisplayName(),
			parent: parentId && store.getters.message(payload.token, parentId),
		})
	}

	return {
		createTemporaryMessage,
	}
}
