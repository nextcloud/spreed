/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { reactive } from 'vue'
import { useActorStore } from './actor.ts'

type TypingState = {
	expirationTimeout: ReturnType<typeof setTimeout>
}

/**
 * Store for signaling state used in chat and call (typing, speaking, raised hands)
 */
export const useSignalingStateStore = defineStore('signalingState', () => {
	const typing = reactive<Record<string, Record<string, TypingState>>>({})

	const actorStore = useActorStore()

	/**
	 * Get the array of external session ids for a conversation (excluding current user)
	 *
	 * @param token - conversation token
	 */
	function externalTypingSignals(token: string): string[] {
		if (!typing[token]) {
			return []
		}

		return Object.keys(typing[token]).filter((sessionId) => actorStore.sessionId !== sessionId)
	}

	/**
	 * Check whether the current actor is typing in a conversation
	 *
	 * @param token - conversation token
	 */
	function isSelfActorTyping(token: string): boolean {
		if (!typing[token]) {
			return false
		}
		return Object.keys(typing[token]).some((sessionId) => actorStore.sessionId === sessionId)
	}

	/**
	 * Sets the typing status of a participant in a conversation.
	 *
	 * Note that "updateParticipant" should not be called to add a "typing"
	 * property to an existing participant, as the participant would be reset
	 * when the participants are purged whenever they are fetched again.
	 * Similarly, "addParticipant" can not be called either to add a participant
	 * if it was not fetched yet but the signaling reported it as being typing,
	 * as the attendeeId would be unknown.
	 *
	 * @param payload - the wrapping object.
	 * @param payload.token - the conversation that the participant is typing in.
	 * @param payload.sessionId - the Nextcloud session ID of the participant.
	 * @param payload.isTyping - whether the participant is typing or not.
	 */
	function setTyping({ token, sessionId, isTyping }: { token: string, sessionId: string, isTyping: boolean }) {
		if (!typing[token]) {
			typing[token] = {}
		}

		if (typing[token][sessionId]) {
			clearTimeout(typing[token][sessionId].expirationTimeout)
		}

		if (isTyping) {
			const expirationTimeout = setTimeout(() => {
				// If updated 'typing' signal doesn't come in last 15s, remove it from store
				setTyping({ token, sessionId, isTyping: false })
			}, 15000)
			typing[token][sessionId] = { expirationTimeout }
		} else {
			delete typing[token][sessionId]
		}
	}

	return {
		externalTypingSignals,
		isSelfActorTyping,
		setTyping,
	}
})
