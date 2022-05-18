import Vuex from 'vuex'
import { cloneDeep } from 'lodash'
import { createLocalVue } from '@vue/test-utils'

import guestNameStore from './guestNameStore.js'

describe('guestNameStore', () => {
	let localVue = null
	let store = null

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(cloneDeep(guestNameStore))
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('sets guest name if empty', () => {
		store.dispatch('setGuestNameIfEmpty', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})
		store.dispatch('setGuestNameIfEmpty', {
			token: 'token-1',
			actorId: 'actor-id2',
			actorDisplayName: 'actor-display-name-two',
		})

		expect(store.getters.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-one')
		expect(store.getters.getGuestName('token-1', 'actor-id2')).toBe('actor-display-name-two')
		expect(store.getters.getGuestName('token-2', 'actor-id1')).toBe('Guest')
		expect(store.getters.getGuestName('token-1', 'actor-id3')).toBe('Guest')
	})

	test('does not override guest name if not empty', () => {
		store.dispatch('setGuestNameIfEmpty', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})

		// attempt overwriting
		store.dispatch('setGuestNameIfEmpty', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-another',
		})

		expect(store.getters.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-one')
	})

	test('force override guest name', () => {
		store.dispatch('setGuestNameIfEmpty', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})

		// attempt overwriting
		store.dispatch('forceGuestName', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-another',
		})

		expect(store.getters.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-another')
	})

	test('clear guest name', () => {
		store.dispatch('setGuestNameIfEmpty', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})

		store.dispatch('forceGuestName', {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: '',
		})

		expect(store.getters.getGuestName('token-1', 'actor-id1')).toBe('Guest')
	})

	test('translates default guest name', () => {
		expect(store.getters.getGuestName('token-1', 'actor-id0')).toBe('Guest')

		expect(global.t).toHaveBeenCalledWith('spreed', 'Guest')
	})

})
