/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

import { loadState } from '@nextcloud/initial-state'

import { PRIVACY } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { setReadStatusPrivacy, setTypingStatusPrivacy } from '../../services/settingsService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { useSettingsStore } from '../settings.js'

jest.mock('../../services/settingsService', () => ({
	setReadStatusPrivacy: jest.fn(),
	setTypingStatusPrivacy: jest.fn(),
}))

jest.mock('../../services/BrowserStorage.js', () => ({
	getItem: jest.fn(),
	setItem: jest.fn(),
}))

describe('settingsStore', () => {
	let settingsStore

	beforeEach(() => {
		loadState.mockImplementation(() => PRIVACY.PUBLIC)
		setActivePinia(createPinia())
		settingsStore = useSettingsStore()
	})

	afterEach(async () => {
		jest.clearAllMocks()
	})

	describe('reading and typing statuses', () => {
		it('shows correct loaded values for statuses', () => {
			// Assert
			expect(settingsStore.readStatusPrivacy).toBe(PRIVACY.PUBLIC)
			expect(settingsStore.typingStatusPrivacy).toBe(PRIVACY.PUBLIC)
		})

		it('updates statuses correctly', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: [] })
			setReadStatusPrivacy.mockResolvedValueOnce(response)
			setTypingStatusPrivacy.mockResolvedValueOnce(response)

			// Act: update read status and typing status privacy
			await settingsStore.updateReadStatusPrivacy(PRIVACY.PRIVATE)
			await settingsStore.updateTypingStatusPrivacy(PRIVACY.PRIVATE)

			// Assert
			expect(settingsStore.readStatusPrivacy).toBe(PRIVACY.PRIVATE)
			expect(settingsStore.typingStatusPrivacy).toBe(PRIVACY.PRIVATE)
		})
	})

	describe('media settings dialog', () => {
		// FIXME: BrowserStorage.getItem('cachedConversations') is always called whenever capabilitiesManager.ts is imported
		const EXTRA_CALLS = 3
		it('shows correct stored values for conversations', () => {
			// Arrange
			settingsStore.showMediaSettings['token-1'] = true
			settingsStore.showMediaSettings['token-2'] = false

			// Act
			const results = [settingsStore.getShowMediaSettings('token-1'),
				settingsStore.getShowMediaSettings('token-2')]

			// Assert
			expect(results).toEqual([true, false])
			expect(BrowserStorage.getItem).toHaveBeenCalledTimes(EXTRA_CALLS)
		})

		it('shows correct values received from BrowserStorage', () => {
			// Arrange
			BrowserStorage.getItem
				.mockReturnValueOnce(null)
				.mockReturnValueOnce('true')
				.mockReturnValueOnce('false')

			// Act
			const results = [settingsStore.getShowMediaSettings('token-1'),
				settingsStore.getShowMediaSettings('token-2'),
				settingsStore.getShowMediaSettings('token-3')]

			// Assert
			expect(results).toEqual([true, true, false])
			expect(BrowserStorage.getItem).toHaveBeenCalledTimes(EXTRA_CALLS + 3)
			expect(BrowserStorage.getItem).toHaveBeenNthCalledWith(EXTRA_CALLS + 1, 'showMediaSettings_token-1')
			expect(BrowserStorage.getItem).toHaveBeenNthCalledWith(EXTRA_CALLS + 2, 'showMediaSettings_token-2')
			expect(BrowserStorage.getItem).toHaveBeenNthCalledWith(EXTRA_CALLS + 3, 'showMediaSettings_token-3')
		})

		it('updates values correctly', async () => {
			// Arrange
			settingsStore.showMediaSettings['token-1'] = true
			settingsStore.showMediaSettings['token-2'] = false

			// Act
			settingsStore.setShowMediaSettings('token-1', false)
			settingsStore.setShowMediaSettings('token-2', true)
			const results = [settingsStore.getShowMediaSettings('token-1'),
				settingsStore.getShowMediaSettings('token-2')]

			// Assert
			expect(results).toEqual([false, true])
			expect(BrowserStorage.getItem).toHaveBeenCalledTimes(EXTRA_CALLS)
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(2)
			expect(BrowserStorage.setItem).toHaveBeenNthCalledWith(1, 'showMediaSettings_token-1', 'false')
			expect(BrowserStorage.setItem).toHaveBeenNthCalledWith(2, 'showMediaSettings_token-2', 'true')
		})
	})
})
