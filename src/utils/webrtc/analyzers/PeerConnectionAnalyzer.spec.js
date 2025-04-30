/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	CONNECTION_QUALITY,
	PEER_DIRECTION,
	PEER_TYPE,
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
 *
 * @param {Array} stats the values of the stats
 */
function newRTCStatsReport(stats) {
	/**
	 * RTCStatsReport constructor.
	 */
	function RTCStatsReport() {
		this.values = () => {
			return stats
		}
	}
	return new RTCStatsReport()
}

describe('PeerConnectionAnalyzer', () => {

	let peerConnectionAnalyzer
	let changeConnectionQualityAudioHandler
	let changeConnectionQualityVideoHandler
	let peerConnection

	beforeEach(() => {
		jest.useFakeTimers()

		peerConnectionAnalyzer = new PeerConnectionAnalyzer()

		changeConnectionQualityAudioHandler = jest.fn()
		peerConnectionAnalyzer.on('change:connectionQualityAudio', changeConnectionQualityAudioHandler)

		changeConnectionQualityVideoHandler = jest.fn()
		peerConnectionAnalyzer.on('change:connectionQualityVideo', changeConnectionQualityVideoHandler)

		peerConnection = newRTCPeerConnection()
	})

	afterEach(() => {
		peerConnectionAnalyzer.setPeerConnection(null)

		jest.clearAllMocks()
	})

	describe('analyze sender connection', () => {

		let logStatsMock

		let expectLogStatsToHaveBeenCalled

		beforeEach(() => {
			logStatsMock = jest.spyOn(peerConnectionAnalyzer, '_logStats').mockImplementation(() => {})

			expectLogStatsToHaveBeenCalled = false

			peerConnection._setIceConnectionState('connected')
			peerConnection._setConnectionState('connected')
		})

		afterEach(() => {
			if (!expectLogStatsToHaveBeenCalled) {
				expect(logStatsMock).not.toHaveBeenCalled()
			}
		})

		test.each([
			['good quality', 'audio'],
			['good quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['good quality, missing remote packet count', 'audio'],
			['good quality, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['medium quality', 'audio'],
			['medium quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 95, timestamp: 11000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 145, timestamp: 11950, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 185, timestamp: 13020, packetsLost: 15, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 230, timestamp: 14010, packetsLost: 20, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 275, timestamp: 14985, packetsLost: 25, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.MEDIUM)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.MEDIUM)
			}
		})

		test.each([
			['medium quality, missing remote packet count', 'audio'],
			['medium quality, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 15, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 20, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 25, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.MEDIUM)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.MEDIUM)
			}
		})

		test.each([
			['bad quality', 'audio'],
			['bad quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 95, timestamp: 11000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 145, timestamp: 11950, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 185, timestamp: 13020, packetsLost: 15, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 220, timestamp: 14010, packetsLost: 30, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 255, timestamp: 14985, packetsLost: 45, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.BAD)
			}
		})

		test.each([
			['bad quality, missing remote packet count', 'audio'],
			['bad quality, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 15, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 30, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 45, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.BAD)
			}
		})

		test.each([
			['very bad quality', 'audio'],
			['very bad quality', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 45, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 90, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 130, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 160, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 190, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 225, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.31')
		})

		test.each([
			['very bad quality, missing remote packet count', 'audio'],
			['very bad quality, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.31')
		})

		test.each([
			['very bad quality with low packets and packet loss', 'audio'],
			['very bad quality with low packets and packet loss', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 5, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 3, timestamp: 10000, packetsLost: 2, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 10, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 6, timestamp: 11000, packetsLost: 4, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 15, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 9, timestamp: 11950, packetsLost: 6, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 20, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 12, timestamp: 13020, packetsLost: 8, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 25, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 15, timestamp: 14010, packetsLost: 10, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 30, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 18, timestamp: 14985, packetsLost: 12, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(2)
			expect(logStatsMock).toHaveBeenNthCalledWith(1, kind, 'Low packets per second: 5.025140924550664')
			expect(logStatsMock).toHaveBeenNthCalledWith(2, kind, 'High packet lost ratio: 0.4')
		})

		test.each([
			['very bad quality with low packets and packet loss, missing remote packet count', 'audio'],
			['very bad quality with low packets and packet loss, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 5, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 2, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 10, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 4, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 15, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 6, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 20, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 8, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 25, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 10, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 30, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 12, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(2)
			expect(logStatsMock).toHaveBeenNthCalledWith(1, kind, 'Low packets per second: 5.025140924550664')
			expect(logStatsMock).toHaveBeenNthCalledWith(2, kind, 'High packet lost ratio: 0.4')
		})

		test.each([
			['good quality even with low packets if no packet loss', 'audio'],
			['good quality even with low packets if no packet loss', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 5, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 5, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 10, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 10, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 15, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 15, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 20, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 20, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 25, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 25, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 30, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 30, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'Low packets per second: 5.025140924550664')
		})

		test.each([
			['good quality even with low packets if no packet loss, missing remote packet count', 'audio'],
			['good quality even with low packets if no packet loss, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 5, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 10, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 15, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 20, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 25, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 30, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'Low packets per second: 5.025140924550664')
		})

		test.each([
			['very bad quality due to high round trip time', 'audio'],
			['very bad quality due to high round trip time', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 1.5 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 1.4 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 1.5 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 1.6 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 1.5 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 1.5 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High round trip time: 1.5133333333333334')
		})

		test.each([
			['very bad quality due to high round trip time, missing remote packet count', 'audio'],
			['very bad quality due to high round trip time, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 1.5 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 1.4 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 1.5 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 1.6 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 1.5 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 1.5 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High round trip time: 1.5133333333333334')
		})

		test.each([
			['no transmitted data due to full packet loss', 'audio'],
			['no transmitted data due to full packet loss', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 11000, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 11950, packetsLost: 100, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 13020, packetsLost: 150, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 14010, packetsLost: 200, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 14985, packetsLost: 250, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'No transmitted data, packet lost ratio: 1')
		})

		test.each([
			['no transmitted data due to full packet loss, missing remote packet count', 'audio'],
			['no transmitted data due to full packet loss, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 100, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 150, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 200, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 250, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'No transmitted data, packet lost ratio: 1')
		})

		test.each([
			['no transmitted data due to packets not updated', 'audio'],
			['no transmitted data due to packets not updated', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// When the packets do not increase the analysis is kept on hold
				// until more stat reports are received, as it is not possible
				// to know if the packets were not transmitted or the stats
				// temporarily stalled.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'No transmitted data, packet lost ratio: 1.35')
		})

		test.each([
			['no transmitted data due to packets not updated, missing remote packet count', 'audio'],
			['no transmitted data due to packets not updated, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// When the packets do not increase the analysis is kept on hold
				// until more stat reports are received, as it is not possible
				// to know if the packets were not transmitted or the stats
				// temporarily stalled.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'No transmitted data, packet lost ratio: 1.35')
		})

		test.each([
			['stats stalled for a second', 'audio'],
			['stats stalled for a second', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// When the packets do not increase the analysis is kept on hold
				// until more stat reports are received, as it is not possible
				// to know if the packets were not transmitted or the stats
				// temporarily stalled.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['stats stalled for a second, missing remote packet count', 'audio'],
			['stats stalled for a second, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// When the packets do not increase the analysis is kept on hold
				// until more stat reports are received, as it is not possible
				// to know if the packets were not transmitted or the stats
				// temporarily stalled.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		describe('remote stats stalled for a second', () => {
			test.each([
				['stats in sync, stall and keep in sync', 'audio'],
				['stats in sync, stall and keep in sync', 'video'],
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
					// A sixth report is needed for the initial calculation due
					// to the first stats report being used as the base to
					// calculate relative values of cumulative stats.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// When the packets do not increase the analysis is kept
					// on hold until more stat reports are received, as it
					// is not possible to know if the packets were not
					// transmitted or the stats temporarily stalled.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 }
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(6000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

				jest.advanceTimersByTime(1000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}
			})

			test.each([
				['stats out of sync, sync, stall and become out of sync again', 'audio'],
				['stats out of sync, sync, stall and become out of sync again', 'video'],
			])('%s, %s', async (name, kind) => {
				peerConnection.getStats
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 90, timestamp: 10800, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 130, timestamp: 11600, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 170, timestamp: 12400, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 210, timestamp: 13200, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// A sixth report is needed for the initial calculation due
					// to the first stats report being used as the base to
					// calculate relative values of cumulative stats.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// When the packets do not increase the analysis is kept
					// on hold until more stat reports are received, as it
					// is not possible to know if the packets were not
					// transmitted or the stats temporarily stalled.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 }
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(6000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}

				jest.advanceTimersByTime(2000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(8)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}
			})

			test.each([
				['stats out of sync, sync, stall and stay in sync', 'audio'],
				['stats out of sync, sync, stall and stay in sync', 'video'],
			])('%s, %s', async (name, kind) => {
				peerConnection.getStats
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 90, timestamp: 10800, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 130, timestamp: 11600, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 170, timestamp: 12400, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 210, timestamp: 13200, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// A sixth report is needed for the initial calculation due
					// to the first stats report being used as the base to
					// calculate relative values of cumulative stats.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// When the packets do not increase the analysis is kept
					// on hold until more stat reports are received, as it
					// is not possible to know if the packets were not
					// transmitted or the stats temporarily stalled.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 400, timestamp: 17000, packetsLost: 0, roundTripTime: 0.1 }
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(6000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}

				jest.advanceTimersByTime(2000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(8)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}
			})

			test.each([
				['stats in sync, stall, stay in sync, stall, stay in sync', 'audio'],
				['stats in sync, stall, stay in sync, stall, stay in sync', 'video'],
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
					// A sixth report is needed for the initial calculation due
					// to the first stats report being used as the base to
					// calculate relative values of cumulative stats.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 }
					]))
					// When the packets do not increase the analysis is kept
					// on hold until more stat reports are received, as it
					// is not possible to know if the packets were not
					// transmitted or the stats temporarily stalled.
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 450, timestamp: 17990 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 450, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 500, timestamp: 19005 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 450, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 }
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 550, timestamp: 20000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 550, timestamp: 20000, packetsLost: 0, roundTripTime: 0.1 }
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(6000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

				jest.advanceTimersByTime(1000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}

				jest.advanceTimersByTime(4000)
				// Force the promises returning the stats to be executed.
				await null

				expect(peerConnection.getStats).toHaveBeenCalledTimes(11)

				if (kind === 'audio') {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
				} else {
					expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
					expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
					expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
					expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				}
			})
		})

		test.each([
			['no transmitted data for two seconds', 'audio'],
			['no transmitted data for two seconds', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// When the packets do not increase the analysis is kept on hold
				// until more stat reports are received, as it is not possible
				// to know if the packets were not transmitted or the stats
				// temporarily stalled. But if the packets are not updated three
				// times in a row it is assumed that the packets were not
				// transmitted.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.825')
		})

		test.each([
			['no transmitted data for two seconds, missing remote packet count', 'audio'],
			['no transmitted data for two seconds, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// When the packets do not increase the analysis is kept on hold
				// until more stat reports are received, as it is not possible
				// to know if the packets were not transmitted or the stats
				// temporarily stalled. But if the packets are not updated three
				// times in a row it is assumed that the packets were not
				// transmitted.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.825')
		})

		test.each([
			['regressing packet count at the beginning', 'audio'],
			['regressing packet count at the beginning', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 1500, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 1500, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// If the packet count changes to a lower value the stats are
				// reset.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['regressing packet count at the beginning, missing remote packet count', 'audio'],
			['regressing packet count at the beginning, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 1500, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// If the packet count changes to a lower value the stats are
				// reset.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(6000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['regressing packet count', 'audio'],
			['regressing packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// If the packet count changes to a lower value the stats are
				// reset.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 17000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 19005, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 20000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 20000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 21010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 21010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
			}

			jest.advanceTimersByTime(4000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(11)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(12)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(3)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(3, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(3)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(3, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test.each([
			['regressing packet count, missing remote packet count', 'audio'],
			['regressing packet count, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// If the packet count changes to a lower value the stats are
				// reset.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 17000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 19005, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 20000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 20000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 21010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 21010, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
			}

			jest.advanceTimersByTime(4000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(11)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(2)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(12)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(3)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityAudioHandler).toHaveBeenNthCalledWith(3, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
				expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(3)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
				expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(3, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			}
		})

		test('regressing packet count, overflowing remote packets in simulcast video', async () => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 50, timestamp: 10000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 50, timestamp: 10000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 30, timestamp: 10000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 10, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 30, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 4294967245, timestamp: 10000, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 100, timestamp: 11000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 100, timestamp: 11000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 60, timestamp: 11000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 20, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 60, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 4294967255, timestamp: 11000, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 150, timestamp: 11950 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 150, timestamp: 11950 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 90, timestamp: 11950 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 30, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 90, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 4294967265, timestamp: 11950, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 200, timestamp: 13020 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 200, timestamp: 13020 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 120, timestamp: 13020 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 40, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 120, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 4294967275, timestamp: 13020, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 250, timestamp: 14010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 250, timestamp: 14010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 150, timestamp: 14010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 50, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 150, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 4294967285, timestamp: 14010, packetsLost: 50, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 300, timestamp: 14985 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 300, timestamp: 14985 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 180, timestamp: 14985 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 60, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 180, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 4294967295, timestamp: 14985, packetsLost: 50, roundTripTime: 0.1 },
				]))
				// If the packet count changes to a lower value the stats are
				// reset.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 350, timestamp: 16010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 350, timestamp: 16010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 210, timestamp: 16010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 70, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 350, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 210, timestamp: 16010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 10, timestamp: 16010, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 400, timestamp: 17000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 400, timestamp: 17000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 240, timestamp: 17000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 80, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 400, timestamp: 17000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 400, timestamp: 17000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 240, timestamp: 17000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 20, timestamp: 17000, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 450, timestamp: 17990 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 450, timestamp: 17990 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 270, timestamp: 17990 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 90, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 450, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 450, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 270, timestamp: 17990, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 30, timestamp: 17990, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 500, timestamp: 19005 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 500, timestamp: 19005 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 300, timestamp: 19005 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 100, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 500, timestamp: 19005, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 500, timestamp: 19005, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 300, timestamp: 19005, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 40, timestamp: 19005, packetsLost: 50, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 550, timestamp: 20000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 550, timestamp: 20000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 330, timestamp: 20000 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 110, timestamp: 20000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 550, timestamp: 20000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 550, timestamp: 20000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 330, timestamp: 20000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 50, timestamp: 20000, packetsLost: 50, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 600, timestamp: 21010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 600, timestamp: 21010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 360, timestamp: 21010 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 120, timestamp: 21010 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 600, timestamp: 21010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 600, timestamp: 21010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 360, timestamp: 21010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 60, timestamp: 21010, packetsLost: 50, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(2)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)

			jest.advanceTimersByTime(4000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(11)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(2)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(12)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(3)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(1, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(2, peerConnectionAnalyzer, CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityVideoHandler).toHaveBeenNthCalledWith(3, peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
		})

		test.each([
			['good quality degrading to very bad', 'audio'],
			['good quality degrading to very bad', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 340, timestamp: 16010, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 380, timestamp: 17000, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 450, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 410, timestamp: 17990, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 500, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 435, timestamp: 19005, packetsLost: 65, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(8)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(9)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(10)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.32')
		})

		test.each([
			['good quality degrading to very bad, missing remote packet count', 'audio'],
			['good quality degrading to very bad, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 17000, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 450, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 17990, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 500, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 19005, packetsLost: 65, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(8)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(9)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(10)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.32')
		})

		test.each([
			['very bad quality improving to good', 'audio'],
			['very bad quality improving to good', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 45, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 90, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 130, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 160, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 190, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 225, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 260, timestamp: 16010, packetsLost: 90, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 305, timestamp: 17000, packetsLost: 95, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 450, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 355, timestamp: 17990, packetsLost: 95, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 500, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind, packetsReceived: 405, timestamp: 19005, packetsLost: 95, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.31')

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)
			expect(logStatsMock).toHaveBeenNthCalledWith(2, kind, 'High packet lost ratio: 0.325')

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(8)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(9)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(10)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)
		})

		test.each([
			['very bad quality improving to good, missing remote packet count', 'audio'],
			['very bad quality improving to good, missing remote packet count', 'video'],
		])('%s, %s', async (name, kind) => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 16010, packetsLost: 90, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 400, timestamp: 17000 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 17000, packetsLost: 95, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 450, timestamp: 17990 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 17990, packetsLost: 95, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind, packetsSent: 500, timestamp: 19005 },
					{ type: 'remote-inbound-rtp', kind, timestamp: 19005, packetsLost: 95, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith(kind, 'High packet lost ratio: 0.31')

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(7)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			}

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(8)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.BAD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.BAD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)
			expect(logStatsMock).toHaveBeenNthCalledWith(2, kind, 'High packet lost ratio: 0.325')

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(9)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.MEDIUM)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.MEDIUM)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(10)

			if (kind === 'audio') {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			} else {
				expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
				expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			}
			expect(logStatsMock).toHaveBeenCalledTimes(2)
		})

		test('good audio quality, very bad video quality', async () => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 45, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 90, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 130, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 160, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 190, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 225, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.GOOD)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.VERY_BAD)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith('video', 'High packet lost ratio: 0.31')
		})

		test('very bad audio quality, good video quality', async () => {
			peerConnection.getStats
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 45, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 50, timestamp: 10000 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 50, timestamp: 10000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 90, timestamp: 11000, packetsLost: 10, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 100, timestamp: 11000 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 100, timestamp: 11000, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 130, timestamp: 11950, packetsLost: 20, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 150, timestamp: 11950 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 150, timestamp: 11950, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 160, timestamp: 13020, packetsLost: 40, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 200, timestamp: 13020 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 200, timestamp: 13020, packetsLost: 0, roundTripTime: 0.1 },
				]))
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 190, timestamp: 14010, packetsLost: 60, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 250, timestamp: 14010 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 250, timestamp: 14010, packetsLost: 0, roundTripTime: 0.1 },
				]))
				// A sixth report is needed for the initial calculation due to
				// the first stats report being used as the base to calculate
				// relative values of cumulative stats.
				.mockResolvedValueOnce(newRTCStatsReport([
					{ type: 'outbound-rtp', kind: 'audio', packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind: 'audio', packetsReceived: 225, timestamp: 14985, packetsLost: 75, roundTripTime: 0.1 },
					{ type: 'outbound-rtp', kind: 'video', packetsSent: 300, timestamp: 14985 },
					{ type: 'remote-inbound-rtp', kind: 'video', packetsReceived: 300, timestamp: 14985, packetsLost: 0, roundTripTime: 0.1 },
				]))

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			jest.advanceTimersByTime(5000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(5)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.UNKNOWN)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(0)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(0)
			expect(logStatsMock).toHaveBeenCalledTimes(0)

			jest.advanceTimersByTime(1000)
			// Force the promises returning the stats to be executed.
			await null

			expect(peerConnection.getStats).toHaveBeenCalledTimes(6)

			expect(peerConnectionAnalyzer.getConnectionQualityAudio()).toBe(CONNECTION_QUALITY.VERY_BAD)
			expect(peerConnectionAnalyzer.getConnectionQualityVideo()).toBe(CONNECTION_QUALITY.GOOD)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityAudioHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.VERY_BAD)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledTimes(1)
			expect(changeConnectionQualityVideoHandler).toHaveBeenCalledWith(peerConnectionAnalyzer, CONNECTION_QUALITY.GOOD)
			expectLogStatsToHaveBeenCalled = true
			expect(logStatsMock).toHaveBeenCalledTimes(1)
			expect(logStatsMock).toHaveBeenCalledWith('audio', 'High packet lost ratio: 0.31')
		})
	})

	describe('add stats', () => {
		test.each([
			['initial stats', 'audio'],
			['initial stats', 'video'],
		])('%s, %s', (name, kind) => {
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)

			expect(peerConnectionAnalyzer._packets[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._packetsLost[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._packetsLostRatio[kind]._relativeValues).toEqual([1.5])
			expect(peerConnectionAnalyzer._timestamps[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._timestampsForLogs[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._packetsPerSecond[kind]._relativeValues).toEqual([NaN])
			expect(peerConnectionAnalyzer._roundTripTime[kind]._relativeValues).toEqual([0.2])
		})

		test.each([
			['packet count not repeated', 'audio'],
			['packet count not repeated', 'video'],
		])('%s, %s', (name, kind) => {
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 200, 50, 11250, 0.3)

			expect(peerConnectionAnalyzer._packets[kind]._relativeValues).toEqual([0, 50])
			expect(peerConnectionAnalyzer._packetsLost[kind]._relativeValues).toEqual([0, 10])
			expect(peerConnectionAnalyzer._packetsLostRatio[kind]._relativeValues).toEqual([1.5, 0.2])
			expect(peerConnectionAnalyzer._timestamps[kind]._relativeValues).toEqual([0, 1250])
			expect(peerConnectionAnalyzer._timestampsForLogs[kind]._relativeValues).toEqual([0, 1250])
			expect(peerConnectionAnalyzer._packetsPerSecond[kind]._relativeValues).toEqual([NaN, 40])
			expect(peerConnectionAnalyzer._roundTripTime[kind]._relativeValues).toEqual([0.2, 0.3])
		})

		test.each([
			['packet count repeated one time', 'audio'],
			['packet count repeated one time', 'video'],
		])('%s, %s', (name, kind) => {
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)

			expect(peerConnectionAnalyzer._packets[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._packetsLost[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._packetsLostRatio[kind]._relativeValues).toEqual([1.5])
			expect(peerConnectionAnalyzer._timestamps[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._timestampsForLogs[kind]._relativeValues).toEqual([0])
			expect(peerConnectionAnalyzer._packetsPerSecond[kind]._relativeValues).toEqual([NaN])
			expect(peerConnectionAnalyzer._roundTripTime[kind]._relativeValues).toEqual([0.2])

			expect(peerConnectionAnalyzer._stagedPackets[kind]).toEqual([150])
			expect(peerConnectionAnalyzer._stagedPacketsLost[kind]).toEqual([40])
			expect(peerConnectionAnalyzer._stagedTimestamps[kind]).toEqual([10000])
			expect(peerConnectionAnalyzer._stagedRoundTripTime[kind]).toEqual([0.2])
		})

		test.each([
			['packet count repeated one time then changed', 'audio'],
			['packet count repeated one time then changed', 'video'],
		])('%s, %s', (name, kind) => {
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 250, 60, 12500, 0.3)

			expect(peerConnectionAnalyzer._packets[kind]._relativeValues).toEqual([0, 50, 50])
			expect(peerConnectionAnalyzer._packetsLost[kind]._relativeValues).toEqual([0, 10, 10])
			expect(peerConnectionAnalyzer._packetsLostRatio[kind]._relativeValues).toEqual([1.5, 0.2, 0.2])
			expect(peerConnectionAnalyzer._timestamps[kind]._relativeValues).toEqual([1250, 1250])
			expect(peerConnectionAnalyzer._timestampsForLogs[kind]._relativeValues).toEqual([0, 1250, 1250])
			expect(peerConnectionAnalyzer._packetsPerSecond[kind]._relativeValues).toEqual([NaN, 40, 40])
			expect(peerConnectionAnalyzer._roundTripTime[kind]._relativeValues).toEqual([0.2, 0.2, 0.3])

			expect(peerConnectionAnalyzer._stagedPackets[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedPacketsLost[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedTimestamps[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedRoundTripTime[kind]).toEqual([])
		})

		test.each([
			['packet count repeated two times', 'audio'],
			['packet count repeated two times', 'video'],
		])('%s, %s', (name, kind) => {
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)

			expect(peerConnectionAnalyzer._packets[kind]._relativeValues).toEqual([0, 0, 0])
			expect(peerConnectionAnalyzer._packetsLost[kind]._relativeValues).toEqual([0, 0, 0])
			expect(peerConnectionAnalyzer._packetsLostRatio[kind]._relativeValues).toEqual([1.5, 1.5, 1.5])
			expect(peerConnectionAnalyzer._timestamps[kind]._relativeValues).toEqual([0, 0])
			expect(peerConnectionAnalyzer._timestampsForLogs[kind]._relativeValues).toEqual([0, 0, 0])
			expect(peerConnectionAnalyzer._packetsPerSecond[kind]._relativeValues).toEqual([NaN, NaN, NaN])
			expect(peerConnectionAnalyzer._roundTripTime[kind]._relativeValues).toEqual([0.2, 0.2, 0.2])

			expect(peerConnectionAnalyzer._stagedPackets[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedPacketsLost[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedTimestamps[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedRoundTripTime[kind]).toEqual([])
		})

		test.each([
			['packet count repeated two times then changed', 'audio'],
			['packet count repeated two times then changed', 'video'],
		])('%s, %s', (name, kind) => {
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 300, 70, 13750, 0.3)

			expect(peerConnectionAnalyzer._packets[kind]._relativeValues).toEqual([0, 0, 0, 150])
			expect(peerConnectionAnalyzer._packetsLost[kind]._relativeValues).toEqual([0, 0, 0, 30])
			expect(peerConnectionAnalyzer._packetsLostRatio[kind]._relativeValues).toEqual([1.5, 1.5, 1.5, 0.2])
			expect(peerConnectionAnalyzer._timestamps[kind]._relativeValues).toEqual([0, 3750])
			expect(peerConnectionAnalyzer._timestampsForLogs[kind]._relativeValues).toEqual([0, 0, 0, 3750])
			expect(peerConnectionAnalyzer._packetsPerSecond[kind]._relativeValues).toEqual([NaN, NaN, NaN, 40])
			expect(peerConnectionAnalyzer._roundTripTime[kind]._relativeValues).toEqual([0.2, 0.2, 0.2, 0.3])

			expect(peerConnectionAnalyzer._stagedPackets[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedPacketsLost[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedTimestamps[kind]).toEqual([])
			expect(peerConnectionAnalyzer._stagedRoundTripTime[kind]).toEqual([])
		})

		describe('distribute staged stats', () => {

			const expectRelativeStagedStats = (kind, index, expectedPackets, expectedPacketsLost, expectedTimestamps, expectedRoundTripTime) => {
				expect(peerConnectionAnalyzer._stagedPackets[kind][index]).toBe(expectedPackets)
				expect(peerConnectionAnalyzer._stagedPacketsLost[kind][index]).toBe(expectedPacketsLost)
				expect(peerConnectionAnalyzer._stagedTimestamps[kind][index]).toBe(expectedTimestamps)
				expect(peerConnectionAnalyzer._stagedRoundTripTime[kind][index]).toBe(expectedRoundTripTime)
			}

			test.each([
				['two sets of different values with repeated timestamps', 'audio'],
				['two sets of different values with repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 250, 60, 12500, 0.3)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 200, 50, 11250, 0.2)
				expectRelativeStagedStats(kind, 1, 250, 60, 12500, 0.3)
			})

			test.each([
				['two sets of different values without repeated timestamps', 'audio'],
				['two sets of different values without repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 11000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 250, 60, 14000, 0.3)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 175, 45, 11000, 0.2)
				expectRelativeStagedStats(kind, 1, 250, 60, 14000, 0.3)
			})

			test.each([
				['two sets of repeated values with repeated timestamps', 'audio'],
				['two sets of repeated values with repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 12500, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 150, 40, 11250, 0.2)
				expectRelativeStagedStats(kind, 1, 150, 40, 12500, 0.2)
			})

			test.each([
				['two sets of repeated values without repeated timestamps', 'audio'],
				['two sets of repeated values without repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 11000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 14000, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 150, 40, 11000, 0.2)
				expectRelativeStagedStats(kind, 1, 150, 40, 14000, 0.2)
			})

			test.each([
				['two sets of fully repeated values', 'audio'],
				['two sets of fully repeated values', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 150, 40, 10000, 0.2)
				expectRelativeStagedStats(kind, 1, 150, 40, 10000, 0.2)
			})

			test.each([
				['several sets of different values with repeated timestamps', 'audio'],
				['several sets of different values with repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.3)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.4)
				peerConnectionAnalyzer._stageStats(kind, 350, 80, 14000, 0.1)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 200, 50, 11000, 0.2)
				expectRelativeStagedStats(kind, 1, 250, 60, 12000, 0.3)
				expectRelativeStagedStats(kind, 2, 300, 70, 13000, 0.4)
				expectRelativeStagedStats(kind, 3, 350, 80, 14000, 0.1)
			})

			test.each([
				['several sets of different values without repeated timestamps', 'audio'],
				['several sets of different values without repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 11000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 15000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 18000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 350, 80, 20000, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 170, 44, 11000, 0.2)
				expectRelativeStagedStats(kind, 1, 250, 60, 15000, 0.2)
				expectRelativeStagedStats(kind, 2, 310, 72, 18000, 0.2)
				expectRelativeStagedStats(kind, 3, 350, 80, 20000, 0.2)
			})

			test.each([
				['several sets of repeated values with repeated timestamps', 'audio'],
				['several sets of repeated values with repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 14000, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 150, 40, 11000, 0.2)
				expectRelativeStagedStats(kind, 1, 150, 40, 12000, 0.2)
				expectRelativeStagedStats(kind, 2, 150, 40, 13000, 0.2)
				expectRelativeStagedStats(kind, 3, 150, 40, 14000, 0.2)
			})

			test.each([
				['several sets of repeated values without repeated timestamps', 'audio'],
				['several sets of repeated values without repeated timestamps', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 11000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 15000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 17500, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 20000, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 150, 40, 11000, 0.2)
				expectRelativeStagedStats(kind, 1, 150, 40, 15000, 0.2)
				expectRelativeStagedStats(kind, 2, 150, 40, 17500, 0.2)
				expectRelativeStagedStats(kind, 3, 150, 40, 20000, 0.2)
			})

			test.each([
				['several sets of fully repeated values', 'audio'],
				['several sets of fully repeated values', 'video'],
			])('%s, %s', (name, kind) => {
				peerConnectionAnalyzer._commitStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)
				peerConnectionAnalyzer._stageStats(kind, 150, 40, 10000, 0.2)

				peerConnectionAnalyzer._distributeStagedStats(kind)

				expectRelativeStagedStats(kind, 0, 150, 40, 10000, 0.2)
				expectRelativeStagedStats(kind, 1, 150, 40, 10000, 0.2)
				expectRelativeStagedStats(kind, 2, 150, 40, 10000, 0.2)
				expectRelativeStagedStats(kind, 3, 150, 40, 10000, 0.2)
			})
		})
	})

	describe('log stats', () => {

		let consoleDebugMock

		beforeEach(() => {
			consoleDebugMock = jest.spyOn(console, 'debug')
		})

		test.each([
			['video peer', 'audio'],
			['video peer', 'video'],
		])('%s, %s', (name, kind) => {
			const logRtcStatsMock = jest.spyOn(peerConnectionAnalyzer, '_logRtcStats').mockImplementation(() => {})

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 200, 50, 11250, 0.3)
			peerConnectionAnalyzer._addStats(kind, 260, 56, 12250, 0.4)

			peerConnectionAnalyzer._logStats(kind, 'Message to log')

			const tag = 'PeerConnectionAnalyzer: ' + kind

			expect(consoleDebugMock).toHaveBeenCalledTimes(7)
			expect(consoleDebugMock).toHaveBeenNthCalledWith(1, '%s: %s', tag, 'Message to log')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(2, '%s: Packets: %s', tag, '[0, 50, 60]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(3, '%s: Packets lost: %s', tag, '[0, 10, 6]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(4, '%s: Packets lost ratio: %s', tag, '[1.5, 0.2, 0.1]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(5, '%s: Packets per second: %s', tag, '[NaN, 40, 60]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(6, '%s: Round trip time: %s', tag, '[0.2, 0.3, 0.4]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(7, '%s: Timestamps: %s', tag, '[0, 1250, 1000]')
			expect(logRtcStatsMock).toHaveBeenCalledTimes(1)
			expect(logRtcStatsMock).toHaveBeenCalledWith(tag, kind)
		})

		test.each([
			['screen peer', 'audio'],
			['screen peer', 'video'],
		])('%s, %s', (name, kind) => {
			const logRtcStatsMock = jest.spyOn(peerConnectionAnalyzer, '_logRtcStats').mockImplementation(() => {})

			peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER, PEER_TYPE.SCREEN)

			peerConnectionAnalyzer._addStats(kind, 150, 40, 10000, 0.2)
			peerConnectionAnalyzer._addStats(kind, 200, 50, 11250, 0.3)
			peerConnectionAnalyzer._addStats(kind, 260, 56, 12250, 0.4)

			peerConnectionAnalyzer._logStats(kind, 'Message to log')

			const tag = 'PeerConnectionAnalyzer: ' + kind + ' (screen)'

			expect(consoleDebugMock).toHaveBeenCalledTimes(7)
			expect(consoleDebugMock).toHaveBeenNthCalledWith(1, '%s: %s', tag, 'Message to log')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(2, '%s: Packets: %s', tag, '[0, 50, 60]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(3, '%s: Packets lost: %s', tag, '[0, 10, 6]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(4, '%s: Packets lost ratio: %s', tag, '[1.5, 0.2, 0.1]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(5, '%s: Packets per second: %s', tag, '[NaN, 40, 60]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(6, '%s: Round trip time: %s', tag, '[0.2, 0.3, 0.4]')
			expect(consoleDebugMock).toHaveBeenNthCalledWith(7, '%s: Timestamps: %s', tag, '[0, 1250, 1000]')
			expect(logRtcStatsMock).toHaveBeenCalledTimes(1)
			expect(logRtcStatsMock).toHaveBeenCalledWith(tag, kind)
		})

		describe('log RTC stats', () => {

			beforeEach(() => {
				peerConnection._setIceConnectionState('connected')
				peerConnection._setConnectionState('connected')
			})

			test.each([
				['sender', 'audio'],
				['sender', 'video'],
			])('%s, %s', async (name, kind) => {
				// Different reports contain different types and values in each
				// type (and some of them not really applicable for a sender),
				// even if in a real world scenario they would be consistent
				// between reports.
				peerConnection.getStats
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 45, timestamp: 10000, packetsLost: 5, roundTripTime: 0.1 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 90, timestamp: 11000, packetsLost: 10, roundTripTime: 0.2 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, id: '67890', packetsSent: 150, timestamp: 11950, rid: 'h' },
						{ type: 'outbound-rtp', kind, id: 'abcde', packetsSent: 80, timestamp: 11950, rid: 'm' },
						{ type: 'remote-inbound-rtp', kind, localId: '67890', packetsReceived: 135, timestamp: 11950, packetsLost: 15, roundTripTime: 0.1 },
						{ type: 'remote-inbound-rtp', kind, localId: 'abcde', packetsReceived: 72, timestamp: 11950, packetsLost: 8, roundTripTime: 0.1 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 180, timestamp: 13020, packetsLost: 20, roundTripTime: 0.15, jitter: 0.007 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'local-candidate', candidateType: 'host', protocol: 'udp' },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 270, timestamp: 14985, packetsLost: 30, roundTripTime: 0.3 },
						{ type: 'inbound-rtp', kind, packetsReceived: 26, timestamp: 14985, packetsLost: 2 },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 28, timestamp: 14985 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'candidate-pair', byteReceived: 2120, bytesSent: 63820, timestamp: 16010 },
						{ type: 'outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 315, timestamp: 16010, packetsLost: 35, roundTripTime: 0.25 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, bytesSent: 64042, packetsSent: 400, timestamp: 17000 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 360, timestamp: 17000, packetsLost: 40, roundTripTime: 0.15 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'outbound-rtp', kind, packetsSent: 450, timestamp: 17990, codecId: '123456' },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 405, timestamp: 17990, packetsLost: 45, roundTripTime: 0.2 },
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.SENDER)

				jest.advanceTimersByTime(9000)
				// Force the promises returning the stats to be executed.
				await null

				const tag = 'PeerConnectionAnalyzer: ' + kind

				peerConnectionAnalyzer._logRtcStats(tag, kind)

				expect(consoleDebugMock).toHaveBeenCalledTimes(15)
				expect(consoleDebugMock).toHaveBeenNthCalledWith(1, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","id":"67890","packetsSent":150,"timestamp":11950,"rid":"h"}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(2, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","id":"abcde","packetsSent":80,"timestamp":11950,"rid":"m"}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(3, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","localId":"67890","packetsReceived":135,"timestamp":11950,"packetsLost":15,"roundTripTime":0.1}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(4, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","localId":"abcde","packetsReceived":72,"timestamp":11950,"packetsLost":8,"roundTripTime":0.1}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(5, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","packetsSent":200,"timestamp":13020}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(6, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","packetsReceived":180,"timestamp":13020,"packetsLost":20,"roundTripTime":0.15,"jitter":0.007}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(7, '%s: no matching type', tag)
				expect(consoleDebugMock).toHaveBeenNthCalledWith(8, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","packetsSent":300,"timestamp":14985}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(9, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","packetsReceived":270,"timestamp":14985,"packetsLost":30,"roundTripTime":0.3}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(10, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","packetsSent":350,"timestamp":16010}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(11, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","packetsReceived":315,"timestamp":16010,"packetsLost":35,"roundTripTime":0.25}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(12, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","bytesSent":64042,"packetsSent":400,"timestamp":17000}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(13, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","packetsReceived":360,"timestamp":17000,"packetsLost":40,"roundTripTime":0.15}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(14, '%s: %s', tag, '{"type":"outbound-rtp","kind":"' + kind + '","packetsSent":450,"timestamp":17990,"codecId":"123456"}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(15, '%s: %s', tag, '{"type":"remote-inbound-rtp","kind":"' + kind + '","packetsReceived":405,"timestamp":17990,"packetsLost":45,"roundTripTime":0.2}')
			})

			test.each([
				['receiver', 'audio'],
				['receiver', 'video'],
			])('%s, %s', async (name, kind) => {
				// Different reports contain different types and values in each
				// type (and some of them not really applicable for a receiver),
				// even if in a real world scenario they would be consistent
				// between reports.
				peerConnection.getStats
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, packetsReceived: 45, timestamp: 10000, packetsLost: 5 },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 50, timestamp: 10000 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, packetsReceived: 90, timestamp: 11000, packetsLost: 10 },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 100, timestamp: 11000 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, id: '67890', packetsReceived: 135, timestamp: 11950, packetsLost: 15 },
						{ type: 'remote-outbound-rtp', kind, localId: '67890', packetsSent: 150, timestamp: 11950 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, packetsReceived: 180, timestamp: 13020, packetsLost: 20, jitter: 0.007 },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 200, timestamp: 13020 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'local-candidate', candidateType: 'host', protocol: 'udp' },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, packetsReceived: 270, timestamp: 14985, packetsLost: 30 },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 300, timestamp: 14985 },
						{ type: 'outbound-rtp', kind, packetsSent: 28, timestamp: 14985 },
						{ type: 'remote-inbound-rtp', kind, packetsReceived: 26, timestamp: 14985, packetsLost: 2, roundTripTime: 0.3 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'candidate-pair', byteReceived: 2120, bytesSent: 63820, timestamp: 16010 },
						{ type: 'inbound-rtp', kind, packetsReceived: 315, timestamp: 16010, packetsLost: 35 },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 350, timestamp: 16010 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, bytesReceived: 64042, packetsReceived: 400, timestamp: 17000 },
					]))
					.mockResolvedValueOnce(newRTCStatsReport([
						{ type: 'inbound-rtp', kind, packetsReceived: 405, timestamp: 17990, packetsLost: 45, codecId: '123456' },
						{ type: 'remote-outbound-rtp', kind, packetsSent: 450, timestamp: 17990 },
					]))

				peerConnectionAnalyzer.setPeerConnection(peerConnection, PEER_DIRECTION.RECEIVER)

				jest.advanceTimersByTime(9000)
				// Force the promises returning the stats to be executed.
				await null

				const tag = 'PeerConnectionAnalyzer: ' + kind + ': '

				peerConnectionAnalyzer._logRtcStats(tag, kind)

				expect(consoleDebugMock).toHaveBeenCalledTimes(12)
				expect(consoleDebugMock).toHaveBeenNthCalledWith(1, '%s: %s', tag, '{"type":"inbound-rtp","kind":"' + kind + '","id":"67890","packetsReceived":135,"timestamp":11950,"packetsLost":15}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(2, '%s: %s', tag, '{"type":"remote-outbound-rtp","kind":"' + kind + '","localId":"67890","packetsSent":150,"timestamp":11950}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(3, '%s: %s', tag, '{"type":"inbound-rtp","kind":"' + kind + '","packetsReceived":180,"timestamp":13020,"packetsLost":20,"jitter":0.007}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(4, '%s: %s', tag, '{"type":"remote-outbound-rtp","kind":"' + kind + '","packetsSent":200,"timestamp":13020}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(5, '%s: no matching type', tag)
				expect(consoleDebugMock).toHaveBeenNthCalledWith(6, '%s: %s', tag, '{"type":"inbound-rtp","kind":"' + kind + '","packetsReceived":270,"timestamp":14985,"packetsLost":30}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(7, '%s: %s', tag, '{"type":"remote-outbound-rtp","kind":"' + kind + '","packetsSent":300,"timestamp":14985}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(8, '%s: %s', tag, '{"type":"inbound-rtp","kind":"' + kind + '","packetsReceived":315,"timestamp":16010,"packetsLost":35}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(9, '%s: %s', tag, '{"type":"remote-outbound-rtp","kind":"' + kind + '","packetsSent":350,"timestamp":16010}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(10, '%s: %s', tag, '{"type":"inbound-rtp","kind":"' + kind + '","bytesReceived":64042,"packetsReceived":400,"timestamp":17000}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(11, '%s: %s', tag, '{"type":"inbound-rtp","kind":"' + kind + '","packetsReceived":405,"timestamp":17990,"packetsLost":45,"codecId":"123456"}')
				expect(consoleDebugMock).toHaveBeenNthCalledWith(12, '%s: %s', tag, '{"type":"remote-outbound-rtp","kind":"' + kind + '","packetsSent":450,"timestamp":17990}')
			})
		})
	})
})
