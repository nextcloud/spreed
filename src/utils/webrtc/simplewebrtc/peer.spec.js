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
	describe('offer', () => {
		let peer
		let videoSender

		beforeEach(() => {
			videoSender = {
				track: { kind: 'video' },
				getParameters: vi.fn(() => ({})),
				setParameters: vi.fn(() => Promise.resolve()),
			}

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
					getSenders: vi.fn(() => [videoSender]),
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

		test('sets the Firefox simulcast encodings in ascending order', async () => {
			await Peer.prototype.offer.call(peer)

			expect(videoSender.setParameters).toHaveBeenCalledTimes(1)
			const encodings = videoSender.setParameters.mock.calls[0][0].encodings
			expect(encodings.map((encoding) => encoding.rid)).toEqual(['l', 'm', 'h'])
			expect(encodings.map((encoding) => encoding.maxBitrate)).toEqual([100000, 300000, 900000])
			expect(encodings.map((encoding) => encoding.scaleResolutionDownBy)).toEqual([4, 2, undefined])
		})

		test('tells Janus the rid order when Firefox sends simulcast', async () => {
			await Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(peer.send).toHaveBeenCalledWith('offer', {
				type: 'offer',
				sdp: 'v=0\r\n',
				rid_order: 'lmh',
			})
		})

		test('keeps the nick along with the rid order', async () => {
			peer.parent.config.nick = 'Guest'

			await Peer.prototype.offer.call(peer)

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

			await Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(videoSender.setParameters).not.toHaveBeenCalled()
			expect(peer.send.mock.calls[0][1]).not.toHaveProperty('rid_order')
		})

		test('does not set the rid order for screen shares', async () => {
			peer.type = 'screen'

			await Peer.prototype.offer.call(peer)

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

			await Peer.prototype.offer.call(peer)

			await vi.waitFor(() => expect(peer.send).toHaveBeenCalledTimes(1))
			expect(videoSender.setParameters).not.toHaveBeenCalled()
			expect(peer.send.mock.calls[0][1].sdp).toContain('a=ssrc-group:SIM 1111 ')
			expect(peer.send.mock.calls[0][1]).not.toHaveProperty('rid_order')
		})
	})
})
