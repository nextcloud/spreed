import { createPinia, setActivePinia } from 'pinia'

import { useGuestNameStore } from '../guestName.js'

describe('guestNameStore', () => {
	let store

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useGuestNameStore()
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('sets guest name if empty', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		}

		// Act
		store.addGuestName(actor1, { noUpdate: true })

		// Assert
		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-one')
		// non-existing token
		expect(store.getGuestName('token-2', 'actor-id1')).toBe('Guest')
		// non-existing actorId
		expect(store.getGuestName('token-1', 'actor-id2')).toBe('Guest')
	})

	test('does not overwrite guest name if not empty', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		}
		const actor1Altered = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-another',
		}

		// Act
		store.addGuestName(actor1, { noUpdate: true })
		// attempt overwriting
		store.addGuestName(actor1Altered, { noUpdate: true })

		// Assert
		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-one')
	})

	test('forces overwriting guest name', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		}
		const actor1Altered = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-another',
		}

		// Act
		store.addGuestName(actor1, { noUpdate: false })
		// attempt overwriting
		store.addGuestName(actor1Altered, { noUpdate: false })

		// Assert
		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor-display-name-another')
	})

	test('clear guest name', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		}

		const actor1Altered = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: '',
		}

		// Act
		store.addGuestName(actor1, { noUpdate: true })
		store.addGuestName(actor1Altered, { noUpdate: false })

		// Assert
		expect(store.getGuestName('token-1', 'actor-id1')).toBe('Guest')
	})

	test('translates default guest name', () => {

		expect(store.getGuestName('token-1', 'actor-id0')).toBe('Guest')
		expect(global.t).toHaveBeenCalledWith('spreed', 'Guest')
	})

	test('gets suffix with guest display name', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		}

		store.addGuestName(actor1, { noUpdate: false })

		// Assert
		expect(store.getGuestNameWithGuestSuffix('token-1', 'actor-id1')).toBe('{guest} (guest)')
		expect(global.t).toHaveBeenCalledWith('spreed', '{guest} (guest)', { guest: 'actor-display-name-one' })
	})

	test('does not get suffix for translatable default guest name', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: t('spreed', 'Guest'),
		}

		store.addGuestName(actor1, { noUpdate: false })

		// Assert
		expect(store.getGuestNameWithGuestSuffix('token-1', 'actor-id1')).toBe('Guest')
		expect(global.t).toHaveBeenCalledWith('spreed', 'Guest')
	})

})
