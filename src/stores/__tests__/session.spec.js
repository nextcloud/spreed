/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { ATTENDEE, PARTICIPANT } from '../../constants.ts'
import vuexStore from '../../store/index.js'
import { useGuestNameStore } from '../guestName.js'
import { useSessionStore } from '../session.ts'

describe('sessionStore', () => {
	const TOKEN = 'TOKEN'
	const participantsInStore = [
		{
			actorId: 'user1',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.OWNER,
			attendeeId: 1,
			inCall: 0,
			sessionIds: ['nextcloud-session-id-1'],
		},
		{
			actorId: 'user2',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.USER,
			attendeeId: 2,
			inCall: 0,
			sessionIds: [],
		},
		{
			actorId: 'user4',
			actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS,
			participantType: PARTICIPANT.TYPE.USER,
			attendeeId: 4,
			inCall: 0,
			sessionIds: [],
		},
		{
			actorId: 'hex',
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			participantType: PARTICIPANT.TYPE.GUEST,
			attendeeId: 5,
			inCall: 0,
			sessionIds: ['nextcloud-session-id-5'],
		},
	]
	const populateParticipantsStore = (participants = participantsInStore) => {
		participants.forEach((participant) => {
			vuexStore.dispatch('addParticipant', { token: TOKEN, participant })
		})
	}

	let sessionStore
	let guestNameStore

	beforeEach(() => {
		setActivePinia(createPinia())
		sessionStore = useSessionStore()
		guestNameStore = useGuestNameStore()
		jest.spyOn(vuexStore, 'commit')
		jest.spyOn(guestNameStore, 'addGuestName')
	})

	afterEach(() => {
		jest.clearAllMocks()
		vuexStore.dispatch('purgeParticipantsStore', TOKEN)
	})

	describe('sessions handling', () => {
		it('should return undefined for an unknown session', () => {
			// Assert
			expect(sessionStore.getSession('id')).toBeUndefined()
		})

		it('should update existing orphan session with new information', () => {
			// Arrange
			sessionStore.addSession({
				token: TOKEN,
				attendeeId: undefined,
				sessionId: 'nextcloud-session-id-1',
				signalingSessionId: 'session-id-1',
			})
			expect(sessionStore.getSession('session-id-1')).toBeDefined()
			expect(sessionStore.getSession('session-id-1').attendeeId).toBeUndefined()

			// Act
			sessionStore.updateSession('session-id-1', { attendeeId: 1 })

			// Assert
			expect(sessionStore.getSession('session-id-1')).toBeDefined()
			expect(sessionStore.getSession('session-id-1').attendeeId).toBe(1)
		})

		it('should handle correctly if sessionId is not defined', () => {
			console.error = jest.fn()
			// Assert
			expect(sessionStore.findOrCreateSession(TOKEN, {})).toBe(null)
		})
	})

	describe('internal session', () => {
		const participantsPayload = [
			{
				actorId: 'user1',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				roomId: 1,
				userId: 'user1',
				sessionId: 'nextcloud-session-id-1',
				inCall: 7,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				actorId: 'user2',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				roomId: 1,
				userId: 'user2',
				sessionId: 'nextcloud-session-id-2',
				inCall: 7,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				actorId: 'user2',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				roomId: 1,
				userId: 'user2',
				sessionId: 'nextcloud-session-id-3',
				inCall: 3,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				actorId: 'hex',
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
				roomId: 1,
				userId: '',
				sessionId: 'nextcloud-session-id-5',
				inCall: 7,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
		]

		it('should return a mapped object for a known session from participants store', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(4)
			expect(sessionStore.getSession('nextcloud-session-id-1'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 1,
					sessionId: 'nextcloud-session-id-1',
					signalingSessionId: 'nextcloud-session-id-1',
				})
			expect(sessionStore.getSession('nextcloud-session-id-2'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 2,
					sessionId: 'nextcloud-session-id-2',
					signalingSessionId: 'nextcloud-session-id-2',
				})
			expect(sessionStore.getSession('nextcloud-session-id-3'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 2,
					sessionId: 'nextcloud-session-id-3',
					signalingSessionId: 'nextcloud-session-id-3',
				})
			expect(sessionStore.getSession('nextcloud-session-id-5'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 5,
					sessionId: 'nextcloud-session-id-5',
					signalingSessionId: 'nextcloud-session-id-5',
				})
		})

		it('should update participant objects for a known session', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(vuexStore.commit).toHaveBeenCalledTimes(3)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 1,
				updatedData: {
					inCall: 7,
					lastPing: 1717192800,
					permissions: 254,
					sessionIds: ['nextcloud-session-id-1'],
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 2,
				updatedData: {
					inCall: 7,
					lastPing: 1717192800,
					permissions: 254,
					sessionIds: ['nextcloud-session-id-2', 'nextcloud-session-id-3'],
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 5,
				updatedData: {
					inCall: 7,
					lastPing: 1717192800,
					permissions: 254,
					sessionIds: ['nextcloud-session-id-5'],
				},
			})
		})

		it('should handle unknown sessions', () => {
			// Arrange
			populateParticipantsStore()
			const sessionsPayload = [
				...participantsPayload,
				{
					actorId: 'user-unknown',
					actorType: ATTENDEE.ACTOR_TYPE.USERS,
					roomId: 1,
					userId: 'user-unknown',
					sessionId: 'nextcloud-session-id-unknown',
				},
			]

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, sessionsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(sessionStore.orphanSessions).toHaveLength(1)
			expect(Object.keys(sessionStore.sessions)).toHaveLength(5)
			expect(sessionStore.getSession('nextcloud-session-id-unknown'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: undefined,
					sessionId: 'nextcloud-session-id-unknown',
					signalingSessionId: 'nextcloud-session-id-unknown',
				})
		})

		it('should remove old sessions and update participant objects', () => {
			// Arrange
			populateParticipantsStore()
			const newParticipantsPayload = [
				{
					roomId: 1,
					userId: 'user2',
					sessionId: 'nextcloud-session-id-3',
					inCall: 3,
					lastPing: 1717192800,
					participantPermissions: 254,
				},
			]
			sessionStore.updateSessions(TOKEN, participantsPayload)

			// Act
			sessionStore.updateSessions(TOKEN, newParticipantsPayload)

			// Assert
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('nextcloud-session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant', { token: TOKEN, attendeeId: 1, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 2,
				updatedData: {
					inCall: 3,
					lastPing: 1717192800,
					permissions: 254,
					sessionIds: ['nextcloud-session-id-3'],
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant', { token: TOKEN, attendeeId: 5, updatedData: { inCall: 0, sessionIds: [] } })
		})
	})

	describe('standalone session', () => {
		const participantsJoinedPayload = [
			{
				userid: 'user1',
				user: { displayname: 'User 1' },
				sessionid: 'session-id-1',
				roomsessionid: 'nextcloud-session-id-1',
			},
			{
				userid: 'user2',
				user: { displayname: 'User 2' },
				sessionid: 'session-id-2',
				roomsessionid: 'nextcloud-session-id-2',
			},
			{
				userid: 'user2',
				sessionid: 'session-id-3',
				roomsessionid: 'nextcloud-session-id-3',
			},
			{
				userid: 'user4',
				federated: true,
				user: { displayname: 'User 4' },
				sessionid: 'session-id-4',
				roomsessionid: 'nextcloud-session-id-4',
			},
			{
				userid: '',
				user: { displayname: 'Guest' },
				sessionid: 'session-id-5',
				roomsessionid: 'nextcloud-session-id-5',
			},
		]

		const participantsChangedPayload = [
			{
				userId: 'user1',
				sessionId: 'session-id-1',
				inCall: 7,
				participantType: 1,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				userId: 'user2',
				sessionId: 'session-id-2',
				inCall: 7,
				participantType: 3,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				userId: 'user2',
				sessionId: 'session-id-3',
				inCall: 3,
				participantType: 3,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				userId: 'user4',
				sessionId: 'session-id-4',
				inCall: 0,
				participantType: 3,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				userId: '',
				displayName: 'Guest New',
				sessionId: 'session-id-5',
				inCall: 7,
				participantType: 6,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
			{
				userId: '',
				sessionId: 'session-id-unknown',
				inCall: 7,
				participantType: 3,
				lastPing: 1717192800,
				participantPermissions: 254,
			},
		]

		it('should return a mapped object for a known session', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(5)
			expect(sessionStore.getSession('session-id-1'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 1,
					sessionId: 'nextcloud-session-id-1',
					signalingSessionId: 'session-id-1',
				})
			expect(sessionStore.getSession('session-id-2'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 2,
					sessionId: 'nextcloud-session-id-2',
					signalingSessionId: 'session-id-2',
				})
			expect(sessionStore.getSession('session-id-3'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 2,
					sessionId: 'nextcloud-session-id-3',
					signalingSessionId: 'session-id-3',
				})
			expect(sessionStore.getSession('session-id-4'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 4,
					sessionId: 'nextcloud-session-id-4',
					signalingSessionId: 'session-id-4',
				})
			expect(sessionStore.getSession('session-id-5'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 5,
					sessionId: 'nextcloud-session-id-5',
					signalingSessionId: 'session-id-5',
				})
		})

		it('should update participant objects for a known session on join', () => {
			// Arrange
			populateParticipantsStore()

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(vuexStore.commit).toHaveBeenCalledTimes(5)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 1,
				updatedData: { displayName: 'User 1', sessionIds: ['nextcloud-session-id-1'] },
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 2,
				updatedData: { displayName: 'User 2', sessionIds: ['nextcloud-session-id-2'] },
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 2,
				updatedData: {
					displayName: 'User 2',
					sessionIds: ['nextcloud-session-id-2', 'nextcloud-session-id-3'],
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 4,
				updatedData: { displayName: 'User 4', sessionIds: ['nextcloud-session-id-4'] },
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 5,
				updatedData: { displayName: 'Guest', sessionIds: ['nextcloud-session-id-5'] },
			})
		})

		it('should handle unknown sessions on join', () => {
			// Arrange
			populateParticipantsStore()
			const participantsPayload = [{
				userid: 'user-unknown',
				user: { displayName: 'User Unknown' },
				sessionid: 'session-id-unknown',
				roomsessionid: 'nextcloud-session-id-unknown',
			}]

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('session-id-unknown'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: undefined,
					sessionId: 'nextcloud-session-id-unknown',
					signalingSessionId: 'session-id-unknown',
				})
		})

		it('should ignore ghost sessions', () => {
			// Arrange
			populateParticipantsStore()
			const participantsPayload = [{
				userid: '',
				sessionid: 'session-id-recording',
			}]

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeFalsy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(0)
		})

		it('should remove old sessions and update participant objects', () => {
			// Arrange
			populateParticipantsStore()
			const participantsLeftPayload = [
				'session-id-1',
				'session-id-2',
				'session-id-4',
				'session-id-5',
				'session-id-unknown',
			]
			const changedPayload = participantsChangedPayload.slice(0, 4).concat({
				...participantsChangedPayload[4],
				displayName: 'Guest New Name',
			})
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)
			// Fake a session with missing inCall attribute
			sessionStore.addSession({
				attendeeId: 1,
				token: TOKEN,
				signalingSessionId: 'session-id-11',
				sessionId: 'nextcloud-session-id-11',
				inCall: undefined,
			})
			sessionStore.updateSessions(TOKEN, changedPayload)
			expect(Object.keys(sessionStore.sessions)).toHaveLength(5 + 1)

			// Act
			sessionStore.updateSessionsLeft(TOKEN, participantsLeftPayload)

			// Assert
			expect(Object.keys(sessionStore.sessions)).toMatchObject(['session-id-3', 'session-id-11'])
			expect(sessionStore.getSession('session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(10 + 4)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(10 + 1, 'updateParticipant', { token: TOKEN, attendeeId: 1, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(10 + 2, 'updateParticipant', { token: TOKEN, attendeeId: 2, updatedData: { inCall: 3, sessionIds: ['nextcloud-session-id-3'] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(10 + 3, 'updateParticipant', { token: TOKEN, attendeeId: 4, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(10 + 4, 'updateParticipant', { token: TOKEN, attendeeId: 5, updatedData: { inCall: 0, sessionIds: [] } })
		})

		it('should skip update if participant is not found', () => {
			// Arrange
			populateParticipantsStore()
			sessionStore.updateSessions(TOKEN, [participantsJoinedPayload[0]])
			vuexStore.commit('deleteParticipant', { token: TOKEN, attendeeId: 1 })

			// Act
			sessionStore.updateParticipantJoinedFromStandaloneSignaling(TOKEN, 1, {})
			sessionStore.updateParticipantChangedFromStandaloneSignaling(TOKEN, 1, {})
			sessionStore.updateParticipantLeftFromStandaloneSignaling(TOKEN, 'session-id-1')

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(2) // 1 update, 1 deletion
		})

		it('should update participant objects for a known session on change', () => {
			// Arrange
			populateParticipantsStore()
			const unknownResultsJoin = sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Act
			const unknownResultsChange = sessionStore.updateSessions(TOKEN, participantsChangedPayload.slice(0, 5))

			// Assert
			expect(unknownResultsJoin).toBeFalsy()
			expect(unknownResultsChange).toBeFalsy()
			expect(vuexStore.commit).toHaveBeenCalledTimes(10)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 1,
				updatedData: {
					inCall: 7,
					participantType: 1,
					displayName: 'User 1',
					lastPing: 1717192800,
					permissions: 254,
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(7, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 2,
				updatedData: {
					inCall: 7,
					participantType: 3,
					displayName: 'User 2',
					lastPing: 1717192800,
					permissions: 254,
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(8, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 2,
				updatedData: {
					inCall: 7,
					participantType: 3,
					displayName: 'User 2',
					lastPing: 1717192800,
					permissions: 254,
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(9, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 4,
				updatedData: {
					inCall: 0,
					participantType: 3,
					displayName: 'User 4',
					lastPing: 1717192800,
					permissions: 254,
				},
			})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(10, 'updateParticipant', {
				token: TOKEN,
				attendeeId: 5,
				updatedData: {
					displayName: 'Guest New',
					inCall: 7,
					participantType: 6,
					lastPing: 1717192800,
					permissions: 254,
				},
			})
		})

		it('should handle unknown sessions on change', () => {
			// Arrange
			populateParticipantsStore()
			const participantsPayload = [{
				userId: 'user-unknown',
				sessionId: 'session-id-unknown',
				nextcloudSessionId: 'nextcloud-session-id-unknown',
			}]

			// Act
			const unknownResults = sessionStore.updateSessions(TOKEN, participantsPayload)

			// Assert
			expect(unknownResults).toBeTruthy()
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('session-id-unknown'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: undefined,
					sessionId: 'nextcloud-session-id-unknown',
					signalingSessionId: 'session-id-unknown',
				})
		})

		it('should update participant objects for a known session on call disconnect', () => {
			// Arrange
			populateParticipantsStore()
			sessionStore.updateSessions(TOKEN, participantsJoinedPayload)

			// Act
			sessionStore.updateParticipantsDisconnectedFromStandaloneSignaling(TOKEN)

			// Assert
			expect(vuexStore.commit).toHaveBeenCalledTimes(5 + 4)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant', { token: TOKEN, attendeeId: 1, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(7, 'updateParticipant', { token: TOKEN, attendeeId: 2, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(8, 'updateParticipant', { token: TOKEN, attendeeId: 4, updatedData: { inCall: 0 } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(9, 'updateParticipant', { token: TOKEN, attendeeId: 5, updatedData: { inCall: 0 } })
		})
	})
})
