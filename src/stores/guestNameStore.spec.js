import { createPinia, setActivePinia } from 'pinia'

import { useGuestNameStore } from './guestNameStore.js'

describe('guestNameStore', () => {
	let store = null
	let pinia

	beforeEach(() => {
		pinia = createPinia()
		setActivePinia(pinia)
		store = useGuestNameStore()
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('sets guest name if empty', () => {
		store.setGuestNameIfEmpty({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})
		store.setGuestNameIfEmpty({
			token: 'token-1',
			actorId: 'actor-id2',
			actorDisplayName: 'actor-display-name-two',
		})

		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-one')
		expect(store.getGuestName('token-1', 'actor-id2')).toBe('actor-display-name-two')
		expect(store.getGuestName('token-2', 'actor-id1')).toBe('Guest')
		expect(store.getGuestName('token-1', 'actor-id3')).toBe('Guest')
	})

	test('does not override guest name if not empty', () => {
		store.setGuestNameIfEmpty({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})

		// attempt overwriting
		store.setGuestNameIfEmpty({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-another',
		})

		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-one')
	})

	test('force override guest name', () => {
		store.setGuestNameIfEmpty({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})

		// attempt overwriting
		store.forceGuestName({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-another',
		})

		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-another')
	})

	test('clear guest name', () => {
		store.setGuestNameIfEmpty({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		})

		store.forceGuestName({
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: '',
		})

		expect(store.getGuestName('token-1', 'actor-id1')).toBe('Guest')
	})

	test('translates default guest name', () => {
		expect(store.getGuestName('token-1', 'actor-id0')).toBe('Guest')

		expect(global.t).toHaveBeenCalledWith('spreed', 'Guest')
	})

})
