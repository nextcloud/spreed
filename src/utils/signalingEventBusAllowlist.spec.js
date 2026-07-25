/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import { EventBus } from '../services/EventBus.ts'
import Signaling from './signaling.js'

describe('Signaling._trigger EventBus allow-list', () => {
	afterEach(() => {
		vi.restoreAllMocks()
	})

	test('forwards every event to the EventBus by default', () => {
		const base = new Signaling.Base()
		const emitSpy = vi.spyOn(EventBus, 'emit')
		const handler = vi.fn()
		base.on('fooEvent', handler)

		base._trigger('fooEvent', ['x'])

		expect(handler).toHaveBeenCalledWith('x')
		expect(emitSpy).toHaveBeenCalledWith('signaling-foo-event', ['x'])
	})

	test('with an allow-list, only listed events reach the EventBus but local handlers always run', () => {
		const base = new Signaling.Base()
		base.setEventBusEmitAllowlist(['supportedFeatures'])
		const emitSpy = vi.spyOn(EventBus, 'emit')
		const handler = vi.fn()
		base.on('joinRoom', handler)

		// Not allow-listed: local handler runs, nothing leaks to the EventBus.
		base._trigger('joinRoom', ['token'])
		expect(handler).toHaveBeenCalledWith('token')
		expect(emitSpy).not.toHaveBeenCalledWith('signaling-join-room', ['token'])

		// Allow-listed: forwarded to the EventBus.
		base._trigger('supportedFeatures', [['chat-relay']])
		expect(emitSpy).toHaveBeenCalledWith('signaling-supported-features', [['chat-relay']])
	})

	test('passing null restores forwarding of every event', () => {
		const base = new Signaling.Base()
		base.setEventBusEmitAllowlist(['supportedFeatures'])
		base.setEventBusEmitAllowlist(null)
		const emitSpy = vi.spyOn(EventBus, 'emit')

		base._trigger('joinRoom', ['token'])
		expect(emitSpy).toHaveBeenCalledWith('signaling-join-room', ['token'])
	})
})
