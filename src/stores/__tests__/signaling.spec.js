/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

import { ATTENDEE } from '../../constants.js'
import vuexStore from '../../store/index.js'
import { useSignalingStore } from '../signaling.ts'

describe('signalingStore', () => {
	const TOKEN = 'TOKEN'
	const participantsInStore = [
		{ actorId: 'user1', actorType: ATTENDEE.ACTOR_TYPE.USERS, attendeeId: 1, sessionIds: ['session-id-1'] },
		{ actorId: 'user2', actorType: ATTENDEE.ACTOR_TYPE.USERS, attendeeId: 2, sessionIds: [] },
		{ actorId: 'hex', actorType: ATTENDEE.ACTOR_TYPE.GUESTS, attendeeId: 3, sessionIds: ['session-id-4'] },
	]
	const populateParticipantsStore = (participants = participantsInStore) => {
		participants.forEach(participant => {
			vuexStore.dispatch('addParticipant', { token: TOKEN, participant })
		})
	}

	let signalingStore

	beforeEach(() => {
		setActivePinia(createPinia())
		signalingStore = useSignalingStore()
	})

	afterEach(() => {
		jest.clearAllMocks()
		vuexStore.dispatch('purgeParticipantsStore', TOKEN)
	})

	describe('session search', () => {
		it('should return undefined for an unknown session', () => {
			// Assert
			expect(signalingStore.getSignalingSession('id')).toBeUndefined()
		})
	})

	describe('internal signaling', () => {
		const participantsPayload = [
			{ userId: 'user1', sessionId: 'session-id-1', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: 'user2', sessionId: 'session-id-2', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: 'user2', sessionId: 'session-id-3', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: '', sessionId: 'session-id-4', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
		]

		it('should return a mapped object for a known session', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = signalingStore.updateParticipantsFromInternalSignaling(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(Object.keys(signalingStore.sessions)).toHaveLength(4)
			expect(signalingStore.getSignalingSession('session-id-1'))
				.toMatchObject({ token: TOKEN, attendeeId: 1, sessionId: 'session-id-1', signalingSessionId: 'session-id-1' })
			expect(signalingStore.getSignalingSession('session-id-2'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-2', signalingSessionId: 'session-id-2' })
			expect(signalingStore.getSignalingSession('session-id-3'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-3', signalingSessionId: 'session-id-3' })
			expect(signalingStore.getSignalingSession('session-id-4'))
				.toMatchObject({ token: TOKEN, attendeeId: 3, sessionId: 'session-id-4', signalingSessionId: 'session-id-4' })
		})

		it('should update participant objects for a known session', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')

			// Act
			signalingStore.updateParticipantsFromInternalSignaling(TOKEN, participantsPayload)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(3)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-1'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-2', 'session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-4'] } })
		})

		it('should handle unknown sessions', () => {
			// Arrange
			populateParticipantsStore()
			const participantsPayload = [{ userId: 'user-unknown', sessionId: 'session-id-unknown' }]

			// Act
			const unknownResults = signalingStore.updateParticipantsFromInternalSignaling(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(Object.keys(signalingStore.sessions)).toHaveLength(1)
			expect(signalingStore.getSignalingSession('session-id-unknown'))
				.toMatchObject({ token: TOKEN, attendeeId: undefined, sessionId: 'session-id-unknown', signalingSessionId: 'session-id-unknown' })
		})

		it('should remove old sessions and update participant objects', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')
			const newParticipantsPayload = [
				{ userId: 'user2', sessionId: 'session-id-2', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			]
			signalingStore.updateParticipantsFromInternalSignaling(TOKEN, participantsPayload)

			// Act
			signalingStore.updateParticipantsFromInternalSignaling(TOKEN, newParticipantsPayload)

			// Assert
			expect(Object.keys(signalingStore.sessions)).toHaveLength(1)
			expect(signalingStore.getSignalingSession('session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-2'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { inCall: 0, sessionIds: [] } })

		})
	})

	describe('standalone signaling', () => {
		const participantsJoinedPayload = [
			{ userid: 'user1', user: { displayname: 'User 1' }, sessionid: 'signaling-id-1', roomsessionid: 'session-id-1' },
			{ userid: 'user2', user: { displayname: 'User 2' }, sessionid: 'signaling-id-2', roomsessionid: 'session-id-2' },
			{ userid: 'user2', user: { displayname: 'User 2' }, sessionid: 'signaling-id-3', roomsessionid: 'session-id-3' },
			{ userid: '', user: { displayname: 'Guest' }, sessionid: 'signaling-id-4', roomsessionid: 'session-id-4' },
		]

		const participantsChangedPayload = [
			{ userId: 'user1', sessionId: 'signaling-id-1', inCall: 7, participantType: 1, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: 'user2', sessionId: 'signaling-id-2', inCall: 7, participantType: 3, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: 'user2', sessionId: 'signaling-id-3', inCall: 0, participantType: 3, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: '', displayName: 'Guest New', sessionId: 'signaling-id-4', inCall: 7, participantType: 6, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: '', sessionId: 'signaling-id-unknown', inCall: 7, participantType: 3, lastPing: 1717192800, participantPermissions: 254 },
		]

		it('should return a mapped object for a known session', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = signalingStore.updateParticipantsJoinedFromStandaloneSignaling(TOKEN, participantsJoinedPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(Object.keys(signalingStore.sessions)).toHaveLength(4)
			expect(signalingStore.getSignalingSession('signaling-id-1'))
				.toMatchObject({ token: TOKEN, attendeeId: 1, sessionId: 'session-id-1', signalingSessionId: 'signaling-id-1' })
			expect(signalingStore.getSignalingSession('signaling-id-2'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-2', signalingSessionId: 'signaling-id-2' })
			expect(signalingStore.getSignalingSession('signaling-id-3'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-3', signalingSessionId: 'signaling-id-3' })
			expect(signalingStore.getSignalingSession('signaling-id-4'))
				.toMatchObject({ token: TOKEN, attendeeId: 3, sessionId: 'session-id-4', signalingSessionId: 'signaling-id-4' })
		})

		it('should update participant objects for a known session on join', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')

			// Act
			signalingStore.updateParticipantsJoinedFromStandaloneSignaling(TOKEN, participantsJoinedPayload)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(3)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { displayName: 'User 1', sessionIds: ['session-id-1'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { displayName: 'User 2', sessionIds: ['session-id-2', 'session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { displayName: 'Guest', sessionIds: ['session-id-4'] } })
		})

		it('should handle unknown sessions', () => {
			// Arrange
			populateParticipantsStore()
			const participantsPayload = [{ userid: 'user-unknown', user: { displayName: 'User Unknown' }, sessionid: 'signaling-id-unknown', roomsessionid: 'session-id-unknown' }]

			// Act
			const unknownResults = signalingStore.updateParticipantsJoinedFromStandaloneSignaling(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(Object.keys(signalingStore.sessions)).toHaveLength(1)
			expect(signalingStore.getSignalingSession('signaling-id-unknown'))
				.toMatchObject({ token: TOKEN, attendeeId: undefined, sessionId: 'session-id-unknown', signalingSessionId: 'signaling-id-unknown' })
		})

		it('should remove old sessions and update participant objects', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')
			const participantsLeftPayload = [
				'signaling-id-1',
				'signaling-id-2',
				'signaling-id-4',
				'signaling-id-unknown',
			]
			signalingStore.updateParticipantsJoinedFromStandaloneSignaling(TOKEN, participantsJoinedPayload)
			expect(Object.keys(signalingStore.sessions)).toHaveLength(4)

			// Act
			signalingStore.updateParticipantsLeftFromStandaloneSignaling(participantsLeftPayload)

			// Assert
			expect(Object.keys(signalingStore.sessions)).toHaveLength(1)
			expect(signalingStore.getSignalingSession('session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { sessionIds: ['session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { inCall: 0, sessionIds: [] } })

		})

		it('should update participant objects for a known session on change', () => {
			// Arrange
			jest.spyOn(vuexStore, 'commit')
			populateParticipantsStore()
			signalingStore.updateParticipantsJoinedFromStandaloneSignaling(TOKEN, participantsJoinedPayload)

			// Act
			signalingStore.updateParticipantsChangedFromStandaloneSignaling(TOKEN, participantsChangedPayload)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 7, participantType: 1, lastPing: 1717192800, permissions: 254 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { inCall: 7, participantType: 3, lastPing: 1717192800, permissions: 254 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { displayName: 'Guest New', inCall: 7, participantType: 6, lastPing: 1717192800, permissions: 254 } })
		})

		it('should update participant objects for a known session on call disconnect', () => {
			// Arrange
			jest.spyOn(vuexStore, 'commit')
			populateParticipantsStore()
			signalingStore.updateParticipantsJoinedFromStandaloneSignaling(TOKEN, participantsJoinedPayload)

			// Act
			signalingStore.updateParticipantsCallDisconnectedFromStandaloneSignaling(TOKEN)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { inCall: 0 } })
		})
	})
})
