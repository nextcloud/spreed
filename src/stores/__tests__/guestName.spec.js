/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
*/

import { setGuestNickname } from '@nextcloud/auth'
import { t } from '@nextcloud/l10n'
import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { setGuestUserName } from '../../services/participantsService.js'
import { generateOCSErrorResponse } from '../../test-helpers.js'
import { useActorStore } from '../actor.ts'
import { useGuestNameStore } from '../guestName.js'

vi.mock('../../services/participantsService', () => ({
	setGuestUserName: vi.fn(),
}))
vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(),
	setGuestNickname: vi.fn(),
}))

describe('guestNameStore', () => {
	let store
	let actorStore

	beforeEach(() => {
		setActivePinia(createPinia())
		store = useGuestNameStore()
		actorStore = useActorStore()
	})

	afterEach(() => {
		vi.clearAllMocks()
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

	test('gets suffix with guest display name', () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'actor-display-name-one',
		}

		store.addGuestName(actor1, { noUpdate: false })

		// Assert
		expect(store.getGuestNameWithGuestSuffix('token-1', 'actor-id1')).toBe('actor-display-name-one (guest)')
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
	})

	test('stores the display name when guest submits it', async () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: t('spreed', 'Guest'),
		}

		actorStore.setCurrentUser({ uid: 'actor-id1' })

		const newName = 'actor 1'

		// Mock implementation of setGuestUserName
		setGuestUserName.mockResolvedValue()

		// Act
		await store.submitGuestUsername(actor1.token, newName)

		// Assert
		expect(setGuestUserName).toHaveBeenCalledWith(actor1.token, newName)
		expect(setGuestNickname).toHaveBeenCalledWith(newName)
		expect(store.getGuestName('token-1', 'actor-id1')).toBe('actor 1')
		expect(actorStore.displayName).toBe('actor 1')
	})

	test('resets to previous display name if there is an error in setting the new one', async () => {
		// Arrange
		const actor1 = {
			token: 'token-1',
			actorId: 'actor-id1',
			actorDisplayName: 'old actor 1',
		}
		console.error = vi.fn()

		actorStore.setCurrentUser({ uid: 'actor-id1' })
		store.addGuestName(actor1, { noUpdate: false })

		const newName = 'actor 1'

		// Mock implementation of setGuestUserName
		const error = generateOCSErrorResponse({ payload: {}, status: 400 })
		setGuestUserName.mockRejectedValue(error)

		// Act
		await store.submitGuestUsername(actor1.token, newName)

		// Assert
		expect(setGuestUserName).toHaveBeenCalledWith(actor1.token, newName)
		expect(actorStore.displayName).toBe(actor1.actorDisplayName)
	})
})
