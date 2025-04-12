/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

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
			sessionIds: ['nextcloud-session-id-1']
		},
		{
			actorId: 'user2',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.USER,
			attendeeId: 2,
			inCall: 0,
			sessionIds: []
		},
		{
			actorId: 'user4',
			actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS,
			participantType: PARTICIPANT.TYPE.USER,
			attendeeId: 4,
			inCall: 0,
			sessionIds: []
		},
		{
			actorId: 'hex',
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			participantType: PARTICIPANT.TYPE.GUEST,
			attendeeId: 5,
			inCall: 0,
			sessionIds: ['nextcloud-session-id-5']
		},
	]
	const populateParticipantsStore = (participants = participantsInStore) => {
		participants.forEach(participant => {
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
				signalingSessionId: 'session-id-1'
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
				participantPermissions: 254
			},
			{
				actorId: 'user2',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				roomId: 1,
				userId: 'user2',
				sessionId: 'nextcloud-session-id-2',
				inCall: 7,
				lastPing: 1717192800,
				participantPermissions: 254
			},
			{
				actorId: 'user2',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				roomId: 1,
				userId: 'user2',
				sessionId: 'nextcloud-session-id-3',
				inCall: 3,
				lastPing: 1717192800,
				participantPermissions: 254
			},
			{
				actorId: 'hex',
				actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
				roomId: 1,
				userId: '',
				sessionId: 'nextcloud-session-id-5',
				inCall: 7,
				lastPing: 1717192800,
				participantPermissions: 254
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
					signalingSessionId: 'nextcloud-session-id-1'
				})
			expect(sessionStore.getSession('nextcloud-session-id-2'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 2,
					sessionId: 'nextcloud-session-id-2',
					signalingSessionId: 'nextcloud-session-id-2'
				})
			expect(sessionStore.getSession('nextcloud-session-id-3'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 2,
					sessionId: 'nextcloud-session-id-3',
					signalingSessionId: 'nextcloud-session-id-3'
				})
			expect(sessionStore.getSession('nextcloud-session-id-5'))
				.toMatchObject({
					token: TOKEN,
					attendeeId: 5,
					sessionId: 'nextcloud-session-id-5',
					signalingSessionId: 'nextcloud-session-id-5'
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
			expect(vuexStore.commit).toHaveBeenNthCalledWith(1, 'updateParticipant',
				{
					token: TOKEN,
					attendeeId: 1,
					updatedData: {
						inCall: 7,
						lastPing: 1717192800,
						permissions: 254,
						sessionIds: ['nextcloud-session-id-1']
					}
				})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(2, 'updateParticipant',
				{
					token: TOKEN,
					attendeeId: 2,
					updatedData: {
						inCall: 7,
						lastPing: 1717192800,
						permissions: 254,
						sessionIds: ['nextcloud-session-id-2', 'nextcloud-session-id-3']
					}
				})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(3, 'updateParticipant',
				{
					token: TOKEN,
					attendeeId: 5,
					updatedData: {
						inCall: 7,
						lastPing: 1717192800,
						permissions: 254,
						sessionIds: ['nextcloud-session-id-5']
					}
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
					sessionId: 'nextcloud-session-id-unknown'
				}
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
					signalingSessionId: 'nextcloud-session-id-unknown'
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
					participantPermissions: 254
				},
			]
			sessionStore.updateSessions(TOKEN, participantsPayload)

			// Act
			sessionStore.updateSessions(TOKEN, newParticipantsPayload)

			// Assert
			expect(Object.keys(sessionStore.sessions)).toHaveLength(1)
			expect(sessionStore.getSession('nextcloud-session-id-1')).toBeUndefined()
			expect(vuexStore.commit).toHaveBeenCalledTimes(6)
			expect(vuexStore.commit).toHaveBeenNthCalledWith(4, 'updateParticipant',
				{ token: TOKEN, attendeeId: 1, updatedData: { inCall: 0, sessionIds: [] } })
			expect(vuexStore.commit).toHaveBeenNthCalledWith(5, 'updateParticipant',
				{
					token: TOKEN,
					attendeeId: 2,
					updatedData: {
						inCall: 3,
						lastPing: 1717192800,
						permissions: 254,
						sessionIds: ['nextcloud-session-id-3']
					}
				})
			expect(vuexStore.commit).toHaveBeenNthCalledWith(6, 'updateParticipant',
				{ token: TOKEN, attendeeId: 5, updatedData: { inCall: 0, sessionIds: [] } })
		})
	})
})
