import { setActivePinia, createPinia } from 'pinia'

import { loadState } from '@nextcloud/initial-state'

import { PRIVACY } from '../../constants.js'
import { useSettingsStore } from '../settings.js'

jest.mock('../../services/settingsService',
	() => ({
		setReadStatusPrivacy: jest.fn().mockReturnValue('success'),
		setTypingStatusPrivacy: jest.fn().mockReturnValue('success'),
	}))

describe('settingsStore', () => {
	beforeEach(() => {
		loadState.mockImplementation(() => PRIVACY.PUBLIC)
		setActivePinia(createPinia())
	})

	it('shows correct loaded values for statuses', () => {
		const settingsStore = useSettingsStore()

		expect(settingsStore.readStatusPrivacy).toBe(PRIVACY.PUBLIC)
		expect(settingsStore.typingStatusPrivacy).toBe(PRIVACY.PUBLIC)
	})

	it('toggles statuses correctly', async () => {
		const settingsStore = useSettingsStore()

		expect(settingsStore.readStatusPrivacy).toBe(PRIVACY.PUBLIC)
		await settingsStore.updateReadStatusPrivacy(PRIVACY.PRIVATE)
		expect(settingsStore.readStatusPrivacy).toBe(PRIVACY.PRIVATE)

		expect(settingsStore.typingStatusPrivacy).toBe(PRIVACY.PUBLIC)
		await settingsStore.updateTypingStatusPrivacy(PRIVACY.PRIVATE)
		expect(settingsStore.typingStatusPrivacy).toBe(PRIVACY.PRIVATE)
	})
})
