/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import adapter from 'webrtc-adapter'
import Peer from './peer.js'

vi.mock('webrtc-adapter', () => ({
	default: {
		browserDetails: {
			browser: 'firefox',
		},
	},
}))

describe('Peer', () => {
	let peer

	beforeEach(() => {
		peer = {
			type: 'video',
			sendVideoIfAvailable: true,
			enableSimulcast: true,
			maxBitrates: {
				high: 900000,
				medium: 300000,
				low: 100000,
			},
			parent: {
				config: {},
			},
			pc: {
				addTrack: vi.fn(),
				addTransceiver: vi.fn(),
				createOffer: vi.fn(() => Promise.resolve({ type: 'offer', sdp: 'v=0\r\n' })),
				setLocalDescription: vi.fn(() => Promise.resolve()),
			},
			send: vi.fn(),
		}
	})

	afterEach(() => {
		adapter.browserDetails.browser = 'firefox'
		vi.clearAllMocks()
	})

	describe('_addSentTrack', () => {
		const videoTrack = { kind: 'video' }
		const audioTrack = { kind: 'audio' }
		const stream = { id: 'stream' }

		test('declares the Firefox simulcast encodings in ascending order', () => {
			Peer.prototype._addSentTrack.call(peer, videoTrack, stream)

			expect(peer.pc.addTrack).not.toHaveBeenCalled()
			expect(peer.pc.addTransceiver).toHaveBeenCalledTimes(1)
			const [track, init] = peer.pc.addTransceiver.mock.calls[0]
			expect(track).toBe(videoTrack)
			expect(init.direction).toBe('sendrecv')
			expect(init.streams).toEqual([stream])
			expect(init.sendEncodings.map((encoding) => encoding.rid)).toEqual(['l', 'm', 'h'])
			expect(init.sendEncodings.map((encoding) => encoding.maxBitrate)).toEqual([100000, 300000, 900000])
			expect(init.sendEncodings.map((encoding) => encoding.scaleResolutionDownBy)).toEqual([4, 2, 1])
		})

		test('adds the Firefox video track without a stream', () => {
			Peer.prototype._addSentTrack.call(peer, videoTrack, null)

			expect(peer.pc.addTransceiver.mock.calls[0][1].streams).toEqual([])
		})

		test('adds audio tracks as is', () => {
			Peer.prototype._addSentTrack.call(peer, audioTrack, stream)

			expect(peer.pc.addTransceiver).not.toHaveBeenCalled()
			expect(peer.pc.addTrack).toHaveBeenCalledWith(audioTrack, stream)
		})

		test('adds the video track as is without simulcast', () => {
			peer.enableSimulcast = false

			Peer.prototype._addSentTrack.call(peer, videoTrack, stream)

			expect(peer.pc.addTransceiver).not.toHaveBeenCalled()
			expect(peer.pc.addTrack).toHaveBeenCalledWith(videoTrack, stream)
		})

		test('adds the video track as is when video is not sent', () => {
			peer.sendVideoIfAvailable = false

			Peer.prototype._addSentTrack.call(peer, videoTrack, stream)

			expect(peer.pc.addTransceiver).not.toHaveBeenCalled()
			expect(peer.pc.addTrack).toHaveBeenCalledWith(videoTrack, stream)
		})

		test('adds the video track as is for screen shares', () => {
			peer.type = 'screen'

			Peer.prototype._addSentTrack.call(peer, videoTrack, stream)

			expect(peer.pc.addTransceiver).not.toHaveBeenCalled()
			expect(peer.pc.addTrack).toHaveBeenCalledWith(videoTrack, stream)
		})

		test('adds the video track as is in Chrome', () => {
			adapter.browserDetails.browser = 'chrome'

			Peer.prototype._addSentTrack.call(peer, videoTrack, stream)

			expect(peer.pc.addTransceiver).not.toHaveBeenCalled()
			expect(peer.pc.addTrack).toHaveBeenCalledWith(videoTrack, stream)
		})
	})

	describe('offer', () => {
		test('tells Janus the rid order when Firefox sends simulcast', async () => {
			Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(peer.send).toHaveBeenCalledWith('offer', {
				type: 'offer',
				sdp: 'v=0\r\n',
				rid_order: 'lmh',
			})
		})

		test('keeps the nick along with the rid order', async () => {
			peer.parent.config.nick = 'Guest'

			Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(peer.send).toHaveBeenCalledWith('offer', {
				type: 'offer',
				sdp: 'v=0\r\n',
				nick: 'Guest',
				rid_order: 'lmh',
			})
		})

		test('does not set the rid order without simulcast', async () => {
			peer.enableSimulcast = false

			Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(peer.send.mock.calls[0][1]).not.toHaveProperty('rid_order')
		})

		test('does not set the rid order for screen shares', async () => {
			peer.type = 'screen'

			Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(peer.send.mock.calls[0][1]).not.toHaveProperty('rid_order')
		})

		test('does not set the rid order in Chrome', async () => {
			adapter.browserDetails.browser = 'chrome'
			const sdp = [
				'v=0',
				'm=video 9 UDP/TLS/RTP/SAVPF 96 97',
				'a=ssrc-group:FID 1111 2222',
				'a=ssrc:1111 cname:test',
				'a=ssrc:2222 cname:test',
				'',
			].join('\r\n')
			peer.pc.createOffer = vi.fn(() => Promise.resolve({ type: 'offer', sdp }))

			Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(peer.send.mock.calls[0][1].sdp).toContain('a=ssrc-group:SIM 1111 ')
			expect(peer.send.mock.calls[0][1]).not.toHaveProperty('rid_order')
		})
	})
})
