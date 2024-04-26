/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import mockConsole from 'jest-mock-console'
import { createPinia, setActivePinia } from 'pinia'

import { showError } from '@nextcloud/dialogs'

import { useTalkHashStore } from '../talkHash.js'

jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

describe('talkHashStore', () => {
	let talkHashStore
	let restoreConsole

	beforeEach(() => {
		setActivePinia(createPinia())
		talkHashStore = useTalkHashStore()
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
			talkHashStore.updateTalkVersionHash({
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(false)

			talkHashStore.updateTalkVersionHash({
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(false)

			talkHashStore.updateTalkVersionHash({
				headers: { 'x-nextcloud-talk-hash': 'hash-changed-1' },
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(true)
		})

		test('does not update hash when no header given', () => {
			// initial one
			talkHashStore.updateTalkVersionHash({
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(false)

			talkHashStore.updateTalkVersionHash({
				status: 200,
				// no header
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(false)
		})

		test('does not error if first response had no hash', () => {
			// initial one
			talkHashStore.updateTalkVersionHash({
				status: 200,
				// no headers
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(false)

			talkHashStore.updateTalkVersionHash({
				headers: { 'x-nextcloud-talk-hash': 'hash-1' },
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(false)

			talkHashStore.updateTalkVersionHash({
				headers: { 'x-nextcloud-talk-hash': 'hash-changed-2' },
			})

			expect(talkHashStore.isNextcloudTalkHashDirty).toEqual(true)
		})
	})

	describe('maintenance mode warning', () => {
		test('displays and clears maintenance mode warning if response contains a 503 status', () => {
			const hideToast = jest.fn()

			showError.mockImplementation(() => ({
				hideToast,
			}))

			expect(talkHashStore.maintenanceWarningToast).toBe(null)

			talkHashStore.checkMaintenanceMode({
				status: 503,
			})

			expect(talkHashStore.maintenanceWarningToast.hideToast).toBeDefined()
			expect(showError).toHaveBeenCalled()

			talkHashStore.clearMaintenanceMode()
			expect(hideToast).toHaveBeenCalled()

			expect(talkHashStore.maintenanceWarningToast).toBe(null)
		})

		test('does not display toast if status is not 503', () => {
			talkHashStore.checkMaintenanceMode({
				status: 200,
			})

			expect(talkHashStore.maintenanceWarningToast).toBe(null)
			expect(showError).not.toHaveBeenCalled()
		})

		test('does not display toast if status is not 503', () => {
			talkHashStore.checkMaintenanceMode({
				status: 200,
			})

			expect(talkHashStore.maintenanceWarningToast).toBe(null)
			expect(showError).not.toHaveBeenCalled()
		})

		test('does nothing when clearing absent warning', () => {
			talkHashStore.clearMaintenanceMode()
		})
	})
})
