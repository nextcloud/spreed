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

jest.spyOn(BrowserStorage, 'getItem')
jest.spyOn(BrowserStorage, 'setItem')

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
		it('shows correct values received from BrowserStorage', () => {
			// Arrange
			BrowserStorage.setItem('showMediaSettings', 'false')
			settingsStore.$reset()
			// Assert
			expect(settingsStore.showMediaSettings).toEqual(false)
			expect(BrowserStorage.getItem).toHaveBeenNthCalledWith(1, 'showMediaSettings')
		})

		it('updates values correctly', async () => {
			// Arrange
			BrowserStorage.setItem('showMediaSettings', 'true')
			settingsStore.$reset()

			// Act
			settingsStore.setShowMediaSettings(false)

			// Assert
			expect(settingsStore.showMediaSettings).toEqual(false)
			expect(BrowserStorage.setItem).toHaveBeenNthCalledWith(2, 'showMediaSettings', 'false')
		})
	})
})
