/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	CallParticipantCollection as CallParticipantCollectionType,
	CallParticipantModel as CallParticipantModelType,
	InternalWebRtc,
	LocalCallParticipantModel as LocalCallParticipantModelType,
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
import { LocalCallParticipantModel } from './models/LocalCallParticipantModel.js'

class BaseLocalStateBroadcaster extends LocalStateBroadcaster {
	protected _handleAddCallParticipantModel(callParticipantCollection: CallParticipantCollectionType, callParticipantModel: CallParticipantModelType): void {
		// Not used in base class tests
	}

	protected _handleRemoveCallParticipantModel(callParticipantCollection: CallParticipantCollectionType, callParticipantModel: CallParticipantModelType): void {
		// Not used in base class tests
	}
}

describe('LocalStateBroadcaster', () => {
	let webRtc: WebRtc
	let internalWebRtc: InternalWebRtc
	let callParticipantCollection: CallParticipantCollectionType
	let localCallParticipantModel: LocalCallParticipantModelType

	let localStateBroadcaster: LocalStateBroadcaster

	beforeEach(() => {
		internalWebRtc = new (function(this: InternalWebRtc) {
			this.isAudioEnabled = vi.fn()
			this.isSpeaking = vi.fn()
			this.isVideoEnabled = vi.fn()
			this.isVirtualBackgroundAvailable = vi.fn()
			this.isVirtualBackgroundEnabled = vi.fn()
			this.getVirtualBackground = vi.fn()
		} as any)()

		const signaling = {
			settings: {
				userId: null,
			},
		}

		webRtc = new (function(this: WebRtc) {
			WildEmitter.mixin(this)

			this.connection = signaling
			this.webrtc = internalWebRtc

			this.sendDataChannelToAll = vi.fn()
			this.sendToAll = vi.fn()

			this.sendDataChannelTo = vi.fn()
			this.sendTo = vi.fn()
		} as any)()

		callParticipantCollection = new CallParticipantCollection()

		localCallParticipantModel = new LocalCallParticipantModel()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('enable audio', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('audioOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'audioOn')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'audio' })
	})

	test('disable audio', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('audioOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'audioOff')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('mute', { name: 'audio' })
	})

	test('enable speaking', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('speaking')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'speaking')
	})

	test('disable speaking', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('stoppedSpeaking')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'stoppedSpeaking')
	})

	test('enable video', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('videoOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOn', { effect: null })

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'video' })
	})

	test('enable video with background effect', () => {
		vi.mocked(internalWebRtc.isVirtualBackgroundAvailable).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVirtualBackgroundEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.getVirtualBackground).mockReturnValue({ backgroundType: 'blur' })

		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('videoOn')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOn', { effect: 'blur' })

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'video' })
	})

	test.each([
		['virtualBackgroundOn', true, true, 'blur'],
		['virtualBackgroundSet', true, true, 'image'],
		['virtualBackgroundOff', true, false, null],
		['virtualBackgroundLoadFailed', false, true, null],
	])('change background effect with %s', (event, available, enabled, effect) => {
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVirtualBackgroundAvailable).mockReturnValue(available)
		vi.mocked(internalWebRtc.isVirtualBackgroundEnabled).mockReturnValue(enabled)
		vi.mocked(internalWebRtc.getVirtualBackground).mockReturnValue({ backgroundType: effect ?? 'blur' })

		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit(event)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOn', { effect })

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(0)
	})

	test('change background effect with video disabled', () => {
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(false)
		vi.mocked(internalWebRtc.isVirtualBackgroundAvailable).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVirtualBackgroundEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.getVirtualBackground).mockReturnValue({ backgroundType: 'blur' })

		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('virtualBackgroundOn')
		webRtc.emit('virtualBackgroundSet')
		webRtc.emit('virtualBackgroundOff')
		webRtc.emit('virtualBackgroundLoadFailed')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
		expect(webRtc.sendToAll).toHaveBeenCalledTimes(0)
	})

	test('disable video', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		webRtc.emit('videoOff')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOff')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('mute', { name: 'video' })
	})

	test('set nick as user', () => {
		webRtc.connection.settings.userId = 'theUserId'

		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		localCallParticipantModel.set('name', 'theName')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'nickChanged', { name: 'theName', userid: 'theUserId' })

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('nickChanged', { name: 'theName' })
	})

	test('set nick as guest', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		localCallParticipantModel.set('name', 'theName')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'nickChanged', 'theName')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('nickChanged', { name: 'theName' })
	})

	test('change state after destroying', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		localStateBroadcaster.destroy()

		webRtc.emit('audioOn')
		webRtc.emit('audioOff')
		webRtc.emit('speaking')
		webRtc.emit('stoppedSpeaking')
		webRtc.emit('videoOn')
		webRtc.emit('videoOff')
		webRtc.emit('virtualBackgroundOn')
		webRtc.emit('virtualBackgroundSet')
		webRtc.emit('virtualBackgroundOff')
		webRtc.emit('virtualBackgroundLoadFailed')

		localCallParticipantModel.set('name', 'theName')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
		expect(webRtc.sendToAll).toHaveBeenCalledTimes(0)
	})
})
