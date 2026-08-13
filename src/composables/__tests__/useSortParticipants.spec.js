/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test, vi } from 'vitest'
import { ref } from 'vue'
import { useStore } from 'vuex'
import { ATTENDEE, PARTICIPANT } from '../../constants.ts'
import { useSortParticipants } from '../useSortParticipants.js'

vi.mock('vuex')
vi.mock('../useGetToken.ts', () => ({
	useGetToken: () => ref('XXTOKENXX'),
}))

describe('useSortParticipants', () => {
	/**
	 * @param {object} data Overrides for the participant
	 */
	function createParticipant(data = {}) {
		return {
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			participantType: PARTICIPANT.TYPE.USER,
			displayName: 'Zoe',
			sessionIds: ['session-id'],
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			attendeePermissions: PARTICIPANT.PERMISSIONS.DEFAULT,
			status: '',
			...data,
		}
	}

	/**
	 * @param {number} selfParticipantType Participant type of the current user
	 */
	function mockStore(selfParticipantType = PARTICIPANT.TYPE.USER) {
		useStore.mockReturnValue({
			getters: {
				conversation: () => ({ participantType: selfParticipantType }),
			},
		})
	}

	beforeEach(() => {
		setActivePinia(createPinia())
		mockStore()
	})

	test('sorts owners above moderators', () => {
		const { sortParticipants } = useSortParticipants()
		const owner = createParticipant({ participantType: PARTICIPANT.TYPE.OWNER, displayName: 'Zoe' })
		const moderator = createParticipant({ participantType: PARTICIPANT.TYPE.MODERATOR, displayName: 'Alice' })

		// The owner wins despite sorting last by display name
		expect(sortParticipants(owner, moderator)).toBe(-1)
		expect(sortParticipants(moderator, owner)).toBe(1)
	})

	test('sorts owners above guest moderators', () => {
		const { sortParticipants } = useSortParticipants()
		const owner = createParticipant({ participantType: PARTICIPANT.TYPE.OWNER })
		const guestModerator = createParticipant({
			participantType: PARTICIPANT.TYPE.GUEST_MODERATOR,
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			displayName: 'Alice',
		})

		expect(sortParticipants(owner, guestModerator)).toBe(-1)
	})

	test('sorts moderators above regular users', () => {
		const { sortParticipants } = useSortParticipants()
		const moderator = createParticipant({ participantType: PARTICIPANT.TYPE.MODERATOR, displayName: 'Zoe' })
		const user = createParticipant({ participantType: PARTICIPANT.TYPE.USER, displayName: 'Alice' })

		expect(sortParticipants(moderator, user)).toBe(-1)
	})

	test('sorts owners above regular users', () => {
		const { sortParticipants } = useSortParticipants()
		const owner = createParticipant({ participantType: PARTICIPANT.TYPE.OWNER, displayName: 'Zoe' })
		const user = createParticipant({ participantType: PARTICIPANT.TYPE.USER, displayName: 'Alice' })

		expect(sortParticipants(owner, user)).toBe(-1)
	})

	test('falls back to the display name between two owners', () => {
		const { sortParticipants } = useSortParticipants()
		const owner1 = createParticipant({ participantType: PARTICIPANT.TYPE.OWNER, displayName: 'Alice' })
		const owner2 = createParticipant({ participantType: PARTICIPANT.TYPE.OWNER, displayName: 'Zoe' })

		expect(sortParticipants(owner1, owner2)).toBeLessThan(0)
		expect(sortParticipants(owner2, owner1)).toBeGreaterThan(0)
	})

	test('orders a full list as owners, moderators, then users', () => {
		const { sortParticipants } = useSortParticipants()
		const participants = [
			createParticipant({ participantType: PARTICIPANT.TYPE.USER, displayName: 'user' }),
			createParticipant({ participantType: PARTICIPANT.TYPE.GUEST_MODERATOR, actorType: ATTENDEE.ACTOR_TYPE.GUESTS, displayName: 'guest moderator' }),
			createParticipant({ participantType: PARTICIPANT.TYPE.OWNER, displayName: 'owner' }),
			createParticipant({ participantType: PARTICIPANT.TYPE.MODERATOR, displayName: 'moderator' }),
		]

		// Within the moderator tier guests still sort below regular participants
		expect(participants.sort(sortParticipants).map((participant) => participant.displayName))
			.toStrictEqual(['owner', 'moderator', 'guest moderator', 'user'])
	})

	test('keeps online participants above owners that are offline', () => {
		const { sortParticipants } = useSortParticipants()
		const offlineOwner = createParticipant({ participantType: PARTICIPANT.TYPE.OWNER, sessionIds: [] })
		const onlineUser = createParticipant({ participantType: PARTICIPANT.TYPE.USER })

		expect(sortParticipants(offlineOwner, onlineUser)).toBe(1)
	})
})
