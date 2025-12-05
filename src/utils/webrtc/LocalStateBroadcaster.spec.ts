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
import { LocalCallParticipantModel } from './models/LocalCallParticipantModel.js'

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

declare module './models/LocalCallParticipantModel.js' {
	interface LocalCallParticipantModel {
		on(event: string, handler: (localCallParticipantModel: LocalCallParticipantModel, ...args: any[]) => void): void
		off(event: string, handler: (localCallParticipantModel: LocalCallParticipantModel, ...args: any[]) => void): void
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

class PeerMock {
	id: string
	parent: object
	off: (event: string, handler: () => void) => void

	constructor(id: string) {
		this.id = id
		this.parent = {
			config: {
			},
		}
		this.off = vi.fn()
	}
}

describe('LocalStateBroadcaster', () => {
	let webRtc: WebRtc
	let internalWebRtc: InternalWebRtc
	let callParticipantCollection: CallParticipantCollection
	let localCallParticipantModel: LocalCallParticipantModel

	let localStateBroadcaster: LocalStateBroadcaster

	beforeEach(() => {
		vi.useFakeTimers()

		internalWebRtc = new (function(this: InternalWebRtc) {
			this.isAudioEnabled = vi.fn()
			this.isSpeaking = vi.fn()
			this.isVideoEnabled = vi.fn()
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
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'videoOn')

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('unmute', { name: 'video' })
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

		localCallParticipantModel.set('guestName', 'theName')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledWith('status', 'nickChanged', { name: 'theName', userid: 'theUserId' })

		expect(webRtc.sendToAll).toHaveBeenCalledTimes(1)
		expect(webRtc.sendToAll).toHaveBeenCalledWith('nickChanged', { name: 'theName' })
	})

	test('set nick as guest', () => {
		localStateBroadcaster = new BaseLocalStateBroadcaster(webRtc, callParticipantCollection, localCallParticipantModel)

		localCallParticipantModel.set('guestName', 'theName')

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

		localCallParticipantModel.set('guestName', 'theName')

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
		expect(webRtc.sendToAll).toHaveBeenCalledTimes(0)
	})

	describe('LocalStateBroadcasterMcu', () => {
		test('add single participant', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(0)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4, 'status', 'nickChanged', 'theName')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(3)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'nickChanged', { name: 'theName' })

			let timeoutCount = 1

			// Test after 1, 2, 4, 8 and 16 seconds have passed since the
			// participant was added
			const timeouts = [1, 2, 4, 8, 16]
			timeouts.forEach((second) => {
				vi.advanceTimersByTime(second * 1000 - 1)

				expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4 * timeoutCount)
				expect(webRtc.sendTo).toHaveBeenCalledTimes(3 * timeoutCount)

				vi.advanceTimersByTime(1)

				expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4 * timeoutCount + 4)
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4 * timeoutCount + 1, 'status', 'audioOn')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4 * timeoutCount + 2, 'status', 'speaking')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4 * timeoutCount + 3, 'status', 'videoOn')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4 * timeoutCount + 4, 'status', 'nickChanged', 'theName')

				expect(webRtc.sendTo).toHaveBeenCalledTimes(3 * timeoutCount + 3)
				expect(webRtc.sendTo).toHaveBeenNthCalledWith(3 * timeoutCount + 1, 'thePeerId', 'unmute', { name: 'audio' })
				expect(webRtc.sendTo).toHaveBeenNthCalledWith(3 * timeoutCount + 2, 'thePeerId', 'unmute', { name: 'video' })
				expect(webRtc.sendTo).toHaveBeenNthCalledWith(3 * timeoutCount + 3, 'thePeerId', 'nickChanged', { name: 'theName' })

				timeoutCount++
			})

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(24)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(18)
		})

		test('change current state while sending initial state', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(8)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4, 'status', 'nickChanged', 'theName')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(5, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(6, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(7, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(8, 'status', 'nickChanged', 'theName')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'nickChanged', { name: 'theName' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(4, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(5, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(6, 'thePeerId', 'nickChanged', { name: 'theName' })

			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(false)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(false)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(false)
			localCallParticipantModel.set('guestName', 'theNewName')

			vi.advanceTimersByTime(2000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(12)
			// Changing the name on the model triggers the normal state changed
			// message
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(9, 'status', 'nickChanged', 'theNewName')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(10, 'status', 'audioOff')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(11, 'status', 'videoOff')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(12, 'status', 'nickChanged', 'theNewName')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(9)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(7, 'thePeerId', 'mute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(8, 'thePeerId', 'mute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(9, 'thePeerId', 'nickChanged', { name: 'theNewName' })
		})

		test('add several participants', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(8)
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(3, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(4, 'status', 'nickChanged', 'theName')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(5, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(6, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(7, 'status', 'videoOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(8, 'status', 'nickChanged', 'theName')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(2, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(3, 'thePeerId', 'nickChanged', { name: 'theName' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(4, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(5, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(6, 'thePeerId', 'nickChanged', { name: 'theName' })

			callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

			vi.advanceTimersByTime(3000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(20)
			for (let i = 0; i < 3; i++) {
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 4 + 1, 'status', 'audioOn')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 4 + 2, 'status', 'speaking')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 4 + 3, 'status', 'videoOn')
				expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 4 + 4, 'status', 'nickChanged', 'theName')
			}

			expect(webRtc.sendTo).toHaveBeenCalledTimes(18)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(7, 'thePeerId2', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(8, 'thePeerId2', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(9, 'thePeerId2', 'nickChanged', { name: 'theName' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(10, 'thePeerId2', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(11, 'thePeerId2', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(12, 'thePeerId2', 'nickChanged', { name: 'theName' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(13, 'thePeerId', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(14, 'thePeerId', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(15, 'thePeerId', 'nickChanged', { name: 'theName' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(16, 'thePeerId2', 'unmute', { name: 'audio' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(17, 'thePeerId2', 'unmute', { name: 'video' })
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(18, 'thePeerId2', 'nickChanged', { name: 'theName' })

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(32)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(36)
		})

		test('remove one of several participants while sending initial state', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(8)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)

			callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

			vi.advanceTimersByTime(3000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(20)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(18)

			callParticipantCollection.remove('thePeerId')

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(32)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(27)
		})

		test('remove the last participant', () => {
			vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
			vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
			vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(8)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)

			callParticipantCollection.remove('thePeerId')

			vi.advanceTimersByTime(100000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(8)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)
		})

		test('destroy while sending initial state', () => {
			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			vi.advanceTimersByTime(1000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)

			localStateBroadcaster.destroy()

			vi.advanceTimersByTime(10000)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(6)
		})

		test('add and remove participant after destroying', () => {
			localStateBroadcaster = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

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
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

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
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)
			localCallParticipantModel.set('guestName', 'theName')

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

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
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

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
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })
			const callParticipantModel2 = callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))
			callParticipantModel2.set('peer', new PeerMock('thePeerId2'))

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

		test('set null peer for participant as user', () => {
			webRtc.connection.settings.userId = 'theUserId'
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', null)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(1)
			expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'nickChanged', { name: 'theName', userid: 'theUserId' })

			expect(webRtc.sendTo).toHaveBeenCalledTimes(1)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'nickChanged', { name: 'theName' })
		})

		test('set null peer for participant as guest', () => {
			localCallParticipantModel.set('guestName', 'theName')

			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', null)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(1)
			expect(webRtc.sendDataChannelTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'status', 'nickChanged', 'theName')

			expect(webRtc.sendTo).toHaveBeenCalledTimes(1)
			expect(webRtc.sendTo).toHaveBeenNthCalledWith(1, 'thePeerId', 'nickChanged', { name: 'theName' })
		})

		test('remove participant and change to connected', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('remove participant and change to completed', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('remove participant and set null peer', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('peer', null)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('destroy and change to connected', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			localStateBroadcaster.destroy()

			callParticipantModel.set('connectionState', ConnectionState.CONNECTED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('destroy and change to completed', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			localStateBroadcaster.destroy()

			callParticipantModel.set('connectionState', ConnectionState.COMPLETED)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('destroy and set null peer', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

			localStateBroadcaster.destroy()

			callParticipantModel.set('peer', null)

			expect(webRtc.sendDataChannelTo).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})

		test('add and remove participant after destroying', () => {
			localStateBroadcaster = new LocalStateBroadcasterNoMcu(webRtc, callParticipantCollection, localCallParticipantModel)

			localStateBroadcaster.destroy()

			const callParticipantModel = callParticipantCollection.add({ peerId: 'thePeerId', webRtc })
			const callParticipantModel2 = callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })
			const callParticipantModel3 = callParticipantCollection.add({ peerId: 'thePeerId3', webRtc })

			callParticipantModel.set('peer', new PeerMock('thePeerId'))
			callParticipantModel2.set('peer', new PeerMock('thePeerId2'))

			callParticipantModel.set('connectionState', ConnectionState.CHECKING)

			callParticipantCollection.remove('thePeerId')

			callParticipantModel.set('connectionState', ConnectionState.CONNECTED)
			callParticipantModel2.set('connectionState', ConnectionState.CONNECTED)
			callParticipantModel3.set('peer', null)

			expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(0)
			expect(webRtc.sendTo).toHaveBeenCalledTimes(0)
		})
	})
})
