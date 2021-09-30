/* global module */

const util = require('util')
const getScreenMedia = require('./getscreenmedia')
const WildEmitter = require('wildemitter')
const mockconsole = require('mockconsole')
const UAParser = require('ua-parser-js')
// Only mediaDevicesManager is used, but it can not be assigned here due to not
// being initialized yet.
const webrtcIndex = require('../index.js')
const SpeakingMonitor = require('../../media/pipeline/SpeakingMonitor.js').default
const TrackEnabler = require('../../media/pipeline/TrackEnabler.js').default
const TrackToStream = require('../../media/pipeline/TrackToStream.js').default

/**
 * @param {object} opts the options object.
 */
function LocalMedia(opts) {
	WildEmitter.call(this)

	const config = this.config = {
		audioFallback: false,
		logger: mockconsole,
	}

	let item
	for (item in opts) {
		if (Object.prototype.hasOwnProperty.call(opts, item)) {
			this.config[item] = opts[item]
		}
	}

	this.logger = config.logger
	this._log = this.logger.log.bind(this.logger, 'LocalMedia:')
	this._logerror = this.logger.error.bind(this.logger, 'LocalMedia:')

	this._localMediaActive = false

	this.localStreams = []
	this.localScreens = []

	if (!webrtcIndex.mediaDevicesManager.isSupported()) {
		this._logerror('Your browser does not support local media capture.')
	}

	this._audioTrackEnabler = new TrackEnabler()
	this._videoTrackEnabler = new TrackEnabler()

	this._speakingMonitor = new SpeakingMonitor()
	this._speakingMonitor.on('speaking', () => {
		this.emit('speaking')
	})
	this._speakingMonitor.on('speakingWhileMuted', () => {
		this.emit('speakingWhileMuted')
	})
	this._speakingMonitor.on('stoppedSpeaking', () => {
		this.emit('stoppedSpeaking')
	})
	this._speakingMonitor.on('stoppedSpeakingWhileMuted', () => {
		this.emit('stoppedSpeakingWhileMuted')
	})
	this._speakingMonitor.on('volumeChange', (speakingMonitor, volume, threshold) => {
		this.emit('volumeChange', volume, threshold)
	})

	this._trackToStream = new TrackToStream()
	this._trackToStream.addInputTrackSlot('audio')
	this._trackToStream.addInputTrackSlot('video')

	this._handleStreamSetBound = this._handleStreamSet.bind(this)
	this._handleTrackReplacedBound = this._handleTrackReplaced.bind(this)
	this._handleTrackEnabledBound = this._handleTrackEnabled.bind(this)

	this._audioTrackEnabler.connectTrackSink('default', this._speakingMonitor)
	this._audioTrackEnabler.connectTrackSink('default', this._trackToStream, 'audio')

	this._videoTrackEnabler.connectTrackSink('default', this._trackToStream, 'video')

	this._handleAudioInputIdChangedBound = this._handleAudioInputIdChanged.bind(this)
	this._handleVideoInputIdChangedBound = this._handleVideoInputIdChanged.bind(this)
}

util.inherits(LocalMedia, WildEmitter)

/**
 * Returns whether the local media is active or not.
 *
 * The local media is active if it has been started and not stopped yet, even if
 * no media was available when started. An active local media will automatically
 * react to changes in the selected media devices.
 *
 * @return {boolean} true if the local media is active, false otherwise
 */
LocalMedia.prototype.isLocalMediaActive = function() {
	return this._localMediaActive
}

/**
 * Adjusts video constraints to work around bug in Chromium.
 *
 * In Chromium it is not possible to increase the resolution of a track once it
 * has been cloned, so the track needs to be initialized with a high resolution
 * (otherwise real devices are initialized with a resolution around 640x480).
 * Therefore, the video is requested with a loose constraint for a high
 * resolution, so if the camera does not have such resolution it will still
 * return the highest resolution available without failing.
 *
 * A high frame rate needs to be requested too, as some cameras offer high
 * resolution but with low frame rates, so Chromium could end providing a laggy
 * high resolution video. If the frame rate is requested too then Chromium needs
 * to balance all the constraints and thus provide a video without the highest
 * resolution but with an acceptable frame rate.
 *
 * @param {object} constraints the constraints to be adjusted
 */
LocalMedia.prototype._adjustVideoConstraintsForChromium = function(constraints) {
	const parser = new UAParser()
	const browserName = parser.getBrowser().name

	if (browserName !== 'Chrome'
		&& browserName !== 'Chromium'
		&& browserName !== 'Opera'
		&& browserName !== 'Safari'
		&& browserName !== 'Mobile Safari'
		&& browserName !== 'Edge') {
		return
	}

	if (!constraints.video) {
		return
	}

	if (!(constraints.video instanceof Object)) {
		constraints.video = {}
	}

	constraints.video.width = 1920
	constraints.video.height = 1200
	constraints.video.frameRate = 60
}

LocalMedia.prototype.start = function(mediaConstraints, cb, context) {
	const self = this
	const constraints = mediaConstraints || { audio: true, video: true }

	// If local media is started with neither audio nor video the local media
	// will not be active (it will not react to changes in the selected media
	// devices). It is just a special case in which starting succeeds with a null
	// stream.
	if (!constraints.audio && !constraints.video) {
		self.emit('localStream', constraints, null)

		if (cb) {
			return cb(null, null, constraints)
		}

		return
	}

	if (!webrtcIndex.mediaDevicesManager.isSupported()) {
		const error = new Error('MediaStreamError')
		error.name = 'NotSupportedError'

		if (cb) {
			return cb(error, null)
		}

		return
	}

	this.emit('localStreamRequested', constraints, context)

	if (!context) {
		// Try to get the devices list before getting user media.
		webrtcIndex.mediaDevicesManager.enableDeviceEvents()
		webrtcIndex.mediaDevicesManager.disableDeviceEvents()
	}

	this._adjustVideoConstraintsForChromium(constraints)

	// The handlers for "change:audioInputId" and "change:videoInputId" events
	// expect the initial "getUserMedia" call to have been completed before
	// being used, so they must be set when the promise is resolved or rejected.

	webrtcIndex.mediaDevicesManager.getUserMedia(constraints).then(function(stream) {
		// Although the promise should be resolved only if all the constraints
		// are met Edge resolves it if both audio and video are requested but
		// only audio is available.
		if (constraints.video && stream.getVideoTracks().length === 0) {
			self.emit('localStreamRequestFailedRetryNoVideo', constraints)
			constraints.video = false
			self.start(constraints, cb, 'retry-no-video')
			return
		}

		stream.getTracks().forEach(function(track) {
			if (track.kind === 'audio') {
				self._audioTrackEnabler._setInputTrack('default', track)
			}
			if (track.kind === 'video') {
				self._videoTrackEnabler._setInputTrack('default', track)
			}
		})

		self.localStreams.push(self._trackToStream.getStream())

		self.emit('localStream', constraints, self._trackToStream.getStream())

		self._trackToStream.on('streamSet', self._handleStreamSetBound)
		self._trackToStream.on('trackReplaced', self._handleTrackReplacedBound)
		self._trackToStream.on('trackEnabled', self._handleTrackEnabledBound)

		webrtcIndex.mediaDevicesManager.on('change:audioInputId', self._handleAudioInputIdChangedBound)
		webrtcIndex.mediaDevicesManager.on('change:videoInputId', self._handleVideoInputIdChangedBound)

		self._localMediaActive = true

		if (cb) {
			return cb(null, self._trackToStream.getStream(), constraints)
		}
	}).catch(function(err) {
		// Fallback for users without a camera or with a camera that can not be
		// accessed, but only if audio is meant to be used.
		if (constraints.audio !== false && self.config.audioFallback && constraints.video !== false) {
			self.emit('localStreamRequestFailedRetryNoVideo', constraints, err)
			constraints.video = false
			self.start(constraints, cb, 'retry-no-video')
			return
		}

		self.emit('localStreamRequestFailed', constraints)

		self._trackToStream.on('streamSet', self._handleStreamSetBound)
		self._trackToStream.on('trackReplaced', self._handleTrackReplacedBound)
		self._trackToStream.on('trackEnabled', self._handleTrackEnabledBound)

		webrtcIndex.mediaDevicesManager.on('change:audioInputId', self._handleAudioInputIdChangedBound)
		webrtcIndex.mediaDevicesManager.on('change:videoInputId', self._handleVideoInputIdChangedBound)

		self._localMediaActive = true

		if (cb) {
			return cb(err, null)
		}
	})
}

LocalMedia.prototype._handleStreamSet = function(trackToStream, newStream, oldStream) {
	if (oldStream) {
		this._removeStream(oldStream)
	}

	if (newStream) {
		this.localStreams.push(newStream)
	}

	// "streamSet" is always emitted along with "trackReplaced", so the
	// "localStreamChanged" only needs to be relayed on "trackReplaced".
}

LocalMedia.prototype._handleTrackReplaced = function(trackToStream, newTrack, oldTrack) {
	// "localStreamChanged" is expected to be emitted also when the tracks of
	// the stream change, even if the stream itself is the same.
	this.emit('localStreamChanged', trackToStream.getStream())
	this.emit('localTrackReplaced', newTrack, oldTrack, trackToStream.getStream())
}

LocalMedia.prototype._handleTrackEnabled = function(trackToStream, track) {
	// MediaStreamTrack does not emit an event when the enabled property
	// changes, so it needs to be explicitly notified.
	this.emit('localTrackEnabledChanged', track, trackToStream.getStream())
}

LocalMedia.prototype._handleAudioInputIdChanged = function(mediaDevicesManager, audioInputId) {
	if (this._pendingAudioInputIdChangedCount) {
		this._pendingAudioInputIdChangedCount++

		return
	}

	this._pendingAudioInputIdChangedCount = 1

	const resetPendingAudioInputIdChangedCount = () => {
		const audioInputIdChangedAgain = this._pendingAudioInputIdChangedCount > 1

		this._pendingAudioInputIdChangedCount = 0

		if (audioInputIdChangedAgain) {
			this._handleAudioInputIdChanged(webrtcIndex.mediaDevicesManager.get('audioInputId'))
		}
	}

	if (audioInputId === null) {
		if (this._audioTrackEnabler.getInputTrack()) {
			this._audioTrackEnabler.getInputTrack().stop()
		}
		this._audioTrackEnabler._setInputTrack('default', null)

		resetPendingAudioInputIdChangedCount()

		return
	}

	if (this._audioTrackEnabler.getInputTrack()) {
		const settings = this._audioTrackEnabler.getInputTrack().getSettings()
		if (settings && settings.deviceId === audioInputId) {
			return
		}
	}

	webrtcIndex.mediaDevicesManager.getUserMedia({ audio: true }).then(stream => {
		// According to the specification "getUserMedia({ audio: true })" will
		// return a single audio track.
		const track = stream.getTracks()[0]
		if (stream.getTracks().length > 1) {
			console.error('More than a single audio track returned by getUserMedia, only the first one will be used')
		}

		if (this._audioTrackEnabler.getInputTrack()) {
			this._audioTrackEnabler.getInputTrack().stop()
		}
		this._audioTrackEnabler._setInputTrack('default', track)

		resetPendingAudioInputIdChangedCount()
	}).catch(() => {
		if (this._audioTrackEnabler.getInputTrack()) {
			this._audioTrackEnabler.getInputTrack().stop()
		}
		this._audioTrackEnabler._setInputTrack('default', null)

		resetPendingAudioInputIdChangedCount()
	})
}

LocalMedia.prototype._handleVideoInputIdChanged = function(mediaDevicesManager, videoInputId) {
	if (this._pendingVideoInputIdChangedCount) {
		this._pendingVideoInputIdChangedCount++

		return
	}

	this._pendingVideoInputIdChangedCount = 1

	const resetPendingVideoInputIdChangedCount = () => {
		const videoInputIdChangedAgain = this._pendingVideoInputIdChangedCount > 1

		this._pendingVideoInputIdChangedCount = 0

		if (videoInputIdChangedAgain) {
			this._handleVideoInputIdChanged(webrtcIndex.mediaDevicesManager.get('videoInputId'))
		}
	}

	if (videoInputId === null) {
		if (this._videoTrackEnabler.getInputTrack()) {
			this._videoTrackEnabler.getInputTrack().stop()
		}
		this._videoTrackEnabler._setInputTrack('default', null)

		resetPendingVideoInputIdChangedCount()

		return
	}

	if (this._videoTrackEnabler.getInputTrack()) {
		const settings = this._videoTrackEnabler.getInputTrack().getSettings()
		if (settings && settings.deviceId === videoInputId) {
			return
		}
	}

	const constraints = { video: true }
	this._adjustVideoConstraintsForChromium(constraints)

	webrtcIndex.mediaDevicesManager.getUserMedia(constraints).then(stream => {
		// According to the specification "getUserMedia({ video: true })" will
		// return a single video track.
		const track = stream.getTracks()[0]
		if (stream.getTracks().length > 1) {
			console.error('More than a single video track returned by getUserMedia, only the first one will be used')
		}

		if (this._videoTrackEnabler.getInputTrack()) {
			this._videoTrackEnabler.getInputTrack().stop()
		}
		this._videoTrackEnabler._setInputTrack('default', track)

		resetPendingVideoInputIdChangedCount()
	}).catch(() => {
		if (this._videoTrackEnabler.getInputTrack()) {
			this._videoTrackEnabler.getInputTrack().stop()
		}
		this._videoTrackEnabler._setInputTrack('default', null)

		resetPendingVideoInputIdChangedCount()
	})
}

LocalMedia.prototype.stop = function() {
	// Handlers need to be removed before stopping the stream to prevent
	// relaying no longer needed events.
	this._trackToStream.off('streamSet', this._handleStreamSetBound)
	this._trackToStream.off('trackReplaced', this._handleTrackReplacedBound)
	this._trackToStream.off('trackEnabled', this._handleTrackEnabledBound)

	this.stopStream()
	this.stopScreenShare()

	webrtcIndex.mediaDevicesManager.off('change:audioInputId', this._handleAudioInputIdChangedBound)
	webrtcIndex.mediaDevicesManager.off('change:videoInputId', this._handleVideoInputIdChangedBound)

	this._localMediaActive = false
}

LocalMedia.prototype.stopStream = function() {
	const stream = this._trackToStream.getStream()

	if (this._audioTrackEnabler.getInputTrack()) {
		this._audioTrackEnabler.getInputTrack().stop()
		this._audioTrackEnabler._setInputTrack('default', null)
	}
	if (this._videoTrackEnabler.getInputTrack()) {
		this._videoTrackEnabler.getInputTrack().stop()
		this._videoTrackEnabler._setInputTrack('default', null)
	}

	if (stream) {
		this._removeStream(stream)
	}
}

LocalMedia.prototype.startScreenShare = function(mode, constraints, cb) {
	const self = this

	this.emit('localScreenRequested')

	if (typeof constraints === 'function' && !cb) {
		cb = constraints
		constraints = null
	}

	getScreenMedia(mode, constraints, function(err, stream) {
		if (!err) {
			self.localScreens.push(stream)

			stream.getTracks().forEach(function(track) {
				track.addEventListener('ended', function() {
					let isAllTracksEnded = true
					stream.getTracks().forEach(function(t) {
						isAllTracksEnded = t.readyState === 'ended' && isAllTracksEnded
					})

					if (isAllTracksEnded) {
						self._removeStream(stream)
					}
				})
			})

			self.emit('localScreen', stream)
		} else {
			self.emit('localScreenRequestFailed')
		}

		// enable the callback
		if (cb) {
			return cb(err, stream)
		}
	})
}

LocalMedia.prototype.stopScreenShare = function() {
	const self = this

	this.localScreens.forEach(function(stream) {
		stream.getTracks().forEach(function(track) { track.stop() })
		self._removeStream(stream)
	})
}

// Audio controls
LocalMedia.prototype.mute = function() {
	this._setAudioEnabled(false)
	this.emit('audioOff')
}

LocalMedia.prototype.unmute = function() {
	this._setAudioEnabled(true)
	this.emit('audioOn')
}

// Video controls
LocalMedia.prototype.pauseVideo = function() {
	this._setVideoEnabled(false)
	this.emit('videoOff')
}
LocalMedia.prototype.resumeVideo = function() {
	this._setVideoEnabled(true)
	this.emit('videoOn')
}

// Combined controls
LocalMedia.prototype.pause = function() {
	this.mute()
	this.pauseVideo()
}
LocalMedia.prototype.resume = function() {
	this.unmute()
	this.resumeVideo()
}

// Internal methods for enabling/disabling audio/video
LocalMedia.prototype._setAudioEnabled = function(bool) {
	this._audioTrackEnabler.setEnabled(bool)
}
LocalMedia.prototype._setVideoEnabled = function(bool) {
	this._videoTrackEnabler.setEnabled(bool)
}

// check if all audio streams are enabled
LocalMedia.prototype.isAudioEnabled = function() {
	let enabled = true
	let hasAudioTracks = false
	this.localStreams.forEach(function(stream) {
		const audioTracks = stream.getAudioTracks()
		if (audioTracks.length > 0) {
			hasAudioTracks = true
			audioTracks.forEach(function(track) {
				enabled = enabled && track.enabled
			})
		}
	})

	// If no audioTracks were found, that means there is no microphone device.
	// In that case, isAudioEnabled should return false.
	if (!hasAudioTracks) {
		return false
	}

	return enabled
}

// check if all video streams are enabled
LocalMedia.prototype.isVideoEnabled = function() {
	let enabled = true
	let hasVideoTracks = false
	this.localStreams.forEach(function(stream) {
		const videoTracks = stream.getVideoTracks()
		if (videoTracks.length > 0) {
			hasVideoTracks = true
			videoTracks.forEach(function(track) {
				enabled = enabled && track.enabled
			})
		}
	})

	// If no videoTracks were found, that means there is no camera device.
	// In that case, isVideoEnabled should return false.
	if (!hasVideoTracks) {
		return false
	}

	return enabled
}

LocalMedia.prototype._removeStream = function(stream) {
	let idx = this.localStreams.indexOf(stream)
	if (idx > -1) {
		this.localStreams.splice(idx, 1)
		this.emit('localStreamStopped', stream)
	} else {
		idx = this.localScreens.indexOf(stream)
		if (idx > -1) {
			this.localScreens.splice(idx, 1)
			this.emit('localScreenStopped', stream)
		}
	}
}

// fallback for old .localScreen behaviour
Object.defineProperty(LocalMedia.prototype, 'localScreen', {
	get() {
		return this.localScreens.length > 0 ? this.localScreens[0] : null
	},
})

module.exports = LocalMedia
