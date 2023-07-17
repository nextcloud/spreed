import { setActivePinia, createPinia } from 'pinia'

import { PRIVACY } from '../../constants.js'
import { useSettingsStore } from '../settings.js'

jest.mock('@nextcloud/initial-state',
	() => ({
		loadState: jest.fn().mockReturnValue(0),
	}))

jest.mock('../../services/settingsService',
	() => ({
		setReadStatusPrivacy: jest.fn().mockReturnValue('success'),
		setTypingStatusPrivacy: jest.fn().mockReturnValue('success'),
	}))

describe('settingsStore', () => {
	beforeEach(() => {
		// creates a fresh pinia and make it active, so it's automatically picked
		// up by any useStore() call without having to pass it to it:
		// `useStore(pinia)`
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
