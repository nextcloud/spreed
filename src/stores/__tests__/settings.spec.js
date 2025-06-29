import { loadState } from '@nextcloud/initial-state'
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createPinia, setActivePinia } from 'pinia'
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
		BrowserStorage.getItem
			.mockReturnValueOnce('false')
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

		it('shows correct values received from BrowserStorage', () => {
			// Arrange
			BrowserStorage.getItem
				.mockReturnValueOnce('false')
			// Assert
			expect(settingsStore.showMediaSettings).toEqual(false)
			expect(BrowserStorage.getItem).toHaveBeenNthCalledWith(1, 'showMediaSettings')
		})

		it('updates values correctly', async () => {
			// Arrange
			settingsStore.showMediaSettings = true

			// Act
			settingsStore.setShowMediaSettings(false)

			// Assert
			expect(settingsStore.showMediaSettings).toEqual(false)
			expect(BrowserStorage.setItem).toHaveBeenNthCalledWith(1, 'showMediaSettings', 'false')
		})
	})
})
