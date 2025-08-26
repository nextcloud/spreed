/*
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { ATTENDEE, PARTICIPANT } from '../../constants.ts'
import { getTeams } from '../../services/teamsService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { useActorStore } from '../actor.ts'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => false),
}))
vi.mock('../../services/teamsService.ts', () => ({
	getTeams: vi.fn(() => Promise.resolve({
		data: {
			ocs: {
				data: [],
			},
		},
	})),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(),
}))

describe('actorStore', () => {
	let actorStore

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
	})

	afterEach(() => {
		vi.clearAllMocks()
		actorStore.userId = null
		actorStore.sessionId = null
		actorStore.attendeeId = null
		actorStore.actorId = null
		actorStore.actorType = null
		actorStore.displayName = ''
		actorStore.actorGroups = []
		actorStore.actorTeams = []
	})

	describe('initialize', () => {
		test('initialize the store', () => {
			const user = { uid: 'userId', displayName: 'display-name' }
			getCurrentUser.mockReturnValue(user)
			expect(actorStore.actorId).toBeNull()
			expect(actorStore.displayName).toBe('')

			actorStore.initialize()

			expect(actorStore.actorId).toBe('userId')
			expect(actorStore.displayName).toBe('display-name')
		})
	})

	test('setCurrentUser updates all relevant attributes', () => {
		actorStore.setCurrentUser({
			uid: 'userId',
			displayName: 'display-name',
		})

		expect(actorStore.userId).toBe('userId')
		expect(actorStore.displayName).toBe('display-name')
		expect(actorStore.actorId).toBe('userId')
		expect(actorStore.actorType).toBe('users')
	})

	test('setDisplayName updates all relevant attributes', () => {
		actorStore.setCurrentUser({
			uid: 'userId',
			displayName: 'display-name',
		})
		actorStore.setDisplayName('new-display-name')

		expect(actorStore.userId).toBe('userId')
		expect(actorStore.displayName).toBe('new-display-name')
	})

	test('check if the message actor is the current one', () => {
		actorStore.setCurrentParticipant({
			actorId: 'guestId',
			attendeeId: 1,
			displayName: 'display-name',
			participantType: PARTICIPANT.TYPE.GUEST,
		})

		const message = {
			actorId: 'guestId',
			actorType: ATTENDEE.ACTOR_TYPE.GUESTS,
			message: 'Hello',
		}

		expect(actorStore.checkIfSelfIsActor(message)).toBe(true)
	})

	describe('setCurrentParticipant', () => {
		test('setCurrentParticipant with type GUEST clears user id and updates all relevant attributes', () => {
			actorStore.setCurrentParticipant({
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST,
			})

			expect(actorStore.userId).toBe(null)
			expect(actorStore.actorId).toBe('guestActorId')
			expect(actorStore.actorType).toBe('guests')
			expect(actorStore.sessionId).toBe('XXSESSIONIDXX')
		})

		test('setCurrentParticipant with type GUEST_MODERATOR clears user id and updates all relevant attributes', () => {
			actorStore.setCurrentParticipant({
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST_MODERATOR,
			})

			expect(actorStore.userId).toBe(null)
			expect(actorStore.actorId).toBe('guestActorId')
			expect(actorStore.actorType).toBe('guests')
			expect(actorStore.sessionId).toBe('XXSESSIONIDXX')
		})

		test('setCurrentParticipant with type USER keeps user id and updates all relevant attributes', () => {
			actorStore.setCurrentUser({
				uid: 'userId',
				displayName: 'display-name',
			})

			actorStore.setCurrentParticipant({
				actorId: 'userActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.USER,
			})

			expect(actorStore.sessionId).toBe('XXSESSIONIDXX')

			// user values unchanged
			expect(actorStore.userId).toBe('userId')
			expect(actorStore.displayName).toBe('display-name')
			expect(actorStore.actorId).toBe('userId')
			expect(actorStore.actorType).toBe('users')
		})
	})

	describe('Groups and Teams', () => {
		test('isActorMemberOfGroup returns true for group member', () => {
			actorStore.actorGroups = ['group1', 'group2']
			expect(actorStore.isActorMemberOfGroup('group1')).toBe(true)
			expect(actorStore.isActorMemberOfGroup('group3')).toBe(false)
		})

		test('isActorMemberOfTeam returns true for team member', () => {
			actorStore.actorTeams = ['team1', 'team2']
			expect(actorStore.isActorMemberOfTeam('team1')).toBe(true)
			expect(actorStore.isActorMemberOfTeam('team3')).toBe(false)
		})
	})

	describe('getCurrentUserTeams', () => {
		test('does nothing if circles are not enabled', async () => {
			loadState.mockReturnValue(false)
			await actorStore.getCurrentUserTeams()
			expect(getTeams).not.toHaveBeenCalled()
			expect(actorStore.actorTeams).toEqual([])
		})

		test('sets actorTeams from API response', async () => {
			loadState.mockReturnValue(true)
			getTeams.mockResolvedValue(generateOCSResponse({
				payload: [
					{ id: 'team1' },
					{ id: 'team2' },
				],
			}))
			await actorStore.getCurrentUserTeams()
			expect(getTeams).toHaveBeenCalled()
			expect(actorStore.actorTeams).toEqual(['team1', 'team2'])
		})

		test('logs error if getTeams throws', async () => {
			loadState.mockReturnValue(true)
			const error = new Error('fail')
			getTeams.mockRejectedValue(error)
			const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
			await actorStore.getCurrentUserTeams()
			expect(consoleSpy).toHaveBeenCalledWith(error)
			consoleSpy.mockRestore()
		})
	})
})
