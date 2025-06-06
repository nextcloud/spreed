/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
import { PARTICIPANT } from '../../constants.ts'
import { useActorStore } from '../actor.js'

describe('actorStore', () => {
	let actorStore

	beforeEach(() => {
		setActivePinia(createPinia())
		actorStore = useActorStore()
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

	describe('setCurrentParticipant', () => {
		test('setCurrentParticipant with type GUEST clears user id and updates all relevant attributes', () => {
			actorStore.setCurrentParticipant({
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST,
			})

			expect(actorStore.userId).toBe(null)
			expect(actorStore.displayName).toBe('')
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
			expect(actorStore.displayName).toBe('')
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
})
