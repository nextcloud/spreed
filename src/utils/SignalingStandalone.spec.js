/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import Signaling from './signaling.js'

describe('Signaling.Standalone', () => {
	let signaling
	let reconnect

	beforeEach(() => {
		vi.stubGlobal('WebSocket', vi.fn(function() {
			this.close = vi.fn()
		}))

		signaling = new Signaling.Standalone({ token: 'XXTOKENXX' }, 'wss://signaling.example.com')
		reconnect = vi.spyOn(signaling, 'reconnect').mockImplementation(() => {})
	})

	afterEach(() => {
		vi.unstubAllGlobals()
	})

	describe('socket close', () => {
		test('reconnects when the server closes the current socket with 1001', () => {
			signaling.socket.onclose({ code: 1001, target: signaling.socket })

			expect(reconnect).toHaveBeenCalledOnce()
		})

		test('does not reconnect when the page is being unloaded', () => {
			window.dispatchEvent(new Event('pagehide'))

			signaling.socket.onclose({ code: 1001, target: signaling.socket })

			expect(reconnect).not.toHaveBeenCalled()
		})

		test('reconnects again when the page is shown again', () => {
			window.dispatchEvent(new Event('pagehide'))
			window.dispatchEvent(new Event('pageshow'))

			signaling.socket.onclose({ code: 1001, target: signaling.socket })

			expect(reconnect).toHaveBeenCalledOnce()
		})

		test('does not reconnect when the socket was already replaced', () => {
			const oldSocket = signaling.socket
			signaling.socket = null

			oldSocket.onclose({ code: 1001, target: oldSocket })

			expect(reconnect).not.toHaveBeenCalled()
		})
	})
})
