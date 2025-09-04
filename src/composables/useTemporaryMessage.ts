/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PrepareTemporaryMessagePayload } from '../utils/prepareTemporaryMessage.ts'

import { useActorStore } from '../stores/actor.ts'
import { prepareTemporaryMessage } from '../utils/prepareTemporaryMessage.ts'

/**
 * Composable to generate temporary messages using defined in store information
 */
export function useTemporaryMessage() {
	const actorStore = useActorStore()

	/**
	 * @param payload payload for generating a temporary message
	 */
	function createTemporaryMessage(payload: PrepareTemporaryMessagePayload) {
		return prepareTemporaryMessage({
			...payload,
			actorId: actorStore.actorId ?? '',
			actorType: actorStore.actorType ?? '',
			actorDisplayName: actorStore.displayName,
		})
	}

	return {
		createTemporaryMessage,
	}
}
