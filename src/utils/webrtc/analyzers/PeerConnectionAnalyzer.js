/**
 *
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import {
	STAT_VALUE_TYPE,
	AverageStatValue,
} from './AverageStatValue'

const CONNECTION_QUALITY = {
	UNKNOWN: 0,
	GOOD: 1,
	MEDIUM: 2,
	BAD: 3,
	VERY_BAD: 4,
	NO_TRANSMITTED_DATA: 5,
}

const PEER_DIRECTION = {
	SENDER: 0,
	RECEIVER: 1,
}

/**
 * Analyzer for the quality of the connection of an RTCPeerConnection.
 *
 * After creation "setPeerConnection(RTCPeerConnection)" must be called to set
 * the RTCPeerConnection to analyze. The analysis will start and stop
 * automatically based on the connection state, except when closed. Suprisingly,
 * "iceConnectionStateChange" is not called when the ICE connection state
 * changes to closed, so the change can not be detected from the
 * PeerConnectionAnalyzer. This change can be detected from the signaling,
 * though, and thus must be handled by the user of this class by calling
 * "setPeerConnection(null)" to stop the analysis. Similarly,
 * "setPeerConnection(null)" must be called too if the RTCPeerConnection is
 * active but the analyzer is no longer needed.
 *
 * The reported connection quality is mainly based on the packets lost ratio,
 * but also in other stats, like the amount of transmitted packets. UNKNOWN is
 * used when the analysis is started or stopped (including when it is done
 * automatically due to changes in the ICE connection status). In general even
 * if the quality of the connection is bad WebRTC is able to keep audio and
 * video at acceptable quality levels; only when the reported connection quality
 * is very bad or no data is transmitted at all the audio and video quality may
 * not be enough.
 */
function PeerConnectionAnalyzer() {
	this._packets = {
		'audio': new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
		'video': new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
	}
	this._packetsLost = {
		'audio': new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
		'video': new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
	}
	this._packetsLostRatio = {
		'audio': new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
		'video': new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
	}
	this._packetsPerSecond = {
		'audio': new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
		'video': new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
	}
	// Latest values have a higher weight than the default one to better detect
	// sudden changes in the round trip time, which can lead to discarded (but
	// not lost) packets.
	this._roundTripTime = {
		'audio': new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE, 5),
		'video': new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE, 5),
	}
	// Only the last relative value is used, but as it is a cumulative value the
	// previous one is needed as a base to calculate the last one.
	this._timestamps = {
		'audio': new AverageStatValue(2, STAT_VALUE_TYPE.CUMULATIVE),
		'video': new AverageStatValue(2, STAT_VALUE_TYPE.CUMULATIVE),
	}

	this._handlers = []

	this._peerConnection = null
	this._peerDirection = null

	this._getStatsInterval = null

	this._handleIceConnectionStateChangedBound = this._handleIceConnectionStateChanged.bind(this)
	this._processStatsBound = this._processStats.bind(this)

	this._connectionQualityAudio = CONNECTION_QUALITY.UNKNOWN
	this._connectionQualityVideo = CONNECTION_QUALITY.UNKNOWN
}
PeerConnectionAnalyzer.prototype = {

	on: function(event, handler) {
		if (!this._handlers.hasOwnProperty(event)) {
			this._handlers[event] = [handler]
		} else {
			this._handlers[event].push(handler)
		}
	},

	off: function(event, handler) {
		const handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		const index = handlers.indexOf(handler)
		if (index !== -1) {
			handlers.splice(index, 1)
		}
	},

	_trigger: function(event, args) {
		let handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		args.unshift(this)

		handlers = handlers.slice(0)
		for (let i = 0; i < handlers.length; i++) {
			const handler = handlers[i]
			handler.apply(handler, args)
		}
	},

	getConnectionQualityAudio: function() {
		return this._connectionQualityAudio
	},

	getConnectionQualityVideo: function() {
		return this._connectionQualityVideo
	},

	_setConnectionQualityAudio: function(connectionQualityAudio) {
		if (this._connectionQualityAudio === connectionQualityAudio) {
			return
		}

		this._connectionQualityAudio = connectionQualityAudio
		this._trigger('change:connectionQualityAudio', [connectionQualityAudio])
	},

	_setConnectionQualityVideo: function(connectionQualityVideo) {
		if (this._connectionQualityVideo === connectionQualityVideo) {
			return
		}

		this._connectionQualityVideo = connectionQualityVideo
		this._trigger('change:connectionQualityVideo', [connectionQualityVideo])
	},

	setPeerConnection: function(peerConnection, peerDirection = null) {
		if (this._peerConnection) {
			this._peerConnection.removeEventListener('iceconnectionstatechange', this._handleIceConnectionStateChangedBound)
			this._stopGetStatsInterval()
		}

		this._peerConnection = peerConnection
		this._peerDirection = peerDirection

		if (this._peerConnection) {
			this._peerConnection.addEventListener('iceconnectionstatechange', this._handleIceConnectionStateChangedBound)
			this._handleIceConnectionStateChangedBound()
		}
	},

	_handleIceConnectionStateChanged: function() {
		// Note that even if the ICE connection state is "disconnected" the
		// connection is actually active, media is still transmitted, and the
		// stats are properly updated.
		if (!this._peerConnection || (this._peerConnection.iceConnectionState !== 'connected' && this._peerConnection.iceConnectionState !== 'completed' && this._peerConnection.iceConnectionState !== 'disconnected')) {
			this._setConnectionQualityAudio(CONNECTION_QUALITY.UNKNOWN)
			this._setConnectionQualityVideo(CONNECTION_QUALITY.UNKNOWN)

			this._stopGetStatsInterval()

			return
		}

		if (this._getStatsInterval) {
			// Already active, nothing to do.
			return
		}

		this._getStatsInterval = window.setInterval(() => {
			this._peerConnection.getStats().then(this._processStatsBound)
		}, 1000)
	},

	_stopGetStatsInterval: function() {
		window.clearInterval(this._getStatsInterval)
		this._getStatsInterval = null
	},

	_processStats: function(stats) {
		if (!this._peerConnection || (this._peerConnection.iceConnectionState !== 'connected' && this._peerConnection.iceConnectionState !== 'completed' && this._peerConnection.iceConnectionState !== 'disconnected')) {
			return
		}

		if (this._peerDirection === PEER_DIRECTION.SENDER) {
			this._processSenderStats(stats)
		} else if (this._peerDirection === PEER_DIRECTION.RECEIVER) {
			this._processReceiverStats(stats)
		}

		this._setConnectionQualityAudio(this._calculateConnectionQualityAudio())
		this._setConnectionQualityVideo(this._calculateConnectionQualityVideo())
	},

	_processSenderStats: function(stats) {
		// Packets are calculated as "packetsReceived + packetsLost" or as
		// "packetsSent" depending on the browser (see below).
		const packets = {
			'audio': -1,
			'video': -1,
		}

		// Packets stats for a sender are checked from the point of view of the
		// receiver.
		const packetsReceived = {
			'audio': -1,
			'video': -1,
		}

		const packetsLost = {
			'audio': -1,
			'video': -1,
		}

		// If "packetsReceived" is not available (like in Chromium) use
		// "packetsSent" instead; it may be measured at a different time from
		// the received statistics, so checking "packetsLost" against it may not
		// be fully accurate, but it should be close enough.
		const packetsSent = {
			'audio': -1,
			'video': -1,
		}

		// Timestamp is set to "timestampReceived" or "timestampSent" depending
		// on how "packets" were calculated.
		const timestamp = {
			'audio': -1,
			'video': -1,
		}

		const timestampReceived = {
			'audio': -1,
			'video': -1,
		}

		const timestampSent = {
			'audio': -1,
			'video': -1,
		}

		const roundTripTime = {
			'audio': -1,
			'video': -1,
		}

		for (const stat of stats.values()) {
			if (stat.type === 'outbound-rtp') {
				if ('packetsSent' in stat && 'kind' in stat) {
					packetsSent[stat.kind] = stat.packetsSent

					if ('timestamp' in stat && 'kind' in stat) {
						timestampSent[stat.kind] = stat.timestamp
					}
				}
			} else if (stat.type === 'remote-inbound-rtp') {
				if ('packetsReceived' in stat && 'kind' in stat) {
					packetsReceived[stat.kind] = stat.packetsReceived

					if ('timestamp' in stat && 'kind' in stat) {
						timestampReceived[stat.kind] = stat.timestamp
					}
				}
				if ('packetsLost' in stat && 'kind' in stat) {
					packetsLost[stat.kind] = stat.packetsLost
				}
				if ('roundTripTime' in stat && 'kind' in stat) {
					roundTripTime[stat.kind] = stat.roundTripTime
				}
			}
		}

		for (const kind of ['audio', 'video']) {
			if (packetsReceived[kind] >= 0 && packetsLost[kind] >= 0) {
				packets[kind] = packetsReceived[kind] + packetsLost[kind]
				timestamp[kind] = timestampReceived[kind]
			} else if (packetsSent[kind] >= 0) {
				packets[kind] = packetsSent[kind]
				timestamp[kind] = timestampSent[kind]
			}

			// In some (strange) cases a newer stat may report a lower value
			// than a previous one (it seems to happen if the connection delay
			// is high; probably the browser assumes that a packet was lost but
			// later receives the acknowledgment). If that happens just keep the
			// previous value to prevent distorting the analysis with negative
			// ratios of lost packets.
			if (packetsLost[kind] >= 0 && packetsLost[kind] < this._packetsLost[kind].getLastRawValue()) {
				packetsLost[kind] = this._packetsLost[kind].getLastRawValue()
			}

			if (packets[kind] >= 0) {
				this._packets[kind].add(packets[kind])
			}
			if (packetsLost[kind] >= 0) {
				this._packetsLost[kind].add(packetsLost[kind])
			}
			if (packets[kind] >= 0 && packetsLost[kind] >= 0) {
				// The packet stats are cumulative values, so the isolated
				// values are got from the helper object.
				// If there were no transmitted packets in the last stats the
				// ratio is higher than 1 both to signal that and to force the
				// quality towards a very bad quality faster, but not
				// immediately.
				let packetsLostRatio = 1.5
				if (this._packets[kind].getLastRelativeValue() > 0) {
					packetsLostRatio = this._packetsLost[kind].getLastRelativeValue() / this._packets[kind].getLastRelativeValue()
				}
				this._packetsLostRatio[kind].add(packetsLostRatio)
			}
			if (timestamp[kind] >= 0) {
				this._timestamps[kind].add(timestamp[kind])
			}
			if (packets[kind] >= 0 && timestamp[kind] >= 0) {
				const elapsedSeconds = this._timestamps[kind].getLastRelativeValue() / 1000
				// The packet stats are cumulative values, so the isolated
				// values are got from the helper object.
				const packetsPerSecond = this._packets[kind].getLastRelativeValue() / elapsedSeconds
				this._packetsPerSecond[kind].add(packetsPerSecond)
			}
			if (roundTripTime[kind] >= 0) {
				this._roundTripTime[kind].add(roundTripTime[kind])
			}
		}
	},

	_processReceiverStats: function(stats) {
		// Packets are calculated as "packetsReceived + packetsLost".
		const packets = {
			'audio': -1,
			'video': -1,
		}

		const packetsReceived = {
			'audio': -1,
			'video': -1,
		}

		const packetsLost = {
			'audio': -1,
			'video': -1,
		}

		const timestamp = {
			'audio': -1,
			'video': -1,
		}

		for (const stat of stats.values()) {
			if (stat.type === 'inbound-rtp') {
				if ('packetsReceived' in stat && 'kind' in stat) {
					packetsReceived[stat.kind] = stat.packetsReceived
				}
				if ('packetsLost' in stat && 'kind' in stat) {
					packetsLost[stat.kind] = stat.packetsLost
				}
				if ('timestamp' in stat && 'kind' in stat) {
					timestamp[stat.kind] = stat.timestamp
				}
			}
		}

		for (const kind of ['audio', 'video']) {
			if (packetsReceived[kind] >= 0 && packetsLost[kind] >= 0) {
				packets[kind] = packetsReceived[kind] + packetsLost[kind]
			}

			// In some (strange) cases a newer stat may report a lower value
			// than a previous one (it seems to happen if the connection delay
			// is high; probably the browser assumes that a packet was lost but
			// later receives the acknowledgment). If that happens just keep the
			// previous value to prevent distorting the analysis with negative
			// ratios of lost packets.
			if (packetsLost[kind] >= 0 && packetsLost[kind] < this._packetsLost[kind].getLastRawValue()) {
				packetsLost[kind] = this._packetsLost[kind].getLastRawValue()
			}

			if (packets[kind] >= 0) {
				this._packets[kind].add(packets[kind])
			}
			if (packetsLost[kind] >= 0) {
				this._packetsLost[kind].add(packetsLost[kind])
			}
			if (packets[kind] >= 0 && packetsLost[kind] >= 0) {
				// The packet stats are cumulative values, so the isolated
				// values are got from the helper object.
				// If there were no transmitted packets in the last stats the
				// ratio is higher than 1 both to signal that and to force the
				// quality towards a very bad quality faster, but not
				// immediately.
				let packetsLostRatio = 1.5
				if (this._packets[kind].getLastRelativeValue() > 0) {
					packetsLostRatio = this._packetsLost[kind].getLastRelativeValue() / this._packets[kind].getLastRelativeValue()
				}
				this._packetsLostRatio[kind].add(packetsLostRatio)
			}
			if (timestamp[kind] >= 0) {
				this._timestamps[kind].add(timestamp[kind])
			}
			if (packets[kind] >= 0 && timestamp[kind] >= 0) {
				const elapsedSeconds = this._timestamps[kind].getLastRelativeValue() / 1000
				// The packet stats are cumulative values, so the isolated
				// values are got from the helper object.
				const packetsPerSecond = this._packets[kind].getLastRelativeValue() / elapsedSeconds
				this._packetsPerSecond[kind].add(packetsPerSecond)
			}
		}
	},

	_calculateConnectionQualityAudio: function() {
		return this._calculateConnectionQuality(this._packetsLostRatio['audio'], this._packetsPerSecond['audio'], this._roundTripTime['audio'])
	},

	_calculateConnectionQualityVideo: function() {
		return this._calculateConnectionQuality(this._packetsLostRatio['video'], this._packetsPerSecond['video'], this._roundTripTime['video'])
	},

	_calculateConnectionQuality: function(packetsLostRatio, packetsPerSecond, roundTripTime) {
		if (!packetsLostRatio.hasEnoughData() || !packetsPerSecond.hasEnoughData()) {
			return CONNECTION_QUALITY.UNKNOWN
		}

		const packetsLostRatioWeightedAverage = packetsLostRatio.getWeightedAverage()
		if (packetsLostRatioWeightedAverage >= 1) {
			return CONNECTION_QUALITY.NO_TRANSMITTED_DATA
		}

		// A high round trip time means that the delay is high, but it can also
		// imply that some packets, even if they are not lost, are anyway
		// discarded to try to keep the playing rate in real time.
		// Round trip time is measured in seconds.
		if (roundTripTime.hasEnoughData() && roundTripTime.getWeightedAverage() > 1.5) {
			return CONNECTION_QUALITY.VERY_BAD
		}

		// In some cases there may be packets being transmitted without any lost
		// packet, but if the number of packets is too low the connection is
		// most likely in bad shape anyway.
		// Note that in the case of video the number of transmitted packets
		// depend on the resolution, frame rate and changes between frames, but
		// even for a small (320x420) static video around 20 packets are
		// transmitted on a good connection. If a high quality video is tried to
		// be sent on a bad network the browser will automatically reduce its
		// quality to keep a smooth video, albeit on a lower resolution. Thus
		// with a threshold of 10 packets issues can be detected too for videos,
		// although only once they can not be further downscaled.
		if (packetsPerSecond.getWeightedAverage() < 10) {
			return CONNECTION_QUALITY.VERY_BAD
		}

		if (packetsLostRatioWeightedAverage > 0.3) {
			return CONNECTION_QUALITY.VERY_BAD
		}

		if (packetsLostRatioWeightedAverage > 0.2) {
			return CONNECTION_QUALITY.BAD
		}

		if (packetsLostRatioWeightedAverage > 0.1) {
			return CONNECTION_QUALITY.MEDIUM
		}

		return CONNECTION_QUALITY.GOOD
	},

}

export {
	CONNECTION_QUALITY,
	PEER_DIRECTION,
	PeerConnectionAnalyzer,
}
