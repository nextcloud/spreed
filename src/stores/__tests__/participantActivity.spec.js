/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { useActorStore } from '../actor.ts'
import { useParticipantActivityStore } from '../participantActivity.ts'

describe('participantActivityStore', () => {
	let participantActivityStore
	let actorStore

	beforeEach(() => {
		setActivePinia(createPinia())
		participantActivityStore = useParticipantActivityStore()
		actorStore = useActorStore()
	})

	describe('typing', () => {
		const TOKEN = 'XXTOKENXX'

		it('adds typing signal for participant', () => {
			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'session-id-1', isTyping: true })

			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual(['session-id-1'])
		})

		it('removes typing signal when participant stops typing', () => {
			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'session-id-1', isTyping: true })
			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'session-id-1', isTyping: false })

			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual([])
		})

		it('excludes current actor session from external typing signals', () => {
			actorStore.setCurrentParticipant({ sessionId: 'local-session-id', attendeeId: 1 })

			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'local-session-id', isTyping: true })
			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'remote-session-id', isTyping: true })

			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual(['remote-session-id'])
		})

		it('detects self typing via isSelfActorTyping', () => {
			actorStore.setCurrentParticipant({ sessionId: 'local-session-id', attendeeId: 1 })

			expect(participantActivityStore.isSelfActorTyping(TOKEN)).toBe(false)

			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'local-session-id', isTyping: true })

			expect(participantActivityStore.isSelfActorTyping(TOKEN)).toBe(true)
		})

		it('automatically expires typing signal after 15 seconds', () => {
			vi.useFakeTimers()

			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'session-id-1', isTyping: true })
			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual(['session-id-1'])

			vi.advanceTimersByTime(15_000)
			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual([])
		})

		it('purges all typing entries and clears timeouts', () => {
			vi.useFakeTimers()

			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'session-id-1', isTyping: true })
			participantActivityStore.setTyping({ token: TOKEN, sessionId: 'session-id-2', isTyping: true })

			participantActivityStore.purgeTypingState()

			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual([])
			vi.advanceTimersByTime(15_000)
			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual([])
		})

		it('does nothing when there is no typing state to purge', () => {
			expect(() => participantActivityStore.purgeTypingState()).not.toThrow()
			expect(participantActivityStore.externalTypingSignals(TOKEN)).toEqual([])
		})

		/* Additional test cases located in src/utils/SignalingTypingHandler.spec.js */
	})

	describe('raised hand', () => {
		it('returns raised hand state for single session id', () => {
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-1',
				raisedHand: { state: true, timestamp: 1 },
			})
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 2 },
			})

			expect(participantActivityStore.getParticipantRaisedHand(['session-id-1']))
				.toStrictEqual({ state: true, timestamp: 1 })

			expect(participantActivityStore.getParticipantRaisedHand(['session-id-2']))
				.toStrictEqual({ state: true, timestamp: 2 })

			expect(participantActivityStore.getParticipantRaisedHand(['session-id-another']))
				.toStrictEqual({ state: false, timestamp: null })
		})

		it('returns false state after hand is lowered', () => {
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 1 },
			})
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-2',
				raisedHand: { state: false, timestamp: 3 },
			})

			expect(participantActivityStore.getParticipantRaisedHand(['session-id-2']))
				.toStrictEqual({ state: false, timestamp: null })
		})

		it('clears all raised hand entries on purgeRaisedHandsState', () => {
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-1',
				raisedHand: { state: true, timestamp: 1 },
			})
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 2 },
			})

			participantActivityStore.purgeRaisedHandsState()

			expect(participantActivityStore.getParticipantRaisedHand(['session-id-1']))
				.toStrictEqual({ state: false, timestamp: null })
			expect(participantActivityStore.getParticipantRaisedHand(['session-id-2']))
				.toStrictEqual({ state: false, timestamp: null })
		})

		it('returns first matching session from list of session ids', () => {
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 1 },
			})
			participantActivityStore.setParticipantHandRaised({
				sessionId: 'session-id-3',
				raisedHand: { state: true, timestamp: 2 },
			})

			expect(participantActivityStore.getParticipantRaisedHand(['session-id-1', 'session-id-2', 'session-id-3']))
				.toStrictEqual({ state: true, timestamp: 1 })
		})

		it('throws if sessionId is empty', () => {
			expect(() => participantActivityStore.setParticipantHandRaised({
				sessionId: '',
				raisedHand: { state: true, timestamp: 1 },
			}))
				.toThrow('Missing or empty sessionId argument in call to setParticipantHandRaised')
		})
	})

	describe('speaking', () => {
		it('creates speaking entry on first setSpeaking call', () => {
			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: true })

			expect(participantActivityStore.getParticipantSpeakingInformation(1)).toMatchObject({
				isSpeaking: true,
				totalCountedTime: 0,
			})
		})

		it('returns undefined for unknown attendeeId', () => {
			expect(participantActivityStore.getParticipantSpeakingInformation(999)).toBeUndefined()
		})

		it('does not accumulate time on false to false transition', () => {
			vi.useFakeTimers()

			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: false })
			vi.advanceTimersByTime(5_000)
			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: false })

			expect(participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime).toBe(0)
		})

		it('accumulates time on interval ticks while speaking', () => {
			vi.useFakeTimers()

			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: true })
			expect(participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime).toBe(0)

			vi.advanceTimersByTime(1_000)
			expect(participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime).toBe(1000)

			vi.advanceTimersByTime(1_000)
			expect(participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime).toBe(2000)
		})

		it('accumulates time when participant stops speaking', () => {
			vi.useFakeTimers()

			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: true })
			vi.advanceTimersByTime(3_000)
			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: false })

			expect(participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime).toBe(3000)
			expect(participantActivityStore.getParticipantSpeakingInformation(1).isSpeaking).toBe(false)
		})

		it('stops interval when last participant stops speaking', () => {
			vi.useFakeTimers()

			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: true })
			vi.advanceTimersByTime(1_000)
			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: false })

			const totalAfterStop = participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime

			vi.advanceTimersByTime(5_000)

			expect(participantActivityStore.getParticipantSpeakingInformation(1).totalCountedTime).toBe(totalAfterStop)
		})

		it('keeps interval running when one of multiple participants stops speaking', () => {
			vi.useFakeTimers()

			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: true })
			participantActivityStore.setSpeaking({ attendeeId: 2, isSpeaking: true })

			vi.advanceTimersByTime(1_000)
			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: false })

			vi.advanceTimersByTime(1_000)

			expect(participantActivityStore.getParticipantSpeakingInformation(2).totalCountedTime).toBeGreaterThan(1000)
		})

		it('purges all speaking entries and stops interval', () => {
			vi.useFakeTimers()

			participantActivityStore.setSpeaking({ attendeeId: 1, isSpeaking: true })
			participantActivityStore.setSpeaking({ attendeeId: 2, isSpeaking: true })
			participantActivityStore.purgeSpeakingState()

			expect(participantActivityStore.getParticipantSpeakingInformation(1)).toBeUndefined()
			expect(participantActivityStore.getParticipantSpeakingInformation(2)).toBeUndefined()

			// Advance time to confirm the interval was stopped and creates no new entries
			vi.advanceTimersByTime(5_000)
			expect(participantActivityStore.getParticipantSpeakingInformation(1)).toBeUndefined()
		})
	})
})
