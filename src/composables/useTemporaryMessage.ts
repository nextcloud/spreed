/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Store } from 'vuex'
import type { PrepareTemporaryMessagePayload } from '../utils/prepareTemporaryMessage.ts'

import { useStore } from 'vuex'
import { useActorStore } from '../stores/actor.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'
import { prepareTemporaryMessage } from '../utils/prepareTemporaryMessage.ts'

/**
 * Composable to generate temporary messages using defined in store information
 * @param context Vuex Store (to be used inside Vuex modules)
 */
export function useTemporaryMessage(context: Store<unknown>) {
	const store = context ?? useStore()
	const chatExtrasStore = useChatExtrasStore()
	const actorStore = useActorStore()

	/**
	 * @param payload payload for generating a temporary message
	 */
	function createTemporaryMessage(payload: PrepareTemporaryMessagePayload) {
		const parentId = chatExtrasStore.getParentIdToReply(payload.token)

		return prepareTemporaryMessage({
			...payload,
			actorId: actorStore.actorId ?? '',
			actorType: actorStore.actorType ?? '',
			actorDisplayName: actorStore.displayName,
			parent: parentId && store.getters.message(payload.token, parentId),
		})
	}

	return {
		createTemporaryMessage,
	}
}
