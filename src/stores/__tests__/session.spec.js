/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

import { ATTENDEE } from '../../constants.ts'
import vuexStore from '../../store/index.js'
import { useSessionStore } from '../session.ts'

describe('sessionStore', () => {
	const TOKEN = 'TOKEN'
	const participantsInStore = [
		{ actorId: 'user1', actorType: ATTENDEE.ACTOR_TYPE.USERS, attendeeId: 1, inCall: 0, sessionIds: ['session-id-1'] },
		{ actorId: 'user2', actorType: ATTENDEE.ACTOR_TYPE.USERS, attendeeId: 2, inCall: 0, sessionIds: [] },
		{ actorId: 'hex', actorType: ATTENDEE.ACTOR_TYPE.GUESTS, attendeeId: 3, inCall: 0, sessionIds: ['session-id-4'] },
	]
	const populateParticipantsStore = (participants = participantsInStore) => {
		participants.forEach(participant => {
			vuexStore.dispatch('addParticipant', { token: TOKEN, participant })
		})
	}

	let sessionStore

	beforeEach(() => {
		setActivePinia(createPinia())
		sessionStore = useSessionStore()
	})

	afterEach(() => {
		jest.clearAllMocks()
		vuexStore.dispatch('purgeParticipantsStore', TOKEN)
	})

	describe('session search', () => {
		it('should return undefined for an unknown session', () => {
			// Assert
			expect(sessionStore.getSession('id')).toBeUndefined()
		})
	})

	describe('internal session', () => {
		const participantsPayload = [
			{ actorId: 'user1', actorType: ATTENDEE.ACTOR_TYPE.USERS, roomId: 1, userId: 'user1', sessionId: 'session-id-1', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			{ actorId: 'user2', actorType: ATTENDEE.ACTOR_TYPE.USERS, roomId: 1, userId: 'user2', sessionId: 'session-id-2', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			{ actorId: 'user2', actorType: ATTENDEE.ACTOR_TYPE.USERS, roomId: 1, userId: 'user2', sessionId: 'session-id-3', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			{ actorId: 'hex', actorType: ATTENDEE.ACTOR_TYPE.GUESTS, roomId: 1, userId: '', sessionId: 'session-id-4', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
		]

		it('should return a mapped object for a known session from participants store', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(4)
			expect(sessionStore.getSession('session-id-1'))
				.toMatchObject({ token: TOKEN, attendeeId: 1, sessionId: 'session-id-1', signalingSessionId: 'session-id-1' })
			expect(sessionStore.getSession('session-id-2'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-2', signalingSessionId: 'session-id-2' })
			expect(sessionStore.getSession('session-id-3'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-3', signalingSessionId: 'session-id-3' })
			expect(sessionStore.getSession('session-id-4'))
				.toMatchObject({ token: TOKEN, attendeeId: 3, sessionId: 'session-id-4', signalingSessionId: 'session-id-4' })
		})

		it('should update participant objects for a known session', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')

			// Act
			sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(3)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { attendeeId: 1, inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-1'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { attendeeId: 2, inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-2', 'session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { attendeeId: 3, inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-4'] } })
		})

		it('should handle unknown sessions', () => {
			// Arrange
			populateParticipantsStore()
			const sessionsPayload = [
				...participantsPayload,
				{ actorId: 'user-unknown', actorType: ATTENDEE.ACTOR_TYPE.USERS, roomId: 1, userId: 'user-unknown', sessionId: 'session-id-unknown' }
			]

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, sessionsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(5)
			expect(sessionStore.getSession('session-id-unknown'))
				.toMatchObject({ token: TOKEN, attendeeId: undefined, sessionId: 'session-id-unknown', signalingSessionId: 'session-id-unknown' })
		})

		it('should remove old sessions and update participant objects', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')
			const newParticipantsPayload = [
				{ roomId: 1, userId: 'user2', sessionId: 'session-id-2', inCall: 7, lastPing: 1717192800, participantPermissions: 254 },
			]
			sessionStore.updateSessions(TOKEN, participantsPayload)

			// Act
			sessionStore.updateSessions(TOKEN, newParticipantsPayload)

			// Assert
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { attendeeId: 1, inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { attendeeId: 2, inCall: 7, lastPing: 1717192800, permissions: 254, sessionIds: ['session-id-2'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { attendeeId: 3, inCall: 0, sessionIds: [] } })
		})
	})

	describe('standalone session', () => {
		const participantsJoinedPayload = [
			{ userid: 'user1', user: { displayname: 'User 1' }, sessionid: 'session-id-1', roomsessionid: 'session-id-1' },
			{ userid: 'user2', user: { displayname: 'User 2' }, sessionid: 'session-id-2', roomsessionid: 'session-id-2' },
			{ userid: 'user2', user: { displayname: 'User 2' }, sessionid: 'session-id-3', roomsessionid: 'session-id-3' },
			{ userid: '', user: { displayname: 'Guest' }, sessionid: 'session-id-4', roomsessionid: 'session-id-4' },
		]

		const participantsChangedPayload = [
			{ userId: 'user1', sessionId: 'session-id-1', inCall: 7, participantType: 1, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: 'user2', sessionId: 'session-id-2', inCall: 7, participantType: 3, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: 'user2', sessionId: 'session-id-3', inCall: 0, participantType: 3, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: '', displayName: 'Guest New', sessionId: 'session-id-4', inCall: 7, participantType: 6, lastPing: 1717192800, participantPermissions: 254 },
			{ userId: '', sessionId: 'session-id-unknown', inCall: 7, participantType: 3, lastPing: 1717192800, participantPermissions: 254 },
		]

		it('should return a mapped object for a known session', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Assert
			// expect(unknownResults).toBeFalsy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(4)
			expect(sessionStore.getSession('session-id-1'))
				.toMatchObject({ token: TOKEN, attendeeId: 1, sessionId: 'session-id-1', signalingSessionId: 'session-id-1' })
			expect(sessionStore.getSession('session-id-2'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-2', signalingSessionId: 'session-id-2' })
			expect(sessionStore.getSession('session-id-3'))
				.toMatchObject({ token: TOKEN, attendeeId: 2, sessionId: 'session-id-3', signalingSessionId: 'session-id-3' })
			expect(sessionStore.getSession('session-id-4'))
				.toMatchObject({ token: TOKEN, attendeeId: 3, sessionId: 'session-id-4', signalingSessionId: 'session-id-4' })
		})

		it('should update participant objects for a known session on join', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')

			// Act
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(4)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { attendeeId: 1, displayName: 'User 1', sessionIds: ['session-id-1'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { attendeeId: 2, displayName: 'User 2', sessionIds: ['session-id-2'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { attendeeId: 2, displayName: 'User 2', sessionIds: ['session-id-2', 'session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { attendeeId: 3, displayName: 'Guest', sessionIds: ['session-id-4'] } })
		})

		it('should handle unknown sessions', () => {
			// Arrange
			populateParticipantsStore()
			const participantsPayload = [{ userid: 'user-unknown', user: { displayName: 'User Unknown' }, sessionid: 'session-id-unknown', roomsessionid: 'session-id-unknown' }]

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('session-id-unknown'))
				.toMatchObject({ token: TOKEN, attendeeId: undefined, sessionId: 'session-id-unknown', signalingSessionId: 'session-id-unknown' })
		})

		it('should remove old sessions and update participant objects', () => {
			// Arrange
			populateParticipantsStore()
			jest.spyOn(vuexStore, 'commit')
			const participantsLeftPayload = [
				'session-id-1',
				'session-id-2',
				'session-id-4',
				'session-id-unknown',
			]
			const changedPayload = participantsChangedPayload.slice(0, 4)
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)
			sessionStore.updateSessions(TOKEN, changedPayload)
			expect(Object.keys(sessionStore.sessions)).toHaveLength(4)

			// Act
			sessionStore.updateSessionsLeft(TOKEN, participantsLeftPayload)

			// Assert
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(8 + 3)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(8 + 1, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(8 + 2, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { inCall: 7, sessionIds: ['session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(8 + 3, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { inCall: 0, sessionIds: [] } })

		})

		it('should update participant objects for a known session on change', () => {
			// Arrange
			jest.spyOn(vuexStore, 'commit')
			populateParticipantsStore()
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Act
			sessionStore.updateSessions(TOKEN, participantsChangedPayload)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(8)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { attendeeId: 1, inCall: 7, participantType: 1, displayName: 'User 1', lastPing: 1717192800, permissions: 254 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { attendeeId: 2, inCall: 7, participantType: 3, displayName: 'User 2', lastPing: 1717192800, permissions: 254 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(7, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { attendeeId: 2, inCall: 7, participantType: 3, displayName: 'User 2', lastPing: 1717192800, permissions: 254 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(8, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { attendeeId: 3, displayName: 'Guest New', inCall: 7, participantType: 6, lastPing: 1717192800, permissions: 254 } })
		})

		it('should update participant objects for a known session on call disconnect', () => {
			// Arrange
			jest.spyOn(vuexStore, 'commit')
			populateParticipantsStore()
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Act
			sessionStore.updateParticipantsDisconnectedFromStandaloneSignaling(TOKEN)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(7)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 2, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(7, 'updateParticipant',
				{ token: TOKEN, attendeeId: 3, updatedData: { inCall: 0 } })
		})
	})
})
