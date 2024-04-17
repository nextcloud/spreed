/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

import createHark from 'hark'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import attachMediaStream from '../utils/attachmediastream.js'
import TrackToStream from '../utils/media/pipeline/TrackToStream.js'
import VirtualBackground from '../utils/media/pipeline/VirtualBackground.js'
import { mediaDevicesManager as mediaDevicesManagerInstance } from '../utils/webrtc/index.js'

/**
 * Check whether the user joined the call of the current token in this PHP session or not
 *
 * @param {import('vue').Ref} video element ref to attach track to
 * @param {boolean} initializeOnMounted whether to initialize mixin or not
 * @return {{[key:string]: Function|import('vue').Ref|import('vue').ComputedRef}}
 */
export function useDevices(video, initializeOnMounted) {
	// Internal variables
	let initialized = false
	let pendingGetUserMediaAudioCount = 0
	let pendingGetUserMediaVideoCount = 0
	const hark = ref(null)
	const videoTrackToStream = ref(null)
	const mediaDevicesManager = reactive(mediaDevicesManagerInstance)

	window.OCA.Talk.mediaDevicesManager = mediaDevicesManagerInstance

	// Public refs
	const currentVolume = ref(-100)
	const currentThreshold = ref(-100)
	const virtualBackground = ref(null)
	const audioStream = ref(null)
	const audioStreamError = ref(null)
	const videoStream = ref(null)
	const videoStreamError = ref(null)

	const devices = computed(() => {
		return mediaDevicesManager.attributes.devices
	})

	const audioInputId = computed({
		get() {
			return mediaDevicesManager.attributes.audioInputId
		},
		set(value) {
			mediaDevicesManager.set('audioInputId', value)
		},
	})

	const audioPreviewAvailable = computed(() => {
		return !!audioInputId.value && !!audioStream.value
	})

	const audioStreamInputId = computed(() => {
		if (!audioStream.value) {
			return null
		}
		const audioTracks = audioStream.value.getAudioTracks()
		return audioTracks.length < 1 ? null : audioTracks[0].getSettings().deviceId
	})

	const videoInputId = computed({
		get() {
			return mediaDevicesManager.attributes.videoInputId
		},
		set(value) {
			mediaDevicesManager.set('videoInputId', value)
		},
	})

	const videoPreviewAvailable = computed(() => {
		return !!videoInputId.value && !!videoStream.value
	})

	const videoStreamInputId = computed(() => {
		if (!videoStream.value) {
			return null
		}
		const videoTracks = videoStream.value.getVideoTracks()
		return videoTracks.length < 1 ? null : videoTracks[0].getSettings().deviceId
	})

	watch(audioInputId, () => {
		if (initialized) {
			updateAudioStream()
		}
	})

	watch(videoInputId, () => {
		if (initialized) {
			updateVideoStream()
		}
	})

	onMounted(() => {
		virtualBackground.value = new VirtualBackground()
		// The virtual background should be enabled and disabled as needed by components
		virtualBackground.value.setEnabled(false)

		videoTrackToStream.value = new TrackToStream()
		videoTrackToStream.value.addInputTrackSlot('video')

		virtualBackground.value.connectTrackSink('default', videoTrackToStream.value, 'video')

		if (initializeOnMounted) {
			initializeDevices()
		}
	})

	onBeforeUnmount(() => {
		stopDevices()
	})

	/**
	 * Start tracking device events (audio and video)
	 * @public
	 */
	function initializeDevices() {
		// Check if has been initialized
		if (initialized) {
			return
		} else {
			initialized = true
		}

		if (!mediaDevicesManager.isSupported()) {
			// DOMException constructor is not supported in Internet Explorer,
			// so a plain object is used instead.
			audioStreamError.value = {
				message: 'MediaDevicesManager is not supported',
				name: 'NotSupportedError',
			}
			videoStreamError.value = {
				message: 'MediaDevicesManager is not supported',
				name: 'NotSupportedError',
			}
		}

		// Attempt to request permissions for camera and microphone
		mediaDevicesManager.getUserMedia({ audio: true, video: true })
			.then(stream => {
				stream.getTracks().forEach(track => track.stop())
			})
			.catch(error => {
				console.error('Error getting audio/video streams: ' + error.name + ': ' + error.message)
				audioStreamError.value = error
				videoStreamError.value = error
			})

		mediaDevicesManager.enableDeviceEvents()
		updateAudioStream()
		updateVideoStream()
	}

	/**
	 * Force enumerate devices (audio and video)
	 * @public
	 */
	function updateDevices() {
		mediaDevicesManager._updateDevices()
	}

	/**
	 * Update preference counters for devices (audio and video)
	 * @public
	 */
	function updatePreferences() {
		mediaDevicesManager.updatePreferences()
	}

	/**
	 * Stop tracking device events (audio and video)
	 * @public
	 */
	function stopDevices() {
		// Check if has been initialized
		if (!initialized) {
			return
		} else {
			initialized = false
		}

		stopAudioStream()
		stopVideoStream()
		mediaDevicesManager.disableDeviceEvents()
	}

	/**
	 * Set audio stream, start listening speaking events
	 * @param {MediaStream|null} stream audio stream
	 */
	function setAudioStream(stream) {
		audioStream.value = stream
		if (!stream) {
			return
		}

		hark.value = createHark(stream)
		hark.value.on('volume_change', (volume, volumeThreshold) => {
			currentVolume.value = volume
			currentThreshold.value = volumeThreshold
		})
	}

	/**
	 * Stop and remove audio stream
	 */
	function stopAudioStream() {
		if (!audioStream.value) {
			return
		}

		audioStream.value.getTracks().forEach(track => track.stop())
		audioStream.value = null
		audioStreamError.value = null

		if (hark.value) {
			hark.value.off('volume_change')
			hark.value.stop()
			hark.value = null
		}
	}

	/**
	 * Reset an update counter and update audio stream if needed
	 */
	function resetPendingGetUserMediaAudioCount() {
		const shouldUpdateAgain = pendingGetUserMediaAudioCount > 1
		pendingGetUserMediaAudioCount = 0
		if (shouldUpdateAgain) {
			updateAudioStream()
		}
	}

	/**
	 * Update audio stream
	 */
	function updateAudioStream() {
		if (!mediaDevicesManager.isSupported()) {
			return
		}
		if (audioStreamInputId.value && audioStreamInputId.value === audioInputId.value) {
			return
		}
		if (pendingGetUserMediaAudioCount) {
			pendingGetUserMediaAudioCount++
			return
		}
		// When the audio input device changes the previous stream must be
		// stopped before a new one is requested, as for example currently
		// Firefox does not support having two different audio input devices
		// active at the same time:
		// https://bugzilla.mozilla.org/show_bug.cgi?id=1468700
		stopAudioStream()

		if (audioInputId.value === null || audioInputId.value === undefined) {
			return
		}
		pendingGetUserMediaAudioCount = 1

		mediaDevicesManager.getUserMedia({ audio: true })
			.then(stream => {
				if (!initialized) {
					// The promise was fulfilled once the stream is no
					// longer needed, so just discard it.
					stream.getTracks().forEach(track => track.stop())
				} else {
					setAudioStream(stream)
				}
			})
			.catch(error => {
				console.error('Error getting audio stream: ' + error.name + ': ' + error.message)
				audioStreamError.value = error
				setAudioStream(null)
			})
			.finally(() => {
				resetPendingGetUserMediaAudioCount()
			})
	}

	/**
	 * Set video stream and virtual background, attach to <video> element
	 * @param {MediaStream|null} stream video stream
	 */
	function setVideoStream(stream) {
		videoStream.value = stream
		// check if <video> element is mounted
		if (!video.value) {
			return
		}
		if (!stream) {
			virtualBackground.value._setInputTrack('default', null)
			return
		}

		virtualBackground.value._setInputTrack('default', videoStream.value.getVideoTracks()[0])

		const options = { autoplay: true, mirror: true, muted: true }
		attachMediaStream(videoTrackToStream.value.getStream(), video.value, options)
	}

	/**
	 * Stop and remove video stream
	 */
	function stopVideoStream() {
		virtualBackground.value._setInputTrack('default', null)
		if (!videoStream.value) {
			return
		}

		videoStream.value.getTracks().forEach(track => track.stop())
		videoStream.value = null
		videoStreamError.value = null

		// check if <video> element is mounted
		if (video.value) {
			video.value.srcObject = null
		}
	}

	/**
	 * Reset an update counter and update video stream if needed
	 */
	function resetPendingGetUserMediaVideoCount() {
		const shouldUpdateAgain = pendingGetUserMediaVideoCount > 1
		pendingGetUserMediaVideoCount = 0
		if (shouldUpdateAgain) {
			updateVideoStream()
		}
	}

	/**
	 * Update video stream
	 */
	function updateVideoStream() {
		if (!mediaDevicesManager.isSupported()) {
			return
		}
		if (videoStreamInputId.value && videoStreamInputId.value === videoInputId.value) {
			return
		}
		if (pendingGetUserMediaVideoCount) {
			pendingGetUserMediaVideoCount++
			return
		}

		// Video stream is stopped too to avoid potential issues similar to
		// the audio ones (see "updateAudioStream").
		stopVideoStream()

		if (videoInputId.value === null || videoInputId.value === undefined) {
			return
		}

		pendingGetUserMediaVideoCount = 1

		mediaDevicesManager.getUserMedia({ video: true })
			.then(stream => {
				if (!initialized) {
					// The promise was fulfilled once the stream is no
					// longer needed, so just discard it.
					stream.getTracks().forEach(track => track.stop())
				} else {
					setVideoStream(stream)
				}
			})
			.catch(error => {
				console.error('Error getting video stream: ' + error.name + ': ' + error.message)
				videoStreamError.value = error
				setVideoStream(null)
			})
			.finally(() => {
				resetPendingGetUserMediaVideoCount()
			})
	}

	return {
		devices,
		updateDevices,
		currentVolume,
		currentThreshold,
		audioPreviewAvailable,
		videoPreviewAvailable,
		audioInputId,
		videoInputId,
		// MediaDevicesPreview only
		audioStream,
		audioStreamError,
		videoStream,
		videoStreamError,
		// MediaSettings only
		initializeDevices,
		updatePreferences,
		stopDevices,
		virtualBackground,
	}
}
