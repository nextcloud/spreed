/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	CallParticipantModel as CallParticipantModelType,
	InternalWebRtc,
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
import { LocalStateBroadcaster } from './LocalStateBroadcaster.ts'
import { CallParticipantCollection } from './models/CallParticipantCollection.js'

class BaseLocalStateBroadcaster extends LocalStateBroadcaster {
	protected _handleAddCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModelType): void {
		// Not used in base class tests
	}

	protected _handleRemoveCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModelType): void {
		// Not used in base class tests
	}
}

describe('LocalStateBroadcaster', () => {
	let webRtc: WebRtc
	let internalWebRtc: InternalWebRtc
	let callParticipantCollection: CallParticipantCollection

	let localStateBroadcaster: LocalStateBroadcaster

	beforeEach(() => {
		internalWebRtc = new (function(this: InternalWebRtc) {
			this.isAudioEnabled = vi.fn()
			this.isSpeaking = vi.fn()
			this.isVideoEnabled = vi.fn()
		} as any)()

		webRtc = new (function(this: WebRtc) {
			WildEmitter.mixin(this)

			this.webrtc = internalWebRtc

			this.sendDataChannelToAll = vi.fn()
			this.sendToAll = vi.fn()

			this.sendDataChannelTo = vi.fn()
			this.sendTo = vi.fn()
		} as any)()

		callParticipantCollection = new CallParticipantCollection()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('enable audio', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

		webRtc.emit('audioOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'audioOn')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'audio' })
	})

	test('disable audio', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

		webRtc.emit('audioOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'audioOff')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('mute', { name: 'audio' })
	})

	test('enable speaking', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

		webRtc.emit('speaking')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'speaking')
	})

	test('disable speaking', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

		webRtc.emit('stoppedSpeaking')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'stoppedSpeaking')
	})

	test('enable video', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

		webRtc.emit('videoOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOn')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'video' })
	})

	test('disable video', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

		webRtc.emit('videoOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOff')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('mute', { name: 'video' })
	})

	test('change state after destroying', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection)

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
