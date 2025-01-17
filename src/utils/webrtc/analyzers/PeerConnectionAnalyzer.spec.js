/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	CONNECTION_QUALITY,
	PEER_DIRECTION,
	PeerConnectionAnalyzer,
} from './PeerConnectionAnalyzer.js'

/**
 * Helper function to create RTCPeerConnection mocks with just the attributes
 * and methods used by PeerConnectionAnalyzer.
 */
function newRTCPeerConnection() {
	/**
	 * RTCPeerConnectionMock constructor.
	 */
	function RTCPeerConnectionMock() {
		this._listeners = []
		this.iceConnectionState = 'new'
		this.connectionState = 'new'
		this.getStats = jest.fn()
		this.addEventListener = jest.fn((type, listener) => {
			if (type !== 'iceconnectionstatechange' || type !== 'connectionstatechange') {
				return
			}

			if (!Object.prototype.hasOwnProperty.call(this._listeners, type)) {
				this._listeners[type] = [listener]
			} else {
				this._listeners[type].push(listener)
			}
		})
		this.dispatchEvent = (event) => {
			let listeners = this._listeners[event.type]
			if (!listeners) {
				return
			}

			listeners = listeners.slice(0)
			for (let i = 0; i < listeners.length; i++) {
				const listener = listeners[i]
				listener.apply(listener, event)
			}
		}
		this.removeEventListener = jest.fn((type, listener) => {
			if (type !== 'iceconnectionstatechange' || type !== 'connectionstatechange') {
				return
			}

			const listeners = this._listeners[type]
			if (!listeners) {
				return
			}

			const index = listeners.indexOf(listener)
			if (index !== -1) {
				listeners.splice(index, 1)
			}
		})
		this._setIceConnectionState = (iceConnectionState) => {
			this.iceConnectionState = iceConnectionState

			this.dispatchEvent(new Event('iceconnectionstatechange'))
		}
		this._setConnectionState = (connectionState) => {
			this.connectionState = connectionState

			this.dispatchEvent(new Event('connectionstatechange'))
		}
	}
	return new RTCPeerConnectionMock()
}

/**
 * Helper function to create RTCStatsReport mocks with just the attributes and
 * methods used by PeerConnectionAnalyzer.
 */
function newRTCStatsReport(stats) {
	/**
	 * RTCStatsReport constructor.
	 */
	function RTCStatsReport() {
		this.values = () =>  {
			return stats
		}
	}
	return new RTCStatsReport()
}

describe('PeerConnectionAnalyzer', () => {

	let peerConnectionAnalyzer
	let peerConnection

	beforeEach(() => {
		jest.useFakeTimers()

		peerConnectionAnalyzer = new PeerConnectionAnalyzer()

		peerConnection = newRTCPeerConnection()
	})

	afterEach(() => {
		peerConnectionAnalyzer.setPeerConnection(null)

		jest.clearAllMocks()
	})

	describe('analyze sender connection', () => {

		beforeEach(() => {
			peerConnection._setIceConnectionState('connected')
			peerConnection._setConnectionState('connected')
		})

		test.each([
			['good quality', 'audio'],
			['good quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 }
				]))
				// FIXME: after the fifth value the connection quality is
				// reported with an invalid value due to the first stats report
				// being used as the base to calculate relative values of
				// cumulative stats. No connection quality should be set until
				// all the values used in the calculation are meaningful.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['medium quality', 'audio'],
			['medium quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 95, timestamp: 11000, packetsLost: 5, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 145, timestamp: 11950, packetsLost: 5, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 185, timestamp: 13020, packetsLost: 15, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 230, timestamp: 14010, packetsLost: 20, roundTripTime: 0.1 }
				]))
				// FIXME: after the fifth value the connection quality is
				// reported with an invalid value due to the first stats report
				// being used as the base to calculate relative values of
				// cumulative stats. No connection quality should be set until
				// all the values used in the calculation are meaningful.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 275, timestamp: 14985, packetsLost: 25, roundTripTime: 0.1 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
			}
		})

		test.each([
			['bad quality', 'audio'],
			['bad quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 95, timestamp: 11000, packetsLost: 5, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 145, timestamp: 11950, packetsLost: 5, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 185, timestamp: 13020, packetsLost: 15, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 220, timestamp: 14010, packetsLost: 30, roundTripTime: 0.1 }
				]))
				// FIXME: after the fifth value the connection quality is
				// reported with an invalid value due to the first stats report
				// being used as the base to calculate relative values of
				// cumulative stats. No connection quality should be set until
				// all the values used in the calculation are meaningful.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 255, timestamp: 14985, packetsLost: 45, roundTripTime: 0.1 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
			}
		})

		test.each([
			['very bad quality', 'audio'],
			['very bad quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 45, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 90, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 130, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 160, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 190, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 225, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
		})

		test.each([
			['very bad quality due to low packets', 'audio'],
			['very bad quality due to low packets', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 5, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 5, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 10, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 10, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 15, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 15, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 20, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 20, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 25, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 25, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 30, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 30, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
		})

		test.each([
			['very bad quality due to high round trip time', 'audio'],
			['very bad quality due to high round trip time', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 1.5 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 1.4 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 1.5 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 1.6 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 1.5 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 1.5 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
		})

		test.each([
			['no transmitted data due to full packet loss', 'audio'],
			['no transmitted data due to full packet loss', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 11000, packetsLost: 50, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 11950, packetsLost: 100, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 13020, packetsLost: 150, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 14010, packetsLost: 200, roundTripTime: 0.1 }
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 14985, packetsLost: 250, roundTripTime: 0.1 }
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
			}
		})

		describe('packets not updated', () => {
			test.each([
				['no transmitted data', 'audio'],
				['no transmitted data', 'video'],
			])('%s, %s', async (name, kind) => {
				peerConnection.getStats
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 13020 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 14010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// When the packets do not increase the analysis is kept on
					// hold until more stat reports are received, as it is not
					// possible to know if the packets were not transmitted or
					// the stats temporarily stalled.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 }
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(7000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				}
			})

			test.each([
				['stats stalled for a second', 'audio'],
				['stats stalled for a second', 'video'],
			])('%s, %s', async (name, kind) => {
				peerConnection.getStats
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// FIXME: after the fifth value the connection quality is
					// reported with an invalid value due to the first stats report
					// being used as the base to calculate relative values of
					// cumulative stats. No connection quality should be set until
					// all the values used in the calculation are meaningful.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// When the packets do not increase the analysis is kept on
					// hold until more stat reports are received, as it is not
					// possible to know if the packets were not transmitted or
					// the stats temporarily stalled.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 }
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(7000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				}
			})
		})
	})
})
