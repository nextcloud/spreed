/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { ref } from 'vue'
import { useStore } from 'vuex'
import { SESSION } from '../../constants.ts'

const mocks = vi.hoisted(() => ({
	token: { current: null },
	isInCall: { current: null },
}))

vi.mock('vuex', async () => {
	const vuex = await vi.importActual('vuex')
	return { ...vuex, useStore: vi.fn() }
})
vi.mock('../useGetToken.ts', () => ({
	useGetToken: () => mocks.token.current,
}))
vi.mock('../useIsInCall.js', () => ({
	useIsInCall: () => mocks.isInCall.current,
}))
vi.mock('../../services/participantsService.js', () => ({
	setSessionState: vi.fn(),
}))

// jsdom does not track window focus, so document.hasFocus() has to be faked
let windowHasFocus = true
document.hasFocus = () => windowHasFocus

/**
 * Move the window to the background.
 */
function blurWindow() {
	windowHasFocus = false
	window.dispatchEvent(new Event('blur'))
}

/**
 * Bring the window back to the foreground.
 */
function focusWindow() {
	windowHasFocus = true
	window.dispatchEvent(new Event('focus'))
}

/**
 * Run out the pending inactivity timer, whatever its configured duration is.
 */
function runInactiveTimer() {
	return vi.advanceTimersToNextTimerAsync()
}

describe('useActiveSession', () => {
	let wrapper
	let setSessionState
	let useIsSessionActive

	/**
	 * Mount a component driving the session state, as App.vue does.
	 */
	async function mountActiveSession() {
		// The state is shared between all consumers, so it has to be reset per test
		vi.resetModules()
		const composable = await import('../useActiveSession.js')
		useIsSessionActive = composable.useIsSessionActive
		setSessionState = (await import('../../services/participantsService.js')).setSessionState
		setSessionState.mockResolvedValue({})

		wrapper = mount({
			setup() {
				composable.useActiveSession()
				return () => null
			},
		})
		await flushPromises()
	}

	beforeEach(async () => {
		vi.useFakeTimers()
		windowHasFocus = true
		mocks.token.current = ref('XXTOKENXX')
		mocks.isInCall.current = ref(false)
		useStore.mockReturnValue({ dispatch: vi.fn() })
		await mountActiveSession()
	})

	afterEach(() => {
		wrapper?.unmount()
		vi.useRealTimers()
		vi.clearAllMocks()
	})

	test('marks the session as inactive when the window stays in the background', async () => {
		blurWindow()
		expect(setSessionState).not.toHaveBeenCalled()

		await runInactiveTimer()

		expect(setSessionState).toHaveBeenCalledWith('XXTOKENXX', SESSION.STATE.INACTIVE)
		expect(useIsSessionActive().value).toBe(false)
	})

	test('keeps counting down when the mouse enters the window in the background', async () => {
		blurWindow()
		await runInactiveTimer()

		document.body.dispatchEvent(new MouseEvent('mouseenter'))
		await flushPromises()
		expect(setSessionState).toHaveBeenLastCalledWith('XXTOKENXX', SESSION.STATE.ACTIVE)
		expect(useIsSessionActive().value).toBe(true)

		// Hovering an unfocused window only postpones the update
		await runInactiveTimer()

		expect(setSessionState).toHaveBeenLastCalledWith('XXTOKENXX', SESSION.STATE.INACTIVE)
		expect(useIsSessionActive().value).toBe(false)
	})

	test('follows the server state and retries when the request failed', async () => {
		// console.error is set up to fail the test otherwise
		const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
		setSessionState.mockImplementationOnce(() => Promise.reject(new Error('Network Error')))
		blurWindow()

		await runInactiveTimer()

		// The server still has the session as active, so notifications are still sent
		expect(setSessionState).toHaveBeenCalledTimes(1)
		expect(useIsSessionActive().value).toBe(true)

		await runInactiveTimer()

		expect(setSessionState).toHaveBeenCalledTimes(2)
		expect(useIsSessionActive().value).toBe(false)
		consoleError.mockRestore()
	})

	test('follows the server state when marking as active failed', async () => {
		// console.error is set up to fail the test otherwise
		const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
		blurWindow()
		await runInactiveTimer()
		expect(useIsSessionActive().value).toBe(false)

		setSessionState.mockImplementationOnce(() => Promise.reject(new Error('Network Error')))
		focusWindow()
		await flushPromises()

		// The server still has the session as inactive and keeps notifying
		expect(setSessionState).toHaveBeenLastCalledWith('XXTOKENXX', SESSION.STATE.ACTIVE)
		expect(useIsSessionActive().value).toBe(false)
		consoleError.mockRestore()
	})

	test('repeats the skipped update when the call ended in the background', async () => {
		mocks.isInCall.current.value = true
		blurWindow()

		await runInactiveTimer()

		// Sessions in a call stay active
		expect(setSessionState).not.toHaveBeenCalled()
		expect(useIsSessionActive().value).toBe(true)

		mocks.isInCall.current.value = false
		await flushPromises()

		expect(setSessionState).toHaveBeenCalledWith('XXTOKENXX', SESSION.STATE.INACTIVE)
		expect(useIsSessionActive().value).toBe(false)
	})

	test('marks a conversation opened in the background as inactive', async () => {
		blurWindow()
		await runInactiveTimer()
		setSessionState.mockClear()

		// Joining another conversation creates a new session, which is active again
		mocks.token.current.value = 'YYTOKENYY'
		await flushPromises()
		expect(useIsSessionActive().value).toBe(true)

		await runInactiveTimer()

		expect(setSessionState).toHaveBeenCalledWith('YYTOKENYY', SESSION.STATE.INACTIVE)
		expect(useIsSessionActive().value).toBe(false)
	})
})
