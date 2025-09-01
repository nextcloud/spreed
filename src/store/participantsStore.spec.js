/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import { PARTICIPANT } from '../constants.ts'
import {
	joinCall,
	leaveCall,
} from '../services/callsService.ts'
import { fetchConversation } from '../services/conversationsService.ts'
import { EventBus } from '../services/EventBus.ts'
import {
	demoteFromModerator,
	fetchParticipants,
	grantAllPermissionsToParticipant,
	joinConversation,
	leaveConversation,
	promoteToModerator,
	removeAllPermissionsFromParticipant,
	removeAttendeeFromConversation,
	removeCurrentUserFromConversation,
	resendInvitations,
} from '../services/participantsService.js'
import SessionStorage from '../services/SessionStorage.js'
import { useActorStore } from '../stores/actor.ts'
import { useGuestNameStore } from '../stores/guestName.js'
import { useSessionStore } from '../stores/session.ts'
import { useTokenStore } from '../stores/token.ts'
import { generateOCSErrorResponse, generateOCSResponse } from '../test-helpers.js'
import participantsStore from './participantsStore.js'
import storeConfig from './storeConfig.js'

vi.mock('../services/participantsService', () => ({
	promoteToModerator: vi.fn(),
	demoteFromModerator: vi.fn(),
	removeAttendeeFromConversation: vi.fn(),
	resendInvitations: vi.fn(),
	joinConversation: vi.fn(),
	leaveConversation: vi.fn(),
	fetchParticipants: vi.fn(),
	removeCurrentUserFromConversation: vi.fn(),
	grantAllPermissionsToParticipant: vi.fn(),
	removeAllPermissionsFromParticipant: vi.fn(),
}))
vi.mock('../services/callsService', () => ({
	joinCall: vi.fn(),
	leaveCall: vi.fn(),
}))
vi.mock('../services/conversationsService', () => ({
	fetchConversation: vi.fn(),
}))
vi.mock('../stores/callView', () => ({
	useCallViewStore: vi.fn(() => ({
		handleJoinCall: vi.fn(),
	})),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.spyOn(EventBus, 'emit')

describe('participantsStore', () => {
	const TOKEN = 'XXTOKENXX'
	let testStoreConfig = null
	let store = null
	let guestNameStore = null
	let actorStore
	let tokenStore

	beforeEach(() => {
		setActivePinia(createPinia())
		guestNameStore = useGuestNameStore()
		actorStore = useActorStore()
		tokenStore = useTokenStore()

		testStoreConfig = cloneDeep(participantsStore)
		store = createStore(testStoreConfig)
	})

	afterEach(() => {
		store = null
		vi.clearAllMocks()
	})

	describe('participant list', () => {
		test('adds participant', () => {
			store.dispatch('addParticipant', {
				token: TOKEN, participant: { attendeeId: 1 },
			})

			expect(store.getters.participantsList(TOKEN)).toStrictEqual([
				{ attendeeId: 1 },
			])
		})

		test('adds participant once', () => {
			store.dispatch('addParticipantOnce', {
				token: TOKEN, participant: { attendeeId: 1 },
			})

			// does not add again
			store.dispatch('addParticipantOnce', {
				token: TOKEN, participant: { attendeeId: 1 },
			})

			expect(store.getters.participantsList(TOKEN)).toStrictEqual([
				{ attendeeId: 1 },
			])
		})

		test('does nothing when removing non-existing participant', () => {
			store.dispatch('removeParticipant', {
				token: TOKEN,
				attendeeId: 1,
			})

			expect(removeAttendeeFromConversation).not.toHaveBeenCalled()
		})

		test('removes participant', async () => {
			store.dispatch('addParticipant', {
				token: TOKEN, participant: { attendeeId: 1 },
			})

			removeAttendeeFromConversation.mockResolvedValue()

			await store.dispatch('removeParticipant', {
				token: TOKEN,
				attendeeId: 1,
			})

			expect(removeAttendeeFromConversation).toHaveBeenCalledWith(TOKEN, 1)

			expect(store.getters.participantsList(TOKEN)).toStrictEqual([])
		})

		test('purges participant list', () => {
			store.dispatch('addParticipant', {
				token: TOKEN, participant: { attendeeId: 1 },
			})
			store.dispatch('addParticipant', {
				token: 'token-2', participant: { attendeeId: 2 },
			})

			store.dispatch('purgeParticipantsStore', TOKEN)

			expect(store.getters.participantsList(TOKEN)).toStrictEqual([])
			expect(store.getters.participantsList('token-2')).toStrictEqual([
				{ attendeeId: 2 },
			])
		})

		test('find participant by attendee id', () => {
			const attendee = { attendeeId: 1 }
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: attendee,
			})

			expect(store.getters.findParticipant(
				TOKEN,
				{ attendeeId: 1 },
			)).toStrictEqual(attendee)
			expect(store.getters.findParticipant(
				TOKEN,
				{ attendeeId: 42 },
			)).toBe(null)
		})

		test('find participant by actor', () => {
			const attendee = { actorType: 'users', actorId: 'admin' }
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: attendee,
			})

			expect(store.getters.findParticipant(
				TOKEN,
				{ actorType: 'users', actorId: 'admin' },
			)).toStrictEqual(attendee)
			expect(store.getters.findParticipant(
				TOKEN,
				{ actorType: 'groups', actorId: 'admin' }, // Actor type mismatch
			)).toBe(null)
			expect(store.getters.findParticipant(
				TOKEN,
				{ actorType: 'users', actorId: 'test1' }, // Actor id mismatch
			)).toBe(null)
		})

		test('find participant by session', () => {
			const attendee = { sessionIds: ['1234567890'] }
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: attendee,
			})

			expect(store.getters.findParticipant(
				TOKEN,
				{ sessionId: '1234567890' },
			)).toStrictEqual(attendee)
			expect(store.getters.findParticipant(
				TOKEN,
				{ sessionId: 'abcdefghi' },
			)).toBe(null)
		})

		test('updates participant data', () => {
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: {
					attendeeId: 1,
					statusMessage: 'status-message',
				},
			})

			store.dispatch('updateUser', {
				token: TOKEN,
				participantIdentifier: { attendeeId: 1 },
				updatedData: {
					statusMessage: 'new-status-message',
				},
			})

			expect(store.getters.participantsList(TOKEN)).toStrictEqual([
				{
					attendeeId: 1,
					statusMessage: 'new-status-message',
				},
			])
		})

		test('updates participant session id', () => {
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: {
					attendeeId: 1,
					sessionId: 'session-id-1',
					inCall: PARTICIPANT.CALL_FLAG.IN_CALL,
				},
			})

			store.dispatch('updateSessionId', {
				token: TOKEN,
				participantIdentifier: { attendeeId: 1 },
				sessionId: 'new-session-id',
			})

			expect(store.getters.participantsList(TOKEN)).toStrictEqual([
				{
					attendeeId: 1,
					sessionId: 'new-session-id',
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				},
			])
		})

		describe('promote to moderator', () => {
			test('does nothing when promoting not found attendee', () => {
				store.dispatch('promoteToModerator', {
					token: TOKEN,
					attendeeId: 1,
				})

				expect(promoteToModerator).not.toHaveBeenCalled()
			})

			/**
			 * @param {number} participantType Participant type before the action
			 * @param {number} expectedParticipantType Expected participant type after the action
			 */
			async function testPromoteModerator(participantType, expectedParticipantType) {
				promoteToModerator.mockResolvedValue()

				store.dispatch('addParticipant', {
					token: TOKEN,
					participant: {
						attendeeId: 1,
						participantType,
					},
				})
				await store.dispatch('promoteToModerator', {
					token: TOKEN,
					attendeeId: 1,
				})
				expect(promoteToModerator)
					.toHaveBeenCalledWith(TOKEN, { attendeeId: 1 })

				expect(store.getters.participantsList(TOKEN)).toStrictEqual([
					{
						attendeeId: 1,
						participantType: expectedParticipantType,
					},
				])
			}

			test('promotes given user to moderator', async () => {
				await testPromoteModerator(PARTICIPANT.TYPE.USER, PARTICIPANT.TYPE.MODERATOR)
			})
			test('promotes given guest to guest moderator', async () => {
				await testPromoteModerator(PARTICIPANT.TYPE.GUEST, PARTICIPANT.TYPE.GUEST_MODERATOR)
			})
		})

		describe('demotes from moderator', () => {
			test('does nothing when demoting not found attendee', () => {
				store.dispatch('demoteFromModerator', {
					token: TOKEN,
					attendeeId: 1,
				})

				expect(demoteFromModerator).not.toHaveBeenCalled()
			})

			/**
			 * @param {number} participantType Participant type before the action
			 * @param {number} expectedParticipantType Expected participant type after the action
			 */
			async function testDemoteModerator(participantType, expectedParticipantType) {
				promoteToModerator.mockResolvedValue()

				store.dispatch('addParticipant', {
					token: TOKEN,
					participant: {
						attendeeId: 1,
						participantType,
					},
				})
				await store.dispatch('demoteFromModerator', {
					token: TOKEN,
					attendeeId: 1,
				})
				expect(demoteFromModerator)
					.toHaveBeenCalledWith(TOKEN, { attendeeId: 1 })

				expect(store.getters.participantsList(TOKEN)).toStrictEqual([
					{
						attendeeId: 1,
						participantType: expectedParticipantType,
					},
				])
			}

			test('demotes given moderator to user', async () => {
				await testDemoteModerator(PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.USER)
			})
			test('promotes given guest to guest moderator', async () => {
				await testDemoteModerator(PARTICIPANT.TYPE.GUEST_MODERATOR, PARTICIPANT.TYPE.GUEST)
			})
		})
	})

	describe('peers list', () => {
		test('adds peer', () => {
			store.dispatch('addPeer', {
				token: TOKEN,
				peer: { sessionId: 'session-id-1', peerData: 1 },
			})

			expect(store.getters.getPeer(TOKEN, 'session-id-1'))
				.toStrictEqual({ sessionId: 'session-id-1', peerData: 1 })

			expect(store.getters.getPeer(TOKEN, 'session-id-2'))
				.toStrictEqual({})
		})

		test('purges peers store', () => {
			store.dispatch('addPeer', {
				token: TOKEN,
				peer: { sessionId: 'session-id-1', peerData: 1 },
			})
			store.dispatch('addPeer', {
				token: 'token-2',
				peer: { sessionId: 'session-id-2', peerData: 1 },
			})

			store.dispatch('purgePeersStore', TOKEN)

			expect(store.getters.getPeer(TOKEN, 'session-id-1'))
				.toStrictEqual({})
			expect(store.getters.getPeer('token-2', 'session-id-2'))
				.toStrictEqual({ sessionId: 'session-id-2', peerData: 1 })
		})
	})

	describe('fetch participants', () => {
		test('populates store for the fetched conversation', async () => {
			// Arrange
			const payload = [{
				attendeeId: 1,
				sessionIds: ['session-id-1'],
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}]

			const sessionStore = useSessionStore()
			sessionStore.addSession({
				attendeeId: undefined,
				token: TOKEN,
				signalingSessionId: 'signaling-session-id-1',
				sessionId: 'session-id-1',
				inCall: undefined,
			})

			fetchParticipants.mockResolvedValue(generateOCSResponse({ payload }))

			// Act
			await store.dispatch('fetchParticipants', { token: TOKEN })

			// Assert
			expect(store.getters.participantsList(TOKEN)).toMatchObject(payload)
			expect(sessionStore.getSession('signaling-session-id-1')).toMatchObject({
				attendeeId: 1,
				sessionId: 'session-id-1',
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			})
		})

		test('populates store for the fetched conversation', async () => {
			// Arrange
			const payloadFirst = [
				{ attendeeId: 1, actorType: 'users', sessionIds: ['session-id-1'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED }, // delete
				{ attendeeId: 2, actorType: 'users', sessionIds: ['session-id-2-1', 'session-id-2-2'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED },
				{ attendeeId: 3, actorType: 'users', sessionIds: ['session-id-3'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED },
				{ attendeeId: 4, actorType: 'users', sessionIds: ['session-id-4'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED },
			]
			const payloadSecond = [
				{ attendeeId: 2, actorType: 'users', sessionIds: ['session-id-2-2', 'session-id-2-1'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED }, // change
				{ attendeeId: 3, actorType: 'users', sessionIds: ['session-id-3'], inCall: PARTICIPANT.CALL_FLAG.IN_CALL }, // change
				{ attendeeId: 4, actorType: 'users', sessionIds: ['session-id-4'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED, status: 'online' }, // change
				{ attendeeId: 5, actorType: 'guests', sessionIds: ['session-id-5'], inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED }, // add
			]
			fetchParticipants
				.mockResolvedValueOnce(generateOCSResponse({
					headers: { 'x-nextcloud-has-user-statuses': true },
					payload: payloadFirst,
				}))
				.mockResolvedValueOnce(generateOCSResponse({
					headers: { 'x-nextcloud-has-user-statuses': true },
					payload: payloadSecond,
				}))

			// Act
			await store.dispatch('fetchParticipants', { token: TOKEN })
			await store.dispatch('fetchParticipants', { token: TOKEN })

			// Assert
			expect(emit).toHaveBeenCalledTimes(5) // 4 added users and 1 status changed
			expect(store.getters.participantsList(TOKEN)).toMatchObject(payloadSecond)
		})

		test('saves a guest name from response', async () => {
			// Arrange
			const payload = [{
				attendeeId: 1,
				sessionIds: ['guest-session-id'],
				actorId: 'guest-actor-id',
				displayName: 'guest-name',
				participantType: PARTICIPANT.TYPE.GUEST,
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}]
			const id = Hex.stringify(SHA1('guest-session-id'))

			fetchParticipants.mockResolvedValue(generateOCSResponse({ payload }))

			// Act
			await store.dispatch('fetchParticipants', { token: TOKEN })

			// Assert
			expect(guestNameStore.getGuestName(TOKEN, id)).toBe('guest-name')
		})

		test('emits an user status update', async () => {
			// Arrange
			const payload = [{
				attendeeId: 1,
				actorId: 'actor-id',
				displayName: 'guest-name',
				actorType: 'users',
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				status: 'status',
				statusMessage: 'statusMessage',
				statusIcon: 'statusIcon',
				statusClearAt: 'statusClearAt',
			}]

			fetchParticipants.mockResolvedValue(generateOCSResponse({
				headers: { 'x-nextcloud-has-user-statuses': true },
				payload,
			}))

			// Act
			await store.dispatch('fetchParticipants', { token: TOKEN })

			// Assert
			expect(emit).toHaveBeenCalledWith(
				'user_status:status.updated',
				{
					clearAt: 'statusClearAt',
					icon: 'statusIcon',
					message: 'statusMessage',
					status: 'status',
					userId: 'actor-id',
				},
			)
		})

		test('updates conversation if fail to fetch participants', async () => {
			// Arrange
			testStoreConfig = cloneDeep(storeConfig)
			store = createStore(testStoreConfig)
			fetchParticipants.mockRejectedValue(generateOCSErrorResponse({
				status: 403,
				payload: [],
			}))
			fetchConversation.mockResolvedValue(generateOCSResponse({
				payload: {},
			}))
			// Act
			await store.dispatch('fetchParticipants', { token: TOKEN })

			// Assert
			expect(fetchConversation).toHaveBeenCalled()
		})

		test('cancels old request', async () => {
			// Arrange
			const payload = [{
				attendeeId: 1,
				sessionId: 'session-id-1',
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}]
			fetchParticipants.mockResolvedValue(generateOCSResponse({ payload }))

			// Act
			store.dispatch('fetchParticipants', { token: TOKEN })
			await store.dispatch('fetchParticipants', { token: TOKEN })

			// Assert
			expect(fetchParticipants).toHaveBeenCalledTimes(2)
			expect(fetchParticipants).toHaveBeenNthCalledWith(1, TOKEN, { cancelToken: { promise: expect.anything(), reason: expect.anything() } })
			expect(fetchParticipants).toHaveBeenNthCalledWith(2, TOKEN, { cancelToken: { promise: expect.anything() } })
		})
	})

	describe('call handling', () => {
		const actualFlags = PARTICIPANT.CALL_FLAG.WITH_AUDIO
		const flags = PARTICIPANT.CALL_FLAG.WITH_AUDIO | PARTICIPANT.CALL_FLAG.WITH_VIDEO

		beforeEach(async () => {
			vi.useFakeTimers()
			testStoreConfig.getters.conversation = () => vi.fn().mockReturnValue({
				token: TOKEN,
				type: 3,
			})
			store = createStore(testStoreConfig)
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: {
					attendeeId: 1,
					sessionId: 'session-id-1',
					participantType: PARTICIPANT.TYPE.USER,
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				},
			})

			// Setup joinCall and leaveCall mock behavior
			joinCall.mockResolvedValue(actualFlags)
			leaveCall.mockResolvedValue()

			await store.dispatch('joinCall', {
				token: TOKEN,
				participantIdentifier: {
					attendeeId: 1,
					sessionId: 'session-id-1',
				},
				flags,
				silent: false,
				recordingConsent: false,
			})
		})

		afterEach(() => {
			vi.clearAllTimers()
		})

		const assertInitialCallState = () => {
			expect(joinCall).toHaveBeenCalledWith(TOKEN, flags, false, false, undefined)
			EventBus.emit('signaling-join-call', [TOKEN, actualFlags])
			expect(store.getters.isInCall(TOKEN)).toBe(true)
			expect(store.getters.isConnecting(TOKEN)).toBe(true)
			expect(store.getters.isJoiningCall(TOKEN)).toBe(false)
			expect(store.getters.participantsList(TOKEN)).toStrictEqual([{
				attendeeId: 1,
				sessionId: 'session-id-1',
				inCall: actualFlags,
				participantType: PARTICIPANT.TYPE.USER,
			}])
		}

		const finishConnectingWithUsers = (event, payload) => {
			EventBus.emit(event, [payload])
			expect(store.getters.isInCall(TOKEN)).toBe(true)
			expect(store.getters.isConnecting(TOKEN)).toBe(false)
			expect(store.getters.isJoiningCall(TOKEN)).toBe(false)
		}

		const emitFailureAndAssert = (reason) => {
			EventBus.emit('signaling-join-call-failed', [TOKEN, reason])
			expect(store.getters.isInCall(TOKEN)).toBe(false)
			expect(store.getters.isConnecting(TOKEN)).toBe(false)
			expect(store.getters.isJoiningCall(TOKEN)).toBe(false)
			expect(store.getters.connectionFailed(TOKEN)).toBe(reason)
		}

		const assertParticipantsListReceivedBeforeJoiningCall = (event, payload) => {
			EventBus.emit(event, [payload])
			expect(store.getters.isJoiningCall(TOKEN)).toBe(true)
			expect(store.getters.isConnecting(TOKEN)).toBe(true)
			expect(store.getters.isInCall(TOKEN)).toBe(false)
			// join call
			EventBus.emit('signaling-join-call', [TOKEN, actualFlags])
			expect(store.getters.isJoiningCall(TOKEN)).toBe(false)
			expect(store.getters.isConnecting(TOKEN)).toBe(false)
			expect(store.getters.isInCall(TOKEN)).toBe(true)
		}

		test('joins call with internal signaling', async () => {
			assertInitialCallState()
			finishConnectingWithUsers('signaling-users-in-room', [{
				attendeeId: 1,
				sessionId: 'session-id-1',
				inCall: actualFlags,
				participantType: PARTICIPANT.TYPE.USER,
			}])
		})

		test('joins call with external signaling', async () => {
			assertInitialCallState()
			finishConnectingWithUsers('signaling-users-changed', [{
				attendeeId: 1,
				nextcloudSessionId: 'session-id-1',
				inCall: actualFlags,
				participantType: PARTICIPANT.TYPE.USER,
			}])
		})

		test('joins call but it fails', async () => {
			assertInitialCallState()
			emitFailureAndAssert('error reason')
		})

		test('trying to join call but it fails', async () => {
			emitFailureAndAssert('error reason')
		})

		test('joins call but it does not receive participants list', async () => {
			assertInitialCallState()
			// No other signaling event, simulates a missing participant list
			expect(store.getters.isInCall(TOKEN)).toBe(true)
			expect(store.getters.isConnecting(TOKEN)).toBe(true)

			// Trigger fallback after delay
			vi.advanceTimersByTime(10000)
			expect(store.getters.isInCall(TOKEN)).toBe(true)
			expect(store.getters.isConnecting(TOKEN)).toBe(false)
		})

		test('joins call but it has already received participants list from internal signaling', async () => {
			assertParticipantsListReceivedBeforeJoiningCall('signaling-users-in-room', [{
				attendeeId: 1,
				sessionId: 'session-id-1',
				inCall: actualFlags,
				participantType: PARTICIPANT.TYPE.USER,
			}])
		})

		test('joins call but it has already received participants list from external signaling', async () => {
			assertParticipantsListReceivedBeforeJoiningCall('signaling-users-changed', [{
				attendeeId: 1,
				nextcloudSessionId: 'session-id-1',
				inCall: actualFlags,
				participantType: PARTICIPANT.TYPE.USER,
			}])
		})

		test('leaves call', async () => {
			// Act
			await store.dispatch('leaveCall', {
				token: TOKEN,
				participantIdentifier: {
					attendeeId: 1,
					sessionId: 'session-id-1',
				},
			})

			// Assert
			expect(leaveCall).toHaveBeenCalledWith(TOKEN, false)
			expect(store.getters.isInCall(TOKEN)).toBe(false)
			expect(store.getters.isConnecting(TOKEN)).toBe(false)
			expect(store.getters.participantsList(TOKEN)).toStrictEqual([
				{
					attendeeId: 1,
					sessionId: 'session-id-1',
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
					participantType: PARTICIPANT.TYPE.USER,
				},
			])
		})

		describe('raised hand', () => {
			test('get whether participants raised hands with single session id', () => {
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-1',
					raisedHand: { state: true, timestamp: 1 },
				})
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-2',
					raisedHand: { state: true, timestamp: 2 },
				})

				expect(store.getters.getParticipantRaisedHand(['session-id-1']))
					.toStrictEqual({ state: true, timestamp: 1 })

				expect(store.getters.getParticipantRaisedHand(['session-id-2']))
					.toStrictEqual({ state: true, timestamp: 2 })

				expect(store.getters.getParticipantRaisedHand(['session-id-another']))
					.toStrictEqual({ state: false, timestamp: null })
			})

			test('get raised hands after lowering', () => {
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-2',
					raisedHand: { state: true, timestamp: 1 },
				})
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-2',
					raisedHand: { state: false, timestamp: 3 },
				})

				expect(store.getters.getParticipantRaisedHand(['session-id-2']))
					.toStrictEqual({ state: false, timestamp: null })
			})

			test('clears raised hands state after leaving call', async () => {
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-2',
					raisedHand: { state: true, timestamp: 1 },
				})
				await store.dispatch('leaveCall', {
					token: TOKEN,
					participantIdentifier: {
						attendeeId: 1,
						sessionId: 'session-id-1',
					},
				})

				expect(store.getters.getParticipantRaisedHand(['session-id-2']))
					.toStrictEqual({ state: false, timestamp: null })
			})

			test('get raised hands with multiple session ids only returns first found', () => {
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-2',
					raisedHand: { state: true, timestamp: 1 },
				})
				store.dispatch('setParticipantHandRaised', {
					sessionId: 'session-id-3',
					raisedHand: { state: true, timestamp: 1 },
				})

				expect(store.getters.getParticipantRaisedHand(['session-id-1', 'session-id-2', 'session-id-3']))
					.toStrictEqual({ state: true, timestamp: 1 })
			})
		})
	})

	test('resends invitations', async () => {
		resendInvitations.mockResolvedValue()

		await store.dispatch('resendInvitations', {
			token: TOKEN,
			attendeeId: 1,
		})

		expect(resendInvitations).toHaveBeenCalledWith(TOKEN, 1)
	})

	describe('joining conversation', () => {
		let participantData
		let joinedConversationEventMock

		beforeEach(() => {
			SessionStorage.clear()

			tokenStore.token = TOKEN

			joinedConversationEventMock = vi.fn()
			EventBus.once('joined-conversation', joinedConversationEventMock)

			participantData = {
				actorId: 'actor-id',
				sessionId: 'session-id-1',
				participantType: PARTICIPANT.TYPE.USER,
				attendeeId: 1,
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}

			actorStore.setCurrentParticipant({ ...participantData, attendeeId: 1 })

			testStoreConfig.actions.addConversation = vi.fn().mockImplementation((context) => {
				// needed for the updateSessionId call which requires this
				context.dispatch('addParticipantOnce', {
					token: TOKEN, participant: participantData,
				})
			})
		})

		test('joins conversation', async () => {
			store = createStore(testStoreConfig)
			const response = generateOCSResponse({ payload: participantData })
			joinConversation.mockResolvedValue(response)

			const returnedResponse = await store.dispatch('joinConversation', { token: TOKEN })

			expect(joinConversation).toHaveBeenCalledWith({ token: TOKEN, forceJoin: false })
			expect(SessionStorage.getItem('joined_conversation')).toBe(TOKEN)
			expect(returnedResponse).toBe(response)

			expect(testStoreConfig.actions.addConversation).toHaveBeenCalledWith(expect.anything(), participantData)

			expect(actorStore.participantIdentifier.sessionId).toBe('session-id-1')

			expect(store.getters.participantsList(TOKEN)[0])
				.toStrictEqual(participantData)

			expect(joinedConversationEventMock).toHaveBeenCalledWith({ token: TOKEN })
		})

		test('force join conversation', async () => {
			store = createStore(testStoreConfig)
			const updatedParticipantData = { ...participantData, sessionId: 'another-session-id' }
			const response = generateOCSResponse({ payload: updatedParticipantData })
			joinConversation.mockResolvedValue(response)

			SessionStorage.setItem('joined_conversation', TOKEN)

			await store.dispatch('forceJoinConversation', { token: TOKEN })

			expect(joinConversation).toHaveBeenCalledWith({ token: TOKEN, forceJoin: true })
			expect(SessionStorage.getItem('joined_conversation')).toBe(TOKEN)

			expect(testStoreConfig.actions.addConversation).toHaveBeenCalledWith(expect.anything(), updatedParticipantData)

			expect(actorStore.participantIdentifier.sessionId).toBe('another-session-id')

			expect(store.getters.participantsList(TOKEN)[0])
				.toStrictEqual(updatedParticipantData)

			expect(joinedConversationEventMock).toHaveBeenCalledWith({ token: TOKEN })
		})

		describe('force join on error', () => {
			afterEach(() => {
				vi.useRealTimers()
				expect(SessionStorage.getItem('joined_conversation')).toBe(null)
				expect(joinedConversationEventMock).not.toHaveBeenCalled()
			})

			/**
			 * @param {number} lastPingAge The unix timestamp of the last ping of the participant
			 * @param {number} inCall The in_call flag of the participant
			 */
			function prepareTestJoinWithMaxPingAge(lastPingAge, inCall) {
				const mockDate = new Date('2020-01-01 20:00:00')
				participantData.lastPing = mockDate.getTime() / 1000 - lastPingAge
				participantData.inCall = inCall

				vi.useFakeTimers().setSystemTime(mockDate)

				const error = generateOCSErrorResponse({ payload: participantData, status: 409 })
				joinConversation.mockRejectedValue(error)
			}

			describe('when not in call', () => {
				test('forces join when max ping age > 40s', async () => {
					prepareTestJoinWithMaxPingAge(41, PARTICIPANT.CALL_FLAG.DISCONNECTED)

					testStoreConfig.actions.forceJoinConversation = vi.fn()

					store = createStore(testStoreConfig)
					await store.dispatch('joinConversation', { token: TOKEN })

					expect(EventBus.emit).not.toHaveBeenCalled()
					expect(testStoreConfig.actions.forceJoinConversation).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
				})

				test('shows force when max ping age <= 40s', async () => {
					prepareTestJoinWithMaxPingAge(40, PARTICIPANT.CALL_FLAG.DISCONNECTED)

					testStoreConfig.actions.forceJoinConversation = vi.fn()

					store = createStore(testStoreConfig)
					await store.dispatch('joinConversation', { token: TOKEN })

					expect(testStoreConfig.actions.forceJoinConversation).not.toHaveBeenCalled()
					expect(EventBus.emit).toHaveBeenCalledWith('session-conflict-confirmation', TOKEN)
				})
			})

			describe('when in call', () => {
				test('forces join when max ping age > 60s', async () => {
					prepareTestJoinWithMaxPingAge(61, PARTICIPANT.CALL_FLAG.IN_CALL)

					testStoreConfig.actions.forceJoinConversation = vi.fn()

					store = createStore(testStoreConfig)
					await store.dispatch('joinConversation', { token: TOKEN })

					expect(EventBus.emit).not.toHaveBeenCalled()
					expect(testStoreConfig.actions.forceJoinConversation).toHaveBeenCalledWith(expect.anything(), { token: TOKEN })
				})

				test('shows force when max ping age <= 60s', async () => {
					prepareTestJoinWithMaxPingAge(60, PARTICIPANT.CALL_FLAG.IN_CALL)

					testStoreConfig.actions.forceJoinConversation = vi.fn()

					store = createStore(testStoreConfig)
					await store.dispatch('joinConversation', { token: TOKEN })

					expect(testStoreConfig.actions.forceJoinConversation).not.toHaveBeenCalled()
					expect(EventBus.emit).toHaveBeenCalledWith('session-conflict-confirmation', TOKEN)
				})
			})
		})
	})

	describe('leaving conversation', () => {
		test('leaves conversation', async () => {
			leaveConversation.mockResolvedValue()

			await store.dispatch('leaveConversation', { token: TOKEN })

			expect(leaveCall).not.toHaveBeenCalled()
			expect(leaveConversation).toHaveBeenCalledWith(TOKEN)
		})

		test('leaves conversation while in call', async () => {
			actorStore.setCurrentParticipant({
				attendeeId: 1,
				sessionId: 'session-id-1',
			})
			testStoreConfig.getters.conversation = () => vi.fn().mockReturnValue({
				token: TOKEN,
				type: 3,
			})
			store = createStore(testStoreConfig)

			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: {
					attendeeId: 1,
					sessionId: 'session-id-1',
					participantType: PARTICIPANT.TYPE.USER,
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				},
			})

			const flags = PARTICIPANT.CALL_FLAG.WITH_AUDIO | PARTICIPANT.CALL_FLAG.WITH_VIDEO
			await store.dispatch('joinCall', {
				token: TOKEN,
				participantIdentifier: {
					attendeeId: 1,
					sessionId: 'session-id-1',
				},
				flags,
				silent: false,
			})
			EventBus.emit('signaling-join-call', [TOKEN, flags])
			expect(store.getters.isInCall(TOKEN)).toBe(true)

			leaveConversation.mockResolvedValue()

			await store.dispatch('leaveConversation', { token: TOKEN })

			expect(store.getters.isInCall(TOKEN)).toBe(false)
			expect(leaveCall).toHaveBeenCalledWith(TOKEN, false)
			expect(leaveConversation).toHaveBeenCalledWith(TOKEN)
		})

		test('removes current user from conversation', async () => {
			removeCurrentUserFromConversation.mockResolvedValue()

			testStoreConfig = cloneDeep(participantsStore)
			testStoreConfig.actions.deleteConversation = vi.fn()
			store = createStore(testStoreConfig)

			await store.dispatch('removeCurrentUserFromConversation', { token: TOKEN })

			expect(removeCurrentUserFromConversation).toHaveBeenCalledWith(TOKEN)
			expect(testStoreConfig.actions.deleteConversation).toHaveBeenCalledWith(expect.anything(), TOKEN)
		})
	})

	describe('participant permissions', () => {
		beforeEach(() => {
			store.dispatch('addParticipant', {
				token: TOKEN,
				participant: {
					attendeeId: 1,
					permissions: PARTICIPANT.PERMISSIONS.MAX_DEFAULT,
				},
			})
		})

		test('grants all permissions to a participant', async () => {
			grantAllPermissionsToParticipant.mockResolvedValue()

			await store.dispatch('grantAllPermissionsToParticipant', { token: TOKEN, attendeeId: 1, permissions: PARTICIPANT.PERMISSIONS.CUSTOM })

			expect(grantAllPermissionsToParticipant).toHaveBeenCalledWith(TOKEN, 1)
			expect(store.getters.getParticipant(TOKEN, 1).permissions).toBe(PARTICIPANT.PERMISSIONS.MAX_CUSTOM)
		})

		test('removes all permissions to a participant', async () => {
			removeAllPermissionsFromParticipant.mockResolvedValue()

			await store.dispatch('removeAllPermissionsFromParticipant', { token: TOKEN, attendeeId: 1, permissions: PARTICIPANT.PERMISSIONS.MAX_CUSTOM })

			expect(removeAllPermissionsFromParticipant).toHaveBeenCalledWith(TOKEN, 1)
			expect(store.getters.getParticipant(TOKEN, 1).permissions).toBe(PARTICIPANT.PERMISSIONS.CUSTOM)
		})
	})
})
