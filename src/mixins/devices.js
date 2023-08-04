/**
 * @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import attachMediaStream from 'attachmediastream/attachmediastream.bundle.js'
import hark from 'hark'

import TrackToStream from '../utils/media/pipeline/TrackToStream.js'
import VirtualBackground from '../utils/media/pipeline/VirtualBackground.js'
import { mediaDevicesManager } from '../utils/webrtc/index.js'

export const devices = {

	data() {
		return {
			mediaDevicesManager,
			pendingGetUserMediaAudioCount: 0,
			pendingGetUserMediaVideoCount: 0,
			audioStream: null,
			audioStreamError: null,
			videoStream: null,
			videoStreamError: null,
			hark: null,
			initialized: false,
			currentVolume: -100,
			volumeThreshold: -100,
			virtualBackground: null,
			videoTrackToStream: null,
		}
	},

	props: {
		initializeOnMounted: {
			type: Boolean,
			default: true,
		},
	},

	methods: {
		initializeDevicesMixin() {
			this.initialized = true

			if (!this.mediaDevicesManager.isSupported()) {
				// DOMException constructor is not supported in Internet Explorer,
				// so a plain object is used instead.
				this.audioStreamError = {
					message: 'MediaDevicesManager is not supported',
					name: 'NotSupportedError',
				}
				this.videoStreamError = {
					message: 'MediaDevicesManager is not supported',
					name: 'NotSupportedError',
				}
			}

			this.mediaDevicesManager.enableDeviceEvents()
			this.updateAudioStream()
			this.updateVideoStream()
		},

		stopDevicesMixin() {
			this.initialized = false

			this.stopAudioStream()
			this.stopVideoStream()
			this.mediaDevicesManager.disableDeviceEvents()

		},

		updateAudioStream() {
			if (!this.mediaDevicesManager.isSupported()) {
				return
			}

			if (this.audioStreamInputId && this.audioStreamInputId === this.audioInputId) {
				return
			}

			if (this.pendingGetUserMediaAudioCount) {
				this.pendingGetUserMediaAudioCount++

				return
			}

			// When the audio input device changes the previous stream must be
			// stopped before a new one is requested, as for example currently
			// Firefox does not support having two different audio input devices
			// active at the same time:
			// https://bugzilla.mozilla.org/show_bug.cgi?id=1468700
			this.stopAudioStream()

			this.audioStreamError = null

			if (this.audioInputId === null || this.audioInputId === undefined) {
				return
			}

			this.pendingGetUserMediaAudioCount = 1

			const resetPendingGetUserMediaAudioCount = () => {
				const updateAudioStreamAgain = this.pendingGetUserMediaAudioCount > 1

				this.pendingGetUserMediaAudioCount = 0

				if (updateAudioStreamAgain) {
					this.updateAudioStream()
				}
			}

			this.mediaDevicesManager.getUserMedia({ audio: true })
				.then(stream => {
					if (!this.initialized) {
						// The promise was fulfilled once the stream is no
						// longer needed, so just discard it.
						stream.getTracks().forEach((track) => {
							track.stop()
						})
					} else {
						this.setAudioStream(stream)
					}

					resetPendingGetUserMediaAudioCount()
				})
				.catch(error => {
					console.error('Error getting audio stream: ' + error.name + ': ' + error.message)
					this.audioStreamError = error
					this.setAudioStream(null)

					resetPendingGetUserMediaAudioCount()
				})
		},

		updateVideoStream() {
			if (!this.mediaDevicesManager.isSupported()) {
				return
			}

			if (this.videoStreamInputId && this.videoStreamInputId === this.videoInputId) {
				return
			}

			if (this.pendingGetUserMediaVideoCount) {
				this.pendingGetUserMediaVideoCount++

				return
			}

			// Video stream is stopped too to avoid potential issues similar to
			// the audio ones (see "updateAudioStream").
			this.stopVideoStream()

			this.videoStreamError = null

			if (this.videoInputId === null || this.videoInputId === undefined) {
				return
			}

			this.pendingGetUserMediaVideoCount = 1

			const resetPendingGetUserMediaVideoCount = () => {
				const updateVideoStreamAgain = this.pendingGetUserMediaVideoCount > 1

				this.pendingGetUserMediaVideoCount = 0

				if (updateVideoStreamAgain) {
					this.updateVideoStream()
				}
			}

			this.mediaDevicesManager.getUserMedia({ video: true })
				.then(stream => {
					if (!this.initialized) {
						// The promise was fulfilled once the stream is no
						// longer needed, so just discard it.
						stream.getTracks().forEach((track) => {
							track.stop()
						})
					} else {
						this.setVideoStream(stream)
					}

					resetPendingGetUserMediaVideoCount()
				})
				.catch(error => {
					console.error('Error getting video stream: ' + error.name + ': ' + error.message)
					this.videoStreamError = error
					this.setVideoStream(null)

					resetPendingGetUserMediaVideoCount()
				})
		},

		setAudioStream(audioStream) {
			this.audioStream = audioStream

			if (!audioStream) {
				return
			}

			this.hark = hark(this.audioStream)
			this.hark.on('volume_change', (volume, volumeThreshold) => {
				this.currentVolume = volume
				this.volumeThreshold = volumeThreshold
			})
		},

		setVideoStream(videoStream) {
			this.videoStream = videoStream

			if (!this.$refs.video) {
				return
			}

			if (!videoStream) {
				this.virtualBackground._setInputTrack('default', null)

				return
			}

			this.virtualBackground._setInputTrack('default', this.videoStream.getVideoTracks()[0])

			const options = {
				autoplay: true,
				mirror: true,
				muted: true,
			}
			attachMediaStream(this.videoTrackToStream.getStream(), this.$refs.video, options)
		},

		stopAudioStream() {
			if (!this.audioStream) {
				return
			}

			this.audioStream.getTracks().forEach(function(track) {
				track.stop()
			})

			this.audioStream = null

			if (this.hark) {
				this.hark.stop()
				this.hark.off('volume_change')
				this.hark = null
			}
		},

		stopVideoStream() {
			this.virtualBackground._setInputTrack('default', null)

			if (!this.videoStream) {
				return
			}

			this.videoStream.getTracks().forEach(function(track) {
				track.stop()
			})

			this.videoStream = null

			if (this.$refs.video) {
				this.$refs.video.srcObject = null
			}
		},
	},

	mounted() {
		this.virtualBackground = new VirtualBackground()
		// The virtual background should be enabled and disabled as needed by
		// the inheriters of the mixin.
		this.virtualBackground.setEnabled(false)

		this.videoTrackToStream = new TrackToStream()
		this.videoTrackToStream.addInputTrackSlot('video')

		this.virtualBackground.connectTrackSink('default', this.videoTrackToStream, 'video')

		if (this.initializeOnMounted) {
			this.initializeDevicesMixin()
		}
	},

	destroyed() {
		this.stopDevicesMixin()
	},

	watch: {
		audioInputId(audioInputId) {
			if (!this.initialized) {
				return
			}

			this.updateAudioStream()
		},

		videoInputId(videoInputId) {
			if (!this.initialized) {
				return
			}

			this.updateVideoStream()
		},
	},

	computed: {
		devices() {
			return mediaDevicesManager.attributes.devices
		},

		audioInputId: {
			get() {
				return mediaDevicesManager.attributes.audioInputId
			},
			set(value) {
				mediaDevicesManager.set('audioInputId', value)
			},
		},

		videoInputId: {
			get() {
				return mediaDevicesManager.attributes.videoInputId
			},
			set(value) {
				mediaDevicesManager.set('videoInputId', value)
			},
		},

		audioStreamInputId() {
			if (!this.audioStream) {
				return null
			}

			const audioTracks = this.audioStream.getAudioTracks()
			if (audioTracks.length < 1) {
				return null
			}

			return audioTracks[0].getSettings().deviceId
		},

		videoStreamInputId() {
			if (!this.videoStream) {
				return null
			}

			const videoTracks = this.videoStream.getVideoTracks()
			if (videoTracks.length < 1) {
				return null
			}

			return videoTracks[0].getSettings().deviceId
		},

		audioPreviewAvailable() {
			return !!this.audioInputId && !!this.audioStream
		},

		audioStreamErrorMessage() {
			if (!this.audioStreamError) {
				return null
			}

			if (this.audioStreamError.name === 'NotSupportedError' && !window.RTCPeerConnection) {
				return t('spreed', 'Calls are not supported in your browser')
			}

			// In newer browser versions MediaDevicesManager is not supported in
			// insecure contexts; in older browser versions it is, but getting
			// the user media fails with "NotAllowedError".
			const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
			const isInsecureContextAccordingToErrorMessage = this.audioStreamError.message && this.audioStreamError.message.includes('Only secure origins')
			if ((this.audioStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.audioStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to microphone is only possible with HTTPS')
			}

			if (this.audioStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to microphone was denied')
			}

			return t('spreed', 'Error while accessing microphone')
		},

		videoStreamErrorMessage() {
			if (!this.videoStreamError) {
				return null
			}

			if (this.videoStreamError.name === 'NotSupportedError' && !window.RTCPeerConnection) {
				return t('spreed', 'Calls are not supported in your browser')
			}

			// In newer browser versions MediaDevicesManager is not supported in
			// insecure contexts; in older browser versions it is, but getting
			// the user media fails with "NotAllowedError".
			const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
			const isInsecureContextAccordingToErrorMessage = this.videoStreamError.message && this.videoStreamError.message.includes('Only secure origins')
			if ((this.videoStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.videoStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to camera is only possible with HTTPS')
			}

			if (this.videoStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to camera was denied')
			}

			return t('spreed', 'Error while accessing camera')
		},

		videoPreviewAvailable() {
			return !!this.videoInputId && !!this.videoStream
		},
	},
}
