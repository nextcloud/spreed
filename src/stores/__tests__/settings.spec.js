import { setActivePinia, createPinia } from 'pinia'

import { loadState } from '@nextcloud/initial-state'

import { PRIVACY } from '../../constants.js'
import { setReadStatusPrivacy, setTypingStatusPrivacy } from '../../services/settingsService.js'
import { generateOCSResponse } from '../../test-helpers.js'
import { useSettingsStore } from '../settings.js'

jest.mock('../../services/settingsService', () => ({
	setReadStatusPrivacy: jest.fn(),
	setTypingStatusPrivacy: jest.fn(),
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
