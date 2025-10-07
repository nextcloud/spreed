/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { PRIVACY } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import {
	setAttachmentFolder,
	setReadStatusPrivacy,
	setTypingStatusPrivacy,
} from '../../services/settingsService.ts'
import { generateOCSResponse } from '../../test-helpers.js'
import { useSettingsStore } from '../settings.ts'

vi.mock('../../services/settingsService', () => ({
	setReadStatusPrivacy: vi.fn(),
	setTypingStatusPrivacy: vi.fn(),
	setAttachmentFolder: vi.fn(),
}))

vi.spyOn(BrowserStorage, 'getItem')
vi.spyOn(BrowserStorage, 'setItem')

describe('settingsStore', () => {
	let settingsStore

	beforeEach(() => {
		loadState.mockImplementation((app, key, fallback) => {
			if (key === 'read_status_privacy' || key === 'typing_privacy') {
				return PRIVACY.PUBLIC
			} else if (key === 'attachment_folder') {
				return '/Talk'
			} else if (key === 'attachment_folder_free_space') {
				return 1024
			}
			return fallback
		})
		setActivePinia(createPinia())
		settingsStore = useSettingsStore()
	})

	afterEach(async () => {
		vi.clearAllMocks()
		settingsStore.readStatusPrivacy = PRIVACY.PUBLIC
		settingsStore.typingStatusPrivacy = PRIVACY.PUBLIC
		settingsStore.showMediaSettings = true
		settingsStore.startWithoutMedia = false
		settingsStore.blurVirtualBackgroundEnabled = false
		settingsStore.conversationsListStyle = 'two-lines'
		settingsStore.attachmentFolder = '/Talk'
		settingsStore.attachmentFolderFreeSpace = 1024
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
			// Assert
			expect(settingsStore.showMediaSettings).toEqual(true)
			expect(BrowserStorage.getItem).toHaveBeenNthCalledWith(1, 'showMediaSettings')
		})

		it('updates values correctly', async () => {
			// Act
			settingsStore.setShowMediaSettings(false)

			// Assert
			expect(settingsStore.showMediaSettings).toEqual(false)
			expect(BrowserStorage.setItem).toHaveBeenNthCalledWith(1, 'showMediaSettings', 'false')
		})
	})

	describe('attachment folder', async () => {
		it('shows correct loaded values for statuses', () => {
			// Assert
			expect(settingsStore.attachmentFolder).toBe('/Talk')
			expect(settingsStore.attachmentFolderFreeSpace).toBe(1024)
		})

		it('updates values correctly', async () => {
			// Arrange
			const response = generateOCSResponse({ payload: [] })
			setAttachmentFolder.mockResolvedValueOnce(response)

			// Act
			await settingsStore.updateAttachmentFolder('/Talk-another')

			// Assert
			expect(setAttachmentFolder).toHaveBeenCalledWith('/Talk-another')
			expect(settingsStore.attachmentFolder).toBe('/Talk-another')
		})
	})
})
