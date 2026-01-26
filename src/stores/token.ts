/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

export const useTokenStore = defineStore('token', () => {
	const token = ref<'' | (string & {})>('')
	const fileIdForToken = ref<string | null>(null)
	/**
	 * The joining of a room with the signaling server always lags
	 * behind the "joining" of it in talk's UI. For this reason we
	 * might have a window of time in which we might be in
	 * conversation B in talk's UI while still leaving conversation
	 * A in the signaling server.
	 */
	const lastJoinedConversationToken = ref<'' | (string & {})>('')
	/**
	 * The joining of a room with the signaling server might fail
	 * for various reasons. We still want to allow basic functionality
	 * (e.g. chatting and file sharing) which does not depend on it.
	 */
	const lastJoinConversationFailed = ref<boolean>(false)

	const currentConversationIsJoined = computed(() => token.value !== '' && lastJoinedConversationToken.value === token.value)
	const currentConversationIsJoinedWithoutHPB = computed(() => token.value !== '' && lastJoinConversationFailed.value)

	/**
	 * @param newToken token of active conversation
	 */
	function updateToken(newToken: string) {
		token.value = newToken
	}

	/**
	 * Coupled function to update both token and file ID
	 *
	 * @param newToken token of active conversation
	 * @param newFileId file ID of active conversation
	 */
	function updateTokenAndFileIdForToken(newToken: string, newFileId: string | null) {
		token.value = newToken
		fileIdForToken.value = newFileId
	}

	/**
	 * @param newToken token of last joined conversation
	 */
	function updateLastJoinedConversationToken(newToken: string) {
		lastJoinedConversationToken.value = newToken
		// Reset last failed conversation attempt on successful join
		lastJoinConversationFailed.value = false
	}

	/**
	 * @param newValue value of the flag for last joined conversation
	 */
	function setLastJoinConversationFailed(newValue: boolean) {
		lastJoinConversationFailed.value = newValue
	}

	return {
		token,
		fileIdForToken,
		lastJoinedConversationToken,
		lastJoinConversationFailed,
		currentConversationIsJoined,
		currentConversationIsJoinedWithoutHPB,

		updateToken,
		updateTokenAndFileIdForToken,
		updateLastJoinedConversationToken,
		setLastJoinConversationFailed,
	}
})
