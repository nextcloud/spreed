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
import { LocalStateBroadcasterMcu } from './LocalStateBroadcasterMcu.ts'
import { CallParticipantCollection } from './models/CallParticipantCollection.js'
import { LocalCallParticipantModel } from './models/LocalCallParticipantModel.js'

describe('LocalStateBroadcasterMcu', () => {
	let webRtc: WebRtc
	let internalWebRtc: InternalWebRtc
	let callParticipantCollection: CallParticipantCollection
	let localCallParticipantModel: LocalCallParticipantModel

	let localStateBroadcasterMcu: LocalStateBroadcasterMcu

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

			this.sendTo = vi.fn()
		} as any)()

		callParticipantCollection = new CallParticipantCollection()

		localCallParticipantModel = new LocalCallParticipantModel()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	test('add single participant', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)
		localCallParticipantModel.set('name', 'theName')

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
		localCallParticipantModel.set('name', 'theName')

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
		localCallParticipantModel.set('name', 'theNewName')

		// Changing the name on the model triggers the normal state changed
		// message
		expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(9, 'status', 'nickChanged', 'theNewName')
		expect(webRtc.sendToAll).toHaveBeenNthCalledWith(1, 'nickChanged', { name: 'theNewName' })

		vi.advanceTimersByTime(2000)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(12)
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
		localCallParticipantModel.set('name', 'theName')

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
		localCallParticipantModel.set('name', 'theName')

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
		localCallParticipantModel.set('name', 'theName')

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		vi.advanceTimersByTime(1000)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(6)

		localStateBroadcasterMcu.destroy()

		vi.advanceTimersByTime(10000)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(6)
	})

	test('add and remove participant after destroying', () => {
		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		localStateBroadcasterMcu.destroy()

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
