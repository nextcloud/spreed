/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import createHark from 'hark'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useSoundsStore } from '../stores/sounds.js'
import attachMediaStream from '../utils/attachmediastream.js'
import TrackToStream from '../utils/media/pipeline/TrackToStream.js'
import VirtualBackground from '../utils/media/pipeline/VirtualBackground.js'
import { callParticipantsAudioPlayer, mediaDevicesManager } from '../utils/webrtc/index.js'

let subscribersCount = 0
let videoElement = ref(null)

/**
 * Check whether the user joined the call of the current token in this PHP session or not
 *
 * @return {{[key:string]: Function|import('vue').Ref|import('vue').ComputedRef}}
 */
export const useDevices = createSharedComposable(function() {
	// Internal variables
	let initialized = false
	let pendingGetUserMediaAudioCount = 0
	let pendingGetUserMediaVideoCount = 0

	const soundsStore = useSoundsStore()

	const hark = ref(null)
	const videoTrackToStream = ref(null)

	window.OCA.Talk.mediaDevicesManager = mediaDevicesManager

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

	const audioOutputId = computed({
		get() {
			return mediaDevicesManager.attributes.audioOutputId
		},
		set(value) {
			mediaDevicesManager.set('audioOutputId', value)
		},
	})

	const audioOutputSupported = computed(() => {
		return mediaDevicesManager.isAudioOutputSelectSupported
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

	watch(audioOutputId, (deviceId) => {
		if (initialized && deviceId !== undefined) {
			soundsStore.setGeneralAudioOutput(deviceId)

			if (callParticipantsAudioPlayer) {
				callParticipantsAudioPlayer.setGeneralAudioOutput(deviceId)
			}
		}
	})

	watch(videoInputId, () => {
		if (initialized) {
			updateVideoStream()
		}
	})

	/**
	 * Called for shared composable when all subscribers are unmounted (onScopeDispose)
	 */
	onBeforeUnmount(() => {
		stopDevices()
	})

	/**
	 * Subscribe element to device changes
	 * If at least one component instance is subscribed, initialize devices
	 *
	 * @public
	 */
	function subscribeToDevices() {
		if (subscribersCount === 0) {
			initializeDevices()
		}
		subscribersCount++
	}

	/**
	 * Unsubscribe element from device changes
	 * If no more component instances are subscribed, stop devices
	 *
	 * @public
	 */
	function unsubscribeFromDevices() {
		if (subscribersCount === 0) {
			console.error('Attempt to unsubscribe from devices when no subscribers')
			return
		}

		subscribersCount--
		if (subscribersCount === 0) {
			stopDevices()
		}
	}

	/**
	 * Start tracking device events (audio and video)
	 *
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

		virtualBackground.value = new VirtualBackground()
		// The virtual background should be enabled and disabled as needed by components
		virtualBackground.value.setEnabled(false)

		videoTrackToStream.value = new TrackToStream()
		videoTrackToStream.value.addInputTrackSlot('video')

		virtualBackground.value.connectTrackSink('default', videoTrackToStream.value, 'video')

		mediaDevicesManager.enableDeviceEvents()
		updateAudioStream()
		updateVideoStream()

		if (mediaDevicesManager.attributes.audioOutputId !== undefined) {
			soundsStore.setGeneralAudioOutput(mediaDevicesManager.attributes.audioOutputId)
		}
	}

	/**
	 * Force enumerate devices (audio and video)
	 *
	 * @public
	 */
	function updateDevices() {
		mediaDevicesManager._updateDevices()
	}

	/**
	 * Update preference counters for devices (audio and video)
	 *
	 * @param {string} kind the kind of the input stream to update ('audioinput', 'audiooutput' or 'videoinput')
	 * @public
	 */
	function updatePreferences(kind) {
		mediaDevicesManager.updatePreferences(kind)
	}

	/**
	 * Stop tracking device events (audio and video)
	 *
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

		videoTrackToStream.value = null

		virtualBackground.value._stopEffect()
		virtualBackground.value = null

		videoElement.value = null
	}

	/**
	 * Set audio stream, start listening speaking events
	 *
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

		audioStream.value.getTracks().forEach((track) => track.stop())
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
	 * Reset an update counter and update audio stream if needed
	 *
	 * @param {import('vue').Ref} video element ref to attach track to
	 */
	function registerVideoElement(video) {
		videoElement = video
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
			.then((stream) => {
				if (!initialized) {
					// The promise was fulfilled once the stream is no
					// longer needed, so just discard it.
					stream.getTracks().forEach((track) => track.stop())
				} else {
					setAudioStream(stream)
				}
			})
			.catch((error) => {
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
	 *
	 * @param {MediaStream|null} stream video stream
	 */
	function setVideoStream(stream) {
		videoStream.value = stream
		// check if <video> element is mounted
		if (!videoElement.value) {
			return
		}
		if (!stream) {
			virtualBackground.value._setInputTrack('default', null)
			return
		}

		virtualBackground.value._setInputTrack('default', videoStream.value.getVideoTracks()[0])

		const options = { autoplay: true, mirror: true, muted: true }
		attachMediaStream(videoTrackToStream.value.getStream(), videoElement.value, options)
	}

	/**
	 * Stop and remove video stream
	 */
	function stopVideoStream() {
		virtualBackground.value._setInputTrack('default', null)
		if (!videoStream.value) {
			return
		}

		videoStream.value.getTracks().forEach((track) => track.stop())
		videoStream.value = null
		videoStreamError.value = null

		// check if <video> element is mounted
		if (videoElement.value) {
			videoElement.value.srcObject = null
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
			.then((stream) => {
				if (!initialized) {
					// The promise was fulfilled once the stream is no
					// longer needed, so just discard it.
					stream.getTracks().forEach((track) => track.stop())
				} else {
					setVideoStream(stream)
				}
			})
			.catch((error) => {
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
		audioOutputId,
		videoInputId,
		audioOutputSupported,
		subscribeToDevices,
		unsubscribeFromDevices,
		// MediaDevicesPreview only
		audioStream,
		audioStreamError,
		videoStream,
		videoStreamError,
		// MediaSettings only
		updatePreferences,
		virtualBackground,
		registerVideoElement,
	}
})
