/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import actorStore from './actorStore.js'
import { PARTICIPANT } from '../constants.js'

describe('actorStore', () => {
	let localVue = null
	let store = null

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(cloneDeep(actorStore))
	})

	test('setCurrentUser updates all relevant attributes', () => {
		store.dispatch('setCurrentUser', {
			uid: 'userId',
			displayName: 'display-name',
		})

		expect(store.getters.getUserId()).toBe('userId')
		expect(store.getters.getDisplayName()).toBe('display-name')
		expect(store.getters.getActorId()).toBe('userId')
		expect(store.getters.getActorType()).toBe('users')
	})

	test('setDisplayName updates all relevant attributes', () => {
		store.dispatch('setCurrentUser', {
			uid: 'userId',
			displayName: 'display-name',
		})

		store.dispatch('setDisplayName', 'new-display-name')

		expect(store.getters.getUserId()).toBe('userId')
		expect(store.getters.getDisplayName()).toBe('new-display-name')
	})

	describe('setCurrentParticipant', () => {
		test('setCurrentParticipant with type GUEST clears user id and updates all relevant attributes', () => {
			store.dispatch('setCurrentParticipant', {
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST,
			})

			expect(store.getters.getSessionId()).toBe('XXSESSIONIDXX')
			expect(store.getters.getUserId()).toBe(null)
			expect(store.getters.getDisplayName()).toBe('')
			expect(store.getters.getActorId()).toBe('guestActorId')
			expect(store.getters.getActorType()).toBe('guests')
		})

		test('setCurrentParticipant with type GUEST_MODERATOR clears user id and updates all relevant attributes', () => {
			store.dispatch('setCurrentParticipant', {
				actorId: 'guestActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.GUEST_MODERATOR,
			})

			expect(store.getters.getSessionId()).toBe('XXSESSIONIDXX')
			expect(store.getters.getUserId()).toBe(null)
			expect(store.getters.getDisplayName()).toBe('')
			expect(store.getters.getActorId()).toBe('guestActorId')
			expect(store.getters.getActorType()).toBe('guests')
		})

		test('setCurrentParticipant with type USER keeps user id and updates all relevant attributes', () => {
			store.dispatch('setCurrentUser', {
				uid: 'userId',
				displayName: 'display-name',
			})

			store.dispatch('setCurrentParticipant', {
				actorId: 'userActorId',
				sessionId: 'XXSESSIONIDXX',
				participantType: PARTICIPANT.TYPE.USER,
			})

			expect(store.getters.getSessionId()).toBe('XXSESSIONIDXX')

			// user values unchanged
			expect(store.getters.getUserId()).toBe('userId')
			expect(store.getters.getDisplayName()).toBe('display-name')
			expect(store.getters.getActorId()).toBe('userId')
			expect(store.getters.getActorType()).toBe('users')
		})
	})
})
