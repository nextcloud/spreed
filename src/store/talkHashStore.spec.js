// eslint-disable-next-line n/no-unpublished-import
import mockConsole from 'jest-mock-console'
import { createLocalVue } from '@vue/test-utils'
import talkHashStore from './talkHashStore.js'
import Vuex from 'vuex'
import { cloneDeep } from 'lodash'
import { showError } from '@nextcloud/dialogs'

jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

describe('talkHashStore', () => {
	let localVue
	let store
	let restoreConsole

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(cloneDeep(talkHashStore))
		restoreConsole = mockConsole(['debug'])
	})

	afterEach(() => {
		restoreConsole()
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('talk hash handling', () => {
		test('sets talk hash from response and updates dirty flag', () => {
			store.dispatch('updateTalkVersionHash', {
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(false)

			store.dispatch('updateTalkVersionHash', {
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(false)

			store.dispatch('updateTalkVersionHash', {
				headers: { 'x-nextcloud-talk-hash': 'hash-changed-1' },
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(true)
		})

		test('does not update hash when no header given', () => {
			// initial one
			store.dispatch('updateTalkVersionHash', {
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(false)

			store.dispatch('updateTalkVersionHash', {
				status: 200,
				// no header
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(false)
		})

		test('does not error if first response had no hash', () => {
			// initial one
			store.dispatch('updateTalkVersionHash', {
				status: 200,
				// no headers
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(false)

			store.dispatch('updateTalkVersionHash', {
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(false)

			store.dispatch('updateTalkVersionHash', {
				headers: { 'x-nextcloud-talk-hash': 'hash-changed-2' },
			})

			expect(store.getters.isNextcloudTalkHashDirty).toEqual(true)
		})
	})

	describe('maintenance mode warning', () => {
		test('displays and clears maintenance mode warning if response contains a 503 status', () => {
			const hideToast = jest.fn()

			showError.mockImplementation(() => ({
				hideToast,
			}))

			expect(store.state.maintenanceWarningToast).toBe(null)

			store.dispatch('checkMaintenanceMode', {
				status: 503,
			})

			expect(store.state.maintenanceWarningToast.hideToast).toBeDefined()
			expect(showError).toHaveBeenCalled()

			store.dispatch('clearMaintenanceMode')
			expect(hideToast).toHaveBeenCalled()

			expect(store.state.maintenanceWarningToast).toBe(null)
		})

		test('does not display toast if status is not 503', () => {
			store.dispatch('checkMaintenanceMode', {
				status: 200,
			})

			expect(store.state.maintenanceWarningToast).toBe(null)
			expect(showError).not.toHaveBeenCalled()
		})

		test('does not display toast if status is not 503', () => {
			store.dispatch('checkMaintenanceMode', {
				status: 200,
			})

			expect(store.state.maintenanceWarningToast).toBe(null)
			expect(showError).not.toHaveBeenCalled()
		})

		test('does nothing when clearing absent warning', () => {
			store.dispatch('clearMaintenanceMode')
		})
	})
})
