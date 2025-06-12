/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useTokenStore = defineStore('token', () => {
	const token = ref<string>('')
	// FIXME doesn't really belong to token store
	const fileIdForToken = ref<string | null>(null)
	/**
	 * The joining of a room with the signaling server always lags
	 * behind the "joining" of it in talk's UI. For this reason we
	 * might have a window of time in which we might be in
	 * conversation B in talk's UI while still leaving conversation
	 * A in the signaling server.
	 */
	const lastJoinedConversationToken = ref<string | null>(null)

	const currentConversationIsJoined = computed(() => lastJoinedConversationToken.value === token.value)

	/**
	 * @param newToken token of active conversation
	 */
	function updateToken(newToken: string) {
		token.value = newToken
	}

	/**
	 * @param newFileId file ID of active conversation
	 */
	function updateFileIdForToken(newFileId: string | null) {
		fileIdForToken.value = newFileId
	}

	/**
	 * @param newToken token of last joined conversation
	 */
	function updateLastJoinedConversationToken(newToken: string) {
		lastJoinedConversationToken.value = newToken
	}

	return {
		token,
		fileIdForToken,
		lastJoinedConversationToken,
		currentConversationIsJoined,

		updateToken,
		updateFileIdForToken,
		updateLastJoinedConversationToken,
	}
})
