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

		webRtc = new (function(this: WebRtc) {
			WildEmitter.mixin(this)

			this.webrtc = internalWebRtc

			this.sendDataChannelToAll = vi.fn()

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

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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

		// Note that, even if they are not explicitly checked, further messages
		// would be nevertheless sent for a few more seconds.
	})

	test('add several participants', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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

		// Data channel messages were reset when the second participant was
		// added and sent at timeouts 0, 1 and 2 after that.
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(15)
		for (let i = 0; i < 3; i++) {
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 3 + 1, 'status', 'audioOn')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 3 + 2, 'status', 'speaking')
			expect(webRtc.sendDataChannelToAll).toHaveBeenNthCalledWith(i * 3 + 3, 'status', 'videoOn')
		}

		// Total elapsed time and relative time for each peer:
		// - 0: peer 1: 0, messages for timeout 0
		// - 1: peer 1: 1, messages for timeout 1
		//      peer 2: 0, messages for timeout 0
		// - 2: peer 1: 2
		//      peer 2: 1, messages for timeout 1
		// - 3: peer 1: 3, messages for timeout 2 (0 + 1 + 2)
		// -    peer 2: 2
		// - 4: peer 1: 4
		//      peer 2: 3, messages for timeout 2 (0 + 1 + 2)
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

		// Data channel messages at timeouts 0 and 1 since first participant was
		// added, and then at timeouts 0, 1, 2, 4, 8 and 16 after second
		// participant was added (2+6=8 blocks of messages in total).
		// Signaling messages at timeouts 0, 1, 2, 4, 8  and 16 after each
		// participant was added (6+6=12 blocks of messages in total).
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(24)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(24)
	})

	test('remove one of several participants while sending initial state', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		vi.advanceTimersByTime(1000)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(6)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(4)

		callParticipantCollection.add({ peerId: 'thePeerId2', webRtc })

		vi.advanceTimersByTime(3000)

		// Data channel messages at timeouts 0 and 1 since first participant was
		// added, and then at timeouts 0, 1 and 2 after second participant was
		// added (2+3=5 blocks of messages in total).
		// Signaling messages at timeouts 0, 1 and 2 after each participant was
		// added (3+3=6 blocks of messages in total).
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(15)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(12)

		callParticipantCollection.remove('thePeerId')

		vi.advanceTimersByTime(100000)

		// Data channel messages at timeouts 0 and 1 since first participant was
		// added, and then at timeouts 0, 1, 2, 4, 8 and 16 after second
		// participant was added (2+6=8 blocks of messages in total).
		// Signaling messages at timeouts 0, 1 and 2 for first participant, and
		// timeouts 0, 1, 2, 4, 8 and 16 for second participant (3+6=9 blocks of
		// messages in total).
		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(24)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(18)
	})

	test('remove the last participant', () => {
		vi.mocked(internalWebRtc.isAudioEnabled).mockReturnValue(true)
		vi.mocked(internalWebRtc.isSpeaking).mockReturnValue(true)
		vi.mocked(internalWebRtc.isVideoEnabled).mockReturnValue(true)

		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

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
		localStateBroadcasterMcu = new LocalStateBroadcasterMcu(webRtc, callParticipantCollection, localCallParticipantModel)

		callParticipantCollection.add({ peerId: 'thePeerId', webRtc })

		vi.advanceTimersByTime(1000)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(4)

		localStateBroadcasterMcu.destroy()

		vi.advanceTimersByTime(10000)

		expect(webRtc.sendDataChannelToAll).toHaveBeenCalledTimes(4)
		expect(webRtc.sendTo).toHaveBeenCalledTimes(4)
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
