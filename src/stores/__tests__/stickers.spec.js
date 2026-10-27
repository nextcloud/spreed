/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { getStickerPacks } from '../../services/stickersService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { useStickersStore } from '../stickers.ts'

vi.mock('../../services/stickersService.ts', () => ({
	getStickerPacks: vi.fn(),
}))

describe('stickersStore', () => {
	const packs = [
		{
			id: 10,
			name: 'Cats',
			stickers: [{ id: 11, name: 'sleepy', path: '/Stickers/Cats/sleepy.gif', mimetype: 'image/gif', etag: 'abc' }],
		},
	]
	let stickersStore

	beforeEach(() => {
		setActivePinia(createPinia())
		stickersStore = useStickersStore()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	it('stores the fetched packs', async () => {
		getStickerPacks.mockResolvedValueOnce(generateOCSResponse({ payload: packs }))

		await stickersStore.fetchPacks()

		expect(stickersStore.packs).toEqual(packs)
		expect(stickersStore.loaded).toBe(true)
		expect(stickersStore.loading).toBe(false)
	})

	it('fetches only once at a time', async () => {
		getStickerPacks.mockResolvedValueOnce(generateOCSResponse({ payload: packs }))

		await Promise.all([stickersStore.fetchPacks(), stickersStore.fetchPacks()])

		expect(getStickerPacks).toHaveBeenCalledTimes(1)
	})

	it('keeps the packs it has and shows an error when fetching fails', async () => {
		console.error = vi.fn()
		getStickerPacks.mockResolvedValueOnce(generateOCSResponse({ payload: packs }))
		await stickersStore.fetchPacks()
		getStickerPacks.mockRejectedValueOnce(new Error('offline'))

		await stickersStore.fetchPacks()

		expect(showError).toHaveBeenCalled()
		expect(stickersStore.packs).toEqual(packs)
		expect(stickersStore.loading).toBe(false)
	})
})
