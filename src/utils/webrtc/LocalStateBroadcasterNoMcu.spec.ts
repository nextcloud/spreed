/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
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
import { LocalStateBroadcasterNoMcu } from './LocalStateBroadcasterNoMcu.ts'
import { CallParticipantCollection } from './models/CallParticipantCollection.js'
import {
	CallParticipantModel,
	ConnectionState,
} from './models/CallParticipantModel.js'
import { LocalCallParticipantModel } from './models/LocalCallParticipantModel.js'

describe('LocalStateBroadcasterNoMcu', () => {
	let webRtc: WebRtc
	let internalWebRtc: InternalWebRtc
	let callParticipantCollection: CallParticipantCollection
	let localCallParticipantModel: LocalCallParticipantModel

	let localStateBroadcasterNoMcu: LocalStateBroadcasterNoMcu

	beforeEach(() => {
		internalWebRtc = new (function(this: InternalWebRtc) {
			this.isAudioEnabled = vi.fn()
			this.isSpeaking = vi.fn()
			this.isVideoEnabled = vi.fn()
		} as any)()

		webRtc = new (function(this: WebRtc) {
			WildEmitter.mixin(this)

			this.webrtc = internalWebRtc

			this.sendDataChannelTo = vi.fn()
			this.sendTo = vi.fn()
		} as any)()

		callParticipantCollection = new CallParticipantCollection()

		localCallParticipantModel = new LocalCallParticipantModel()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('add participant and change to connected', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(3)
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'audioOn')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'status', 'speaking')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'status', 'videoOn')

		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
	})

	test('add participant and change to completed', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)

		callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(3)
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'audioOn')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'status', 'speaking')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'status', 'videoOn')

		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
	})

	test('add participant and change to connected and then completed', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'audioOff')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'status', 'videoOff')

		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'mute', { name: 'audio' })
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'mute', { name: 'video' })

		callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
	})

	test('add participant and change to connected from different states', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'audioOff')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'status', 'videoOff')

		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'mute', { name: 'audio' })
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'mute', { name: 'video' })

		callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)

		// Completed -> Connected could happen with an ICE restart
		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)

		callParticipantModel.set('connectionState', ConnectionState.DISCONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)

		// Failed -> Checking could happen with an ICE restart
		callParticipantModel.set('connectionState', ConnectionState.FAILED)
		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
	})

	test('add several participants and change to connected', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })
		const callParticipantModel2 = callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)
		callParticipantModel2.set('connectionState', ConnectionState.CHECKING)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(3)
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'audioOn')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'status', 'speaking')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'status', 'videoOn')

		expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })

		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(false)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(false)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(false)

		callParticipantModel2.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(5)
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(4, 'thePeerId2', 'status', 'audioOff')
		expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(5, 'thePeerId2', 'status', 'videoOff')

		expect(webRtc.sendTo).toHaveBeenCalledTimes(4)
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(3, 'thePeerId2', 'mute', { name: 'audio' })
		expect(webRtc.sendTo).toHaveBeenNthCalledWith(4, 'thePeerId2', 'mute', { name: 'video' })
	})

	test('remove participant and change to connected', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		callParticipantCollection.remove('thePeerId')

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
	})

	test('remove participant and change to completed', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		callParticipantCollection.remove('thePeerId')

		callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
	})

	test('destroy and change to connected', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		localStateBroadcasterNoMcu.destroy()

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
	})

	test('destroy and change to completed', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		localStateBroadcasterNoMcu.destroy()

		callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
	})

	test('add and remove participant after destroying', () => {
		localStateBroadcasterNoMcu = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		localStateBroadcasterNoMcu.destroy()

		const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })
		const callParticipantModel2 = callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

		callParticipantModel.set('connectionState', ConnectionState.CHECKING)

		callParticipantCollection.remove('thePeerId')

		callParticipantModel.set('connectionState', ConnectionState.CONNECTED)
		callParticipantModel2.set('connectionState', ConnectionState.CONNECTED)

		expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
	})
})
