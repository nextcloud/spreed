/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	WebRtc,
} from '../../types/index.ts'

import {
	afterEach,
	beforeEach,
	describe,
	expect,
	test,
	vi,
} from 'vitest'
import WildEmitter from 'wildemitter'
import {
	LocalStateBroadcaster,
} from './LocalStateBroadcaster.ts'

describe('LocalStateBroadcaster', () => {
	let webRtc: WebRtc

	let localStateBroadcaster: LocalStateBroadcaster

	beforeEach(() => {
		webRtc = new (function(this: WebRtc) {
			WildEmitter.mixin(this)

			this.sendDataChannelToAll = vi.fn()
			this.sendToAll = vi.fn()
		} as any)()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('enable audio', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		webRtc.emit('audioOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'audioOn')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'audio' })
	})

	test('disable audio', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		webRtc.emit('audioOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'audioOff')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('mute', { name: 'audio' })
	})

	test('enable speaking', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		webRtc.emit('speaking')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'speaking')
	})

	test('disable speaking', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		webRtc.emit('stoppedSpeaking')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'stoppedSpeaking')
	})

	test('enable video', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		webRtc.emit('videoOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOn')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'video' })
	})

	test('disable video', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		webRtc.emit('videoOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOff')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('mute', { name: 'video' })
	})

	test('change state after destroying', () => {
		localStateBroadcaster = new LocalStateBroadcaster(webRtc)

		localStateBroadcaster.destroy()

		webRtc.emit('audioOn')
		webRtc.emit('audioOff')
		webRtc.emit('speaking')
		webRtc.emit('stoppedSpeaking')
		webRtc.emit('videoOn')
		webRtc.emit('videoOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
		expect(webRtc.sendToAll).toHaveBeenCalledTimes(0)
	})
})
