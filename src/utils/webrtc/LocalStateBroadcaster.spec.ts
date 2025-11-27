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
import {
	LocalStateBroadcaster,
	LocalStateBroadcasterMcu,
	LocalStateBroadcasterNoMcu,
} from './LocalStateBroadcaster.ts'
import { CallParticipantCollection } from './models/CallParticipantCollection.js'
import {
	CallParticipantModel,
	ConnectionState,
} from './models/CallParticipantModel.js'

// Augment models with the public methods added to their prototype by the
// EmitterMixin.
declare module './models/CallParticipantCollection.js' {
	interface CallParticipantCollection {
		on(event: string, handler: (callParticipantCollection: CallParticipantCollection, ...args: any[]) => void): void
		off(event: string, handler: (callParticipantCollection: CallParticipantCollection, ...args: any[]) => void): void
	}
}

declare module './models/CallParticipantModel.js' {
	interface CallParticipantModel {
		on(event: string, handler: (callParticipantModel: CallParticipantModel, ...args: any[]) => void): void
		off(event: string, handler: (callParticipantModel: CallParticipantModel, ...args: any[]) => void): void
	}
}

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
		vi.useFakeTimers()

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

	describe('LocalStateBroadcasterMcu', () => {
		test('add single participant', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(0)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(3)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3, 'status', 'videoOn')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(2)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })

			let timeoutCount = 1

			// Test after 1, 2, 4, 8 and 16 seconds have passed since the
			// participant was added
			const timeouts = [1, 2, 4, 8, 16]
			timeouts.forEach((second) => {
				vi.advanceTimersByTime(second * 1000 - 1)

				expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(3 * timeoutCount)
				expect(webRtc.sendTo).toHaveBeenCalledTimes(2 * timeoutCount)

				vi.advanceTimersByTime(1)

				expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(3 * timeoutCount + 3)
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3 * timeoutCount + 1, 'status', 'audioOn')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3 * timeoutCount + 2, 'status', 'speaking')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3 * timeoutCount + 3, 'status', 'videoOn')

				expect(webRtc.sendTo).toHaveBeenCalledTimes(2 * timeoutCount + 2)
				expect(webRtc.sendTo).toHaveBeenNthCalledWith(2 * timeoutCount + 1, 'thePeerId', 'unmute', { name: 'audio' })
				expect(webRtc.sendTo).toHaveBeenNthCalledWith(2 * timeoutCount + 2, 'thePeerId', 'unmute', { name: 'video' })

				timeoutCount++
			})

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(18)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(12)
		})

		test('change current state while sending initial state', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(5, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(6, 'status', 'videoOn')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(4, 'thePeerId', 'unmute', { name: 'video' })

			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(false)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(false)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(false)

			vi.advanceTimersByTime(2000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(8)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(7, 'status', 'audioOff')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(8, 'status', 'videoOff')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(5, 'thePeerId', 'mute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(6, 'thePeerId', 'mute', { name: 'video' })
		})

		test('add several participants', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(5, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(6, 'status', 'videoOn')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(4, 'thePeerId', 'unmute', { name: 'video' })

			callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

			vi.advanceTimersByTime(3000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(15)
			for (let i = 0; i < 3; i++) {
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 3 + 1, 'status', 'audioOn')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 3 + 2, 'status', 'speaking')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 3 + 3, 'status', 'videoOn')
			}

			expect(webRtc.sendTo).toHaveBeenCalledTimes(12)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(5, 'thePeerId2', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(6, 'thePeerId2', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(7, 'thePeerId2', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(8, 'thePeerId2', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(9, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(10, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(11, 'thePeerId2', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(12, 'thePeerId2', 'unmute', { name: 'video' })

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(24)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(24)
		})

		test('remove one of several participants while sending initial state', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)

			callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

			vi.advanceTimersByTime(3000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(15)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(12)

			callParticipantCollection.remove('thePeerId')

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(24)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(18)
		})

		test('remove the last participant', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)

			callParticipantCollection.remove('thePeerId')

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)
		})

		test('destroy while sending initial state', () => {
			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)

			localStateBroadcaster.destroy()

			vi.advanceTimersByTime(10000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(4)
		})

		test('add and remove participant after destroying', () => {
			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection)

			localStateBroadcaster.destroy()

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(10000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)

			callParticipantCollection.remove('thePeerId')

			vi.advanceTimersByTime(10000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})
	})

	describe('LocalStateBroadcasterNoMcu', () => {
		test('add participant and change to connected', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

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

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

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
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

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
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

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

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

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
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('remove participant and change to completed', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('destroy and change to connected', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			localStateBroadcaster.destroy()

			callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('destroy and change to completed', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			localStateBroadcaster.destroy()

			callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('add and remove participant after destroying', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection)

			localStateBroadcaster.destroy()

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })
			const callParticipantModel2 = callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('connectionState', ConnectionState.CONNECTED)
			callParticipantModel2.set('connectionState', ConnectionState.CONNECTED)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})
	})
})
