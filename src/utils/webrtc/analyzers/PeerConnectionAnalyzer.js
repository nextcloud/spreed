/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { STAT_VALUE_TYPE, AverageStatValue } from './AverageStatValue.js'
import EmitterMixin from '../../EmitterMixin.js'

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

const PEER_TYPE = {
	VIDEO: 0,
	SCREEN: 1,
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
 * Similarly, the analysis should be enabled only when audio or video are
 * enabled. This is also known from the signaling messages and needs to be
 * handled by the user of this class by calling "setAnalysisEnabledAudio(bool)"
 * and "setAnalysisEnabledVideo(bool)".
 *
 * The reason is that when audio or video are disabled the transmitted packets
 * are much lower, so it is not possible to get a reliable analysis from them.
 * Moreover, when the sent video is disabled in Firefox the stats are
 * meaningless, as the packet count is no longer a monotonic increasing value.
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
	this._superEmitterMixin()

	this._packets = {
		audio: new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
		video: new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
	}
	this._packetsLost = {
		audio: new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
		video: new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
	}
	this._packetsLostRatio = {
		audio: new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
		video: new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
	}
	this._packetsPerSecond = {
		audio: new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
		video: new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE),
	}
	// Latest values have a higher weight than the default one to better detect
	// sudden changes in the round trip time, which can lead to discarded (but
	// not lost) packets.
	this._roundTripTime = {
		audio: new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE, 5),
		video: new AverageStatValue(5, STAT_VALUE_TYPE.RELATIVE, 5),
	}
	// Only the last relative value is used, but as it is a cumulative value the
	// previous one is needed as a base to calculate the last one.
	this._timestamps = {
		audio: new AverageStatValue(2, STAT_VALUE_TYPE.CUMULATIVE),
		video: new AverageStatValue(2, STAT_VALUE_TYPE.CUMULATIVE),
	}
	this._timestampsForLogs = {
		audio: new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
		video: new AverageStatValue(5, STAT_VALUE_TYPE.CUMULATIVE),
	}

	this._stagedPackets = {
		audio: [],
		video: [],
	}
	this._stagedPacketsLost = {
		audio: [],
		video: [],
	}
	this._stagedRoundTripTime = {
		audio: [],
		video: [],
	}
	this._stagedTimestamps = {
		audio: [],
		video: [],
	}

	this._analysisEnabled = {
		audio: true,
		video: true,
	}

	this._peerConnection = null
	this._peerDirection = null
	this._peerType = null

	this._getStatsInterval = null

	this._handleIceConnectionStateChangedBound = this._handleIceConnectionStateChanged.bind(this)
	this._handleConnectionStateChangedBound = this._handleConnectionStateChanged.bind(this)
	this._processStatsBound = this._processStats.bind(this)

	this._connectionQuality = {
		audio: CONNECTION_QUALITY.UNKNOWN,
		video: CONNECTION_QUALITY.UNKNOWN,
	}
}
PeerConnectionAnalyzer.prototype = {

	getConnectionQualityAudio() {
		return this._connectionQuality.audio
	},

	getConnectionQualityVideo() {
		return this._connectionQuality.video
	},

	_setConnectionQualityAudio(connectionQualityAudio) {
		if (this._connectionQuality.audio === connectionQualityAudio) {
			return
		}

		this._connectionQuality.audio = connectionQualityAudio
		this._trigger('change:connectionQualityAudio', [connectionQualityAudio])
	},

	_setConnectionQualityVideo(connectionQualityVideo) {
		if (this._connectionQuality.video === connectionQualityVideo) {
			return
		}

		this._connectionQuality.video = connectionQualityVideo
		this._trigger('change:connectionQualityVideo', [connectionQualityVideo])
	},

	setPeerConnection(peerConnection, peerDirection = null, peerType = PEER_TYPE.VIDEO) {
		if (this._peerConnection) {
			this._peerConnection.removeEventListener('iceconnectionstatechange', this._handleIceConnectionStateChangedBound)
			this._peerConnection.removeEventListener('connectionstatechange', this._handleConnectionStateChangedBound)
			this._stopGetStatsInterval()
		}

		this._peerConnection = peerConnection
		this._peerDirection = peerDirection
		this._peerType = peerType

		this._setConnectionQualityAudio(CONNECTION_QUALITY.UNKNOWN)
		this._setConnectionQualityVideo(CONNECTION_QUALITY.UNKNOWN)

		if (this._peerConnection) {
			this._peerConnection.addEventListener('iceconnectionstatechange', this._handleIceConnectionStateChangedBound)
			this._peerConnection.addEventListener('connectionstatechange', this._handleConnectionStateChangedBound)
			this._handleIceConnectionStateChangedBound()
		}
	},

	setAnalysisEnabledAudio(analysisEnabledAudio) {
		if (this._analysisEnabled.audio === analysisEnabledAudio) {
			return
		}

		this._analysisEnabled.audio = analysisEnabledAudio

		if (!analysisEnabledAudio) {
			this._setConnectionQualityAudio(CONNECTION_QUALITY.UNKNOWN)
		} else {
			this._resetStats('audio')
		}
	},

	setAnalysisEnabledVideo(analysisEnabledVideo) {
		if (this._analysisEnabled.video === analysisEnabledVideo) {
			return
		}

		this._analysisEnabled.video = analysisEnabledVideo

		if (!analysisEnabledVideo) {
			this._setConnectionQualityVideo(CONNECTION_QUALITY.UNKNOWN)
		} else {
			this._resetStats('video')
		}
	},

	_resetStats(kind) {
		this._packets[kind].reset()
		this._packetsLost[kind].reset()
		this._packetsLostRatio[kind].reset()
		this._packetsPerSecond[kind].reset()
		this._timestamps[kind].reset()
		this._timestampsForLogs[kind].reset()
	},

	_handleIceConnectionStateChanged() {
		// Note that even if the ICE connection state is "disconnected" the
		// connection is actually active, media is still transmitted, and the
		// stats are properly updated.
		// "connectionState === failed" needs to be checked due to a Chromium
		// bug in which "iceConnectionState" can get stuck as "disconnected"
		// even if the connection has already failed.
		if (!this._peerConnection || (this._peerConnection.iceConnectionState !== 'connected' && this._peerConnection.iceConnectionState !== 'completed' && this._peerConnection.iceConnectionState !== 'disconnected') || this._peerConnection.connectionState === 'failed') {
			this._setConnectionQualityAudio(CONNECTION_QUALITY.UNKNOWN)
			this._setConnectionQualityVideo(CONNECTION_QUALITY.UNKNOWN)

			this._stopGetStatsInterval()

			return
		}

		if (this._getStatsInterval) {
			// Already active, nothing to do.
			return
		}

		// When a connection is started the stats must be reset, as a different
		// peer connection could have been used before and its stats would be
		// unrelated to the new one.
		// When a connection is restarted the reported stats continue from the
		// last values. However, during the reconnection the stats will not be
		// updated, so the timestamps will suddenly increase once the connection
		// is ready again. This could cause a wrong analysis, so the stats
		// should be reset too in that case.
		this._resetStats('audio')
		this._resetStats('video')

		this._getStatsInterval = window.setInterval(() => {
			this._peerConnection.getStats().then(this._processStatsBound)
		}, 1000)
	},

	_handleConnectionStateChanged() {
		if (!this._peerConnection) {
			return
		}

		if (this._peerConnection.connectionState !== 'failed') {
			return
		}

		if (this._peerConnection.iceConnectionState === 'failed') {
			return
		}

		// Work around Chromium bug where "iceConnectionState" never changes
		// to "failed" (it stays as "disconnected"). When that happens
		// "connectionState" actually does change to "failed", so the normal
		// handling of "iceConnectionState === failed" is triggered here.

		this._handleIceConnectionStateChanged()
	},

	_stopGetStatsInterval() {
		window.clearInterval(this._getStatsInterval)
		this._getStatsInterval = null
	},

	_processStats(stats) {
		// "connectionState === failed" needs to be checked due to a Chromium
		// bug in which "iceConnectionState" can get stuck as "disconnected"
		// even if the connection has already failed.
		if (!this._peerConnection || (this._peerConnection.iceConnectionState !== 'connected' && this._peerConnection.iceConnectionState !== 'completed' && this._peerConnection.iceConnectionState !== 'disconnected') || this._peerConnection.connectionState === 'failed') {
			return
		}

		if (this._peerDirection === PEER_DIRECTION.SENDER) {
			this._processSenderStats(stats)
		} else if (this._peerDirection === PEER_DIRECTION.RECEIVER) {
			this._processReceiverStats(stats)
		}

		if (this._analysisEnabled.audio) {
			this._setConnectionQualityAudio(this._calculateConnectionQualityAudio())
		}
		if (this._analysisEnabled.video) {
			this._setConnectionQualityVideo(this._calculateConnectionQualityVideo())
		}
	},

	_processSenderStats(stats) {
		// Packets are calculated as "packetsReceived + packetsLost" or as
		// "packetsSent" depending on the browser (see below).
		const packets = {
			audio: -1,
			video: -1,
		}

		// Packets stats for a sender are checked from the point of view of the
		// receiver.
		const packetsReceived = {
			audio: -1,
			video: -1,
		}

		const packetsLost = {
			audio: -1,
			video: -1,
		}

		// If "packetsReceived" is not available (like in Chromium) use
		// "packetsSent" instead; it may be measured at a different time from
		// the received statistics, so checking "packetsLost" against it may not
		// be fully accurate, but it should be close enough.
		const packetsSent = {
			audio: -1,
			video: -1,
		}

		// Timestamp is set to "timestampReceived" or "timestampSent" depending
		// on how "packets" were calculated.
		const timestamp = {
			audio: -1,
			video: -1,
		}

		const timestampReceived = {
			audio: -1,
			video: -1,
		}

		const timestampSent = {
			audio: -1,
			video: -1,
		}

		const roundTripTime = {
			audio: -1,
			video: -1,
		}

		for (const stat of stats.values()) {
			if (!this._analysisEnabled[stat.kind]) {
				continue
			}

			if (stat.type === 'outbound-rtp') {
				if ('packetsSent' in stat && 'kind' in stat) {
					packetsSent[stat.kind] = (packetsSent[stat.kind] === -1) ? stat.packetsSent : packetsSent[stat.kind] + stat.packetsSent

					if ('timestamp' in stat && 'kind' in stat) {
						timestampSent[stat.kind] = stat.timestamp
					}
				}
			} else if (stat.type === 'remote-inbound-rtp') {
				if ('packetsReceived' in stat && 'kind' in stat) {
					packetsReceived[stat.kind] = (packetsReceived[stat.kind] === -1) ? stat.packetsReceived : packetsReceived[stat.kind] + stat.packetsReceived

					if ('timestamp' in stat && 'kind' in stat) {
						timestampReceived[stat.kind] = stat.timestamp
					}
				}
				if ('packetsLost' in stat && 'kind' in stat) {
					packetsLost[stat.kind] = (packetsLost[stat.kind] === -1) ? stat.packetsLost : packetsLost[stat.kind] + stat.packetsLost
				}
				if ('roundTripTime' in stat && 'kind' in stat) {
					roundTripTime[stat.kind] = (roundTripTime[stat.kind] === -1) ? stat.roundTripTime : Math.max(roundTripTime[stat.kind], stat.roundTripTime)
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

			// In some (also strange) cases a newer stat may report a lower
			// value than a previous one (it happens sometimes with garbage
			// remote reports in simulcast video that cause the values to
			// overflow, although it was also seen with a small value regression
			// when enabling video). If that happens the stats are reset to
			// prevent distorting the analysis with negative packet counts; note
			// that in this case the previous value is not kept because it is
			// not just an isolated wrong value, all the following stats
			// increase from the regressed value.
			if (packets[kind] >= 0 && packets[kind] < this._packets[kind].getLastRawValue()) {
				this._resetStats(kind)
			}

			this._addStats(kind, packets[kind], packetsLost[kind], timestamp[kind], roundTripTime[kind])
		}
	},

	_processReceiverStats(stats) {
		// Packets are calculated as "packetsReceived + packetsLost".
		const packets = {
			audio: -1,
			video: -1,
		}

		const packetsReceived = {
			audio: -1,
			video: -1,
		}

		const packetsLost = {
			audio: -1,
			video: -1,
		}

		const timestamp = {
			audio: -1,
			video: -1,
		}

		for (const stat of stats.values()) {
			if (!this._analysisEnabled[stat.kind]) {
				continue
			}

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

			this._addStats(kind, packets[kind], packetsLost[kind], timestamp[kind])
		}
	},

	/**
	 * Adds the stats reported by the browser to the average stats used to do
	 * the analysis.
	 *
	 * The stats reported by the browser can sometimes stall for a second (or
	 * more, but typically they stall only for a single report). When that
	 * happens the stats are still reported, but with the same number of packets
	 * as in the previous report (timestamp and round trip time may be updated
	 * or not, apparently depending on browser version and/or Janus version). In
	 * that case the given stats are not added yet to the average stats; they
	 * are kept on hold until more stats are provided by the browser and it can
	 * be determined if the previous stats were stalled or not. If they were
	 * stalled the previous and new stats are distributed, and if they were not
	 * they are added as is to the average stats.
	 *
	 * @param {string} kind the type of the stats ("audio" or "video")
	 * @param {number} packets the cumulative number of packets
	 * @param {number} packetsLost the cumulative number of lost packets
	 * @param {number} timestamp the cumulative timestamp
	 * @param {number} roundTripTime the relative round trip time
	 */
	_addStats(kind, packets, packetsLost, timestamp, roundTripTime) {
		if (this._stagedPackets[kind].length === 0) {
			if (packets !== this._packets[kind].getLastRawValue()) {
				this._commitStats(kind, packets, packetsLost, timestamp, roundTripTime)
			} else {
				this._stageStats(kind, packets, packetsLost, timestamp, roundTripTime)
			}

			return
		}

		this._stageStats(kind, packets, packetsLost, timestamp, roundTripTime)

		// Distributing the stats has no effect if the stats were not stalled
		// (that is, if the values are still unchanged, so it is probably an
		// actual connection problem rather than a stalled report).
		this._distributeStagedStats(kind)

		while (this._stagedPackets[kind].length > 0) {
			const stagedPackets = this._stagedPackets[kind].shift()
			const stagedPacketsLost = this._stagedPacketsLost[kind].shift()
			const stagedTimestamp = this._stagedTimestamps[kind].shift()
			const stagedRoundTripTime = this._stagedRoundTripTime[kind].shift()

			this._commitStats(kind, stagedPackets, stagedPacketsLost, stagedTimestamp, stagedRoundTripTime)
		}
	},

	_stageStats(kind, packets, packetsLost, timestamp, roundTripTime) {
		this._stagedPackets[kind].push(packets)
		this._stagedPacketsLost[kind].push(packetsLost)
		this._stagedTimestamps[kind].push(timestamp)
		this._stagedRoundTripTime[kind].push(roundTripTime)
	},

	/**
	 * Distributes the values of the staged stats proportionately to their
	 * timestamps.
	 *
	 * Once the stats unstall the new stats are a sum of the values that should
	 * have been reported before and the actual new values. The stats typically
	 * stall for just a second, but they can stall for an arbitrary length too.
	 * Due to this the staged stats need to be distributed based on their
	 * timestamps.
	 *
	 * @param {string} kind the type of the stats ("audio" or "video")
	 */
	_distributeStagedStats(kind) {
		let packetsBase = this._packets[kind].getLastRawValue()
		let packetsLostBase = this._packetsLost[kind].getLastRawValue()
		let timestampsBase = this._timestamps[kind].getLastRawValue()

		let packetsTotal = 0
		let packetsLostTotal = 0
		let timestampsTotal = 0

		// If the last timestamp is still stalled there is nothing to
		// distribute.
		if (this._stagedTimestamps[kind][this._stagedTimestamps[kind].length - 1] === timestampsBase) {
			return
		}

		// If the first timestamp stalled it is assumed that all of them
		// stalled and are thus evenly distributed based on the new timestamp.
		if (this._stagedTimestamps[kind][0] === timestampsBase) {
			const lastTimestamp = this._stagedTimestamps[kind][this._stagedTimestamps[kind].length - 1]
			const timestampsTotalDifference = lastTimestamp - timestampsBase
			const timestampsDelta = timestampsTotalDifference / this._stagedTimestamps[kind].length

			for (let i = 0; i < this._stagedTimestamps[kind].length - 1; i++) {
				this._stagedTimestamps[kind][i] += timestampsDelta * (i + 1)
			}
		}

		for (let i = 0; i < this._stagedPackets[kind].length; i++) {
			packetsTotal += (this._stagedPackets[kind][i] - packetsBase)
			packetsBase = this._stagedPackets[kind][i]

			packetsLostTotal += (this._stagedPacketsLost[kind][i] - packetsLostBase)
			packetsLostBase = this._stagedPacketsLost[kind][i]

			timestampsTotal += (this._stagedTimestamps[kind][i] - timestampsBase)
			timestampsBase = this._stagedTimestamps[kind][i]
		}

		packetsBase = this._packets[kind].getLastRawValue()
		packetsLostBase = this._packetsLost[kind].getLastRawValue()
		timestampsBase = this._timestamps[kind].getLastRawValue()

		for (let i = 0; i < this._stagedPackets[kind].length; i++) {
			const weight = (this._stagedTimestamps[kind][i] - timestampsBase) / timestampsTotal
			timestampsBase = this._stagedTimestamps[kind][i]

			this._stagedPackets[kind][i] = packetsBase + packetsTotal * weight
			packetsBase = this._stagedPackets[kind][i]

			this._stagedPacketsLost[kind][i] = packetsLostBase + packetsLostTotal * weight
			packetsLostBase = this._stagedPacketsLost[kind][i]

			// Timestamps and round trip time are not distributed, as those
			// values may be properly updated even if the stats are stalled. In
			// case they were not timestamps were already evenly distributed
			// above, and round trip time can not be distributed, as it is
			// already provided in the stats as a relative value rather than a
			// cumulative one.
		}
	},

	_commitStats(kind, packets, packetsLost, timestamp, roundTripTime) {
		if (packets >= 0) {
			this._packets[kind].add(packets)
		}
		if (packetsLost >= 0) {
			this._packetsLost[kind].add(packetsLost)
		}
		if (packets >= 0 && packetsLost >= 0) {
			// The packet stats are cumulative values, so the isolated values
			// are got from the helper object.
			// If there were no transmitted packets in the last stats the ratio
			// is higher than 1 both to signal that and to force the quality
			// towards "no transmitted data" faster, but not immediately.
			// However, note that the quality will immediately change to "very
			// bad quality".
			let packetsLostRatio = 1.5
			if (this._packets[kind].getLastRelativeValue() > 0) {
				packetsLostRatio = this._packetsLost[kind].getLastRelativeValue() / this._packets[kind].getLastRelativeValue()
			}
			this._packetsLostRatio[kind].add(packetsLostRatio)
		}
		if (timestamp >= 0) {
			this._timestamps[kind].add(timestamp)
			this._timestampsForLogs[kind].add(timestamp)
		}
		if (packets >= 0 && timestamp >= 0) {
			const elapsedSeconds = this._timestamps[kind].getLastRelativeValue() / 1000
			// The packet stats are cumulative values, so the isolated
			// values are got from the helper object.
			const packetsPerSecond = this._packets[kind].getLastRelativeValue() / elapsedSeconds
			this._packetsPerSecond[kind].add(packetsPerSecond)
		}
		if (roundTripTime !== undefined && roundTripTime >= 0) {
			this._roundTripTime[kind].add(roundTripTime)
		}
	},

	_calculateConnectionQualityAudio() {
		return this._calculateConnectionQuality('audio')
	},

	_calculateConnectionQualityVideo() {
		return this._calculateConnectionQuality('video')
	},

	_calculateConnectionQuality(kind) {
		const packets = this._packets[kind]
		const packetsLost = this._packetsLost[kind]
		const timestamps = this._timestamps[kind]
		const packetsLostRatio = this._packetsLostRatio[kind]
		const packetsPerSecond = this._packetsPerSecond[kind]
		const roundTripTime = this._roundTripTime[kind]

		// packetsLostRatio and packetsPerSecond are relative values, but they
		// are calculated from cumulative values. Therefore, it is necessary to
		// check if the cumulative values that are their source have enough data
		// or not, rather than checking if the relative values themselves have
		// enough data.
		if (!packets.hasEnoughData() || !packetsLost.hasEnoughData() || !timestamps.hasEnoughData()) {
			return CONNECTION_QUALITY.UNKNOWN
		}

		// The stats might be in a temporary stall and the analysis is on hold
		// until further stats arrive, so until that happens the last known
		// state is returned again.
		if (this._stagedPackets[kind].length > 0) {
			return this._connectionQuality[kind]
		}

		const packetsLostRatioWeightedAverage = packetsLostRatio.getWeightedAverage()
		if (packetsLostRatioWeightedAverage >= 1) {
			this._logStats(kind, 'No transmitted data, packet lost ratio: ' + packetsLostRatioWeightedAverage)

			return CONNECTION_QUALITY.NO_TRANSMITTED_DATA
		}

		// A high round trip time means that the delay is high, but it can also
		// imply that some packets, even if they are not lost, are anyway
		// discarded to try to keep the playing rate in real time.
		// Round trip time is measured in seconds.
		if (roundTripTime.hasEnoughData() && roundTripTime.getWeightedAverage() > 1.5) {
			this._logStats(kind, 'High round trip time: ' + roundTripTime.getWeightedAverage())

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
		// Despite all of the above it has been observed that less than 10
		// packets are sometimes sent without any connection problem (for
		// example, when the background is blurred and the video quality is
		// reduced due to being in a call with several participants), so for now
		// it is only logged but not reported.
		if (packetsPerSecond.getWeightedAverage() < 10) {
			this._logStats(kind, 'Low packets per second: ' + packetsPerSecond.getWeightedAverage())
		}

		if (packetsLostRatioWeightedAverage > 0.3) {
			this._logStats(kind, 'High packet lost ratio: ' + packetsLostRatioWeightedAverage)

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

	_getLogTag(kind) {
		let type = kind
		if (this._peerType === PEER_TYPE.SCREEN) {
			type += ' (screen)'
		}

		return 'PeerConnectionAnalyzer: ' + type + ': '
	},

	_logStats(kind, message) {
		const tag = this._getLogTag(kind)

		if (message) {
			console.debug(tag + message)
		}

		console.debug(tag + 'Packets: ' + this._packets[kind].toString())
		console.debug(tag + 'Packets lost: ' + this._packetsLost[kind].toString())
		console.debug(tag + 'Packets lost ratio: ' + this._packetsLostRatio[kind].toString())
		console.debug(tag + 'Packets per second: ' + this._packetsPerSecond[kind].toString())
		console.debug(tag + 'Round trip time: ' + this._roundTripTime[kind].toString())
		console.debug(tag + 'Timestamps: ' + this._timestampsForLogs[kind].toString())
	},

}

EmitterMixin.apply(PeerConnectionAnalyzer.prototype)

export {
	CONNECTION_QUALITY,
	PEER_DIRECTION,
	PEER_TYPE,
	PeerConnectionAnalyzer,
}
