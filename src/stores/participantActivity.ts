/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { onScopeDispose, reactive } from 'vue'
import { useActorStore } from './actor.ts'

type TypingState = {
	expirationTimeout: ReturnType<typeof setTimeout>
}

type SpeakingState = {
	isSpeaking: boolean
	lastTimestamp: number
	totalCountedTime: number
}

type SpeakingPayload = {
	attendeeId: number
	isSpeaking: boolean
}

type RaisedHandState = {
	state: boolean
	timestamp: number | null
}

/**
 * Store for participant activity used in chat and call (typing, speaking, raised hands)
 */
export const useParticipantActivityStore = defineStore('participantActivity', () => {
	const typing = reactive<Record<string, Record<string, TypingState>>>({})
	const speaking = reactive<Record<string, SpeakingState>>({})
	const raisedHands = reactive<Record<string, RaisedHandState>>({})

	let speakingInterval: ReturnType<typeof setInterval> | null = null

	const actorStore = useActorStore()

	onScopeDispose(() => {
		purgeTypingState()
		purgeSpeakingState()
		purgeRaisedHandsState()
	})

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

	/**
	 * Purge the typing information for all conversations (called when leaving).
	 */
	function purgeTypingState() {
		for (const token in typing) {
			for (const sessionId in typing[token]) {
				clearTimeout(typing[token][sessionId].expirationTimeout)
				delete typing[token][sessionId]
			}
			delete typing[token]
		}
	}

	/**
	 * Gets the speaking information for the participant.
	 *
	 * @param attendeeId - attendee's ID for the participant in conversation.
	 */
	function getParticipantSpeakingInformation(attendeeId: SpeakingPayload['attendeeId']) {
		return speaking[attendeeId]
	}

	/**
	 * Update speaking information for a participant.
	 *
	 * @param data - the wrapping object.
	 * @param data.attendeeId - the attendee ID of the participant in conversation.
	 * @param data.isSpeaking - whether the participant is speaking or not
	 */
	function updateTimeSpeaking({ attendeeId, isSpeaking }: SpeakingPayload) {
		if (!speaking[attendeeId]) {
			return
		}

		const currentTimestamp = Date.now()
		const currentSpeakingState = speaking[attendeeId].isSpeaking

		if (!currentSpeakingState && !isSpeaking) {
			// false -> false, no updates
			return
		}

		if (currentSpeakingState) {
			// true -> false / true -> true, participant is still speaking or finished to speak, update total time
			speaking[attendeeId].totalCountedTime += (currentTimestamp - speaking[attendeeId].lastTimestamp)
		}

		// false -> true / true -> false / true -> true, update timestamp of last check / signal
		speaking[attendeeId].lastTimestamp = currentTimestamp
	}

	/**
	 * Sets the speaking status of a participant in a conversation / call.
	 *
	 * Note that "updateParticipant" should not be called to add a "speaking"
	 * property to an existing participant, as the participant would be reset
	 * when the participants are purged whenever they are fetched again.
	 * Similarly, "addParticipant" can not be called either to add a participant
	 * if it was not fetched yet but the call model reported it as being
	 * speaking, as the attendeeId would be unknown.
	 *
	 * @param data - the wrapping object.
	 * @param data.attendeeId - the attendee ID of the participant in conversation.
	 * @param data.isSpeaking - whether the participant is speaking or not
	 */
	function setSpeaking({ attendeeId, isSpeaking }: SpeakingPayload) {
		// We should update time before speaking state, to be able to check previous state
		updateTimeSpeaking({ attendeeId, isSpeaking })
		// create a dummy object for current call
		if (!speaking[attendeeId]) {
			speaking[attendeeId] = { isSpeaking, lastTimestamp: Date.now(), totalCountedTime: 0 }
		}
		speaking[attendeeId].isSpeaking = isSpeaking

		if (!speakingInterval && isSpeaking) {
			speakingInterval = setInterval(updateIntervalTimeSpeaking, 1000)
		}

		// Stop interval ticks if nobody is speaking
		if (!isSpeaking && speakingInterval
			&& Object.values(speaking).every((attendee) => !attendee.isSpeaking)) {
			clearInterval(speakingInterval)
			speakingInterval = null
		}
	}

	/**
	 * Update speaking time for all currently speaking attendees.
	 */
	function updateIntervalTimeSpeaking() {
		if (!speakingInterval) {
			return
		}

		for (const attendeeId in speaking) {
			if (speaking[attendeeId].isSpeaking) {
				updateTimeSpeaking({ attendeeId: +attendeeId, isSpeaking: true })
			}
		}
	}

	/**
	 * Purge the speaking information for recent call when local participant leaves call
	 * (including cases when the call ends for everyone).
	 */
	function purgeSpeakingState() {
		for (const attendeeId in speaking) {
			delete speaking[attendeeId]
		}

		if (speakingInterval) {
			clearInterval(speakingInterval)
			speakingInterval = null
		}
	}

	/**
	 * Get the raised hand state for the first matching sessionId.
	 *
	 * @param sessionIds - list of session IDs to look up
	 */
	function getParticipantRaisedHand(sessionIds: string[]): RaisedHandState {
		for (const sessionId of sessionIds) {
			if (raisedHands[sessionId]) {
				// note: only the raised states are stored, so no need to confirm
				return raisedHands[sessionId]
			}
		}
		return { state: false, timestamp: null }
	}

	/**
	 * Set or clear the raised hand state for a participant.
	 *
	 * @param payload - the wrapping object.
	 * @param payload.sessionId - the Nextcloud session ID of the participant.
	 * @param payload.raisedHand - the raised hand state, or false to lower the hand.
	 */
	function setParticipantHandRaised({ sessionId, raisedHand }: { sessionId: string, raisedHand: RaisedHandState | false }) {
		if (!sessionId) {
			throw new Error('Missing or empty sessionId argument in call to setParticipantHandRaised')
		}
		if (raisedHand && raisedHand.state) {
			raisedHands[sessionId] = raisedHand
		} else {
			delete raisedHands[sessionId]
		}
	}

	/**
	 * Clear all raised hand states (called when leaving a call).
	 */
	function purgeRaisedHandsState() {
		for (const sessionId in raisedHands) {
			delete raisedHands[sessionId]
		}
	}

	return {
		typing,
		externalTypingSignals,
		isSelfActorTyping,
		setTyping,
		purgeTypingState,

		speaking,
		getParticipantSpeakingInformation,
		setSpeaking,
		purgeSpeakingState,

		raisedHands,
		getParticipantRaisedHand,
		setParticipantHandRaised,
		purgeRaisedHandsState,
	}
})
