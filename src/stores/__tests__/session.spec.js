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
})
