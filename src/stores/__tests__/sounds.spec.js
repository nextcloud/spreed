/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(),
}))
vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(() => Promise.resolve()),
	},
}))
vi.mock('../../services/BrowserStorage.js', () => ({
	default: {
		getItem: vi.fn(),
		setItem: vi.fn(),
	},
}))
vi.mock('../../services/CapabilitiesManager.ts', () => ({
	getTalkConfig: vi.fn(),
}))

/**
 * The initial value is computed when the module is loaded, so load it fresh for every test
 */
async function loadSoundsStore() {
	vi.resetModules()
	const { useSoundsStore } = await import('../sounds.js')
	setActivePinia(createPinia())
	return useSoundsStore()
}

describe('soundsStore', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		getCurrentUser.mockReturnValue({ uid: 'alice' })
		getTalkConfig.mockReturnValue(undefined)
		BrowserStorage.getItem.mockReturnValue(null)
	})

	describe('initial value for users', () => {
		it('takes the value from the capabilities on Talk 24+', async () => {
			getTalkConfig.mockReturnValue(false)
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(false)
			expect(getTalkConfig).toHaveBeenCalledWith('local', 'call', 'play-sounds')
		})

		it('prefers the capabilities over browser storage', async () => {
			getTalkConfig.mockReturnValue(true)
			BrowserStorage.getItem.mockReturnValue('no')
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(true)
		})

		it('falls back to browser storage when the server has no capability', async () => {
			BrowserStorage.getItem.mockReturnValue('no')
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(false)
		})

		it('defaults to enabled without capability or storage', async () => {
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(true)
		})
	})

	describe('initial value for guests', () => {
		beforeEach(() => {
			getCurrentUser.mockReturnValue(null)
		})

		it('prefers browser storage over the capabilities', async () => {
			getTalkConfig.mockReturnValue(true)
			BrowserStorage.getItem.mockReturnValue('no')
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(false)
		})

		it('takes the value from the capabilities without storage', async () => {
			getTalkConfig.mockReturnValue(false)
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(false)
		})

		it('defaults to enabled on older servers', async () => {
			const store = await loadSoundsStore()
			expect(store.shouldPlaySounds).toBe(true)
		})
	})

	describe('setShouldPlaySounds', () => {
		it('saves on the server only when the capability exists', async () => {
			getTalkConfig.mockReturnValue(true)
			const store = await loadSoundsStore()
			await store.setShouldPlaySounds(false)
			expect(axios.post).toHaveBeenCalledWith(
				generateOcsUrl('apps/spreed/api/v1/settings/user'),
				{ key: 'play_sounds', value: 'no' },
			)
			expect(BrowserStorage.setItem).not.toHaveBeenCalled()
			expect(store.shouldPlaySounds).toBe(false)
		})

		it('saves to browser storage instead on older servers, since they cannot hand it back', async () => {
			const store = await loadSoundsStore()
			await store.setShouldPlaySounds(false)
			expect(axios.post).not.toHaveBeenCalled()
			expect(BrowserStorage.setItem).toHaveBeenCalledWith('play_sounds', 'no')
			expect(store.shouldPlaySounds).toBe(false)
		})

		it('saves guests to browser storage', async () => {
			getCurrentUser.mockReturnValue(null)
			const store = await loadSoundsStore()
			await store.setShouldPlaySounds(true)
			expect(axios.post).not.toHaveBeenCalled()
			expect(BrowserStorage.setItem).toHaveBeenCalledWith('play_sounds', 'yes')
		})
	})
})
