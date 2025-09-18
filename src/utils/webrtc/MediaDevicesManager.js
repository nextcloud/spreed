/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { reactive } from 'vue'
import BrowserStorage from '../../services/BrowserStorage.js'
import {
	getFirstAvailableMediaDevice,
	listMediaDevices,
	populateMediaDevicesPreferences,
	promoteMediaDevice,
} from '../../services/mediaDevicePreferences.ts'
import EmitterMixin from '../EmitterMixin.js'

/**
 * Special string to set null device ids in local storage (as only strings are
 * allowed).
 */
const LOCAL_STORAGE_NULL_DEVICE_ID = 'local-storage-null-device-id'

/**
 * Wrapper for MediaDevices to simplify its use.
 *
 * The MediaDevicesManager keeps an updated list of devices that can be accessed
 * from "attributes.devices". Clients of this class must call
 * "enableDeviceEvents()" to start keeping track of the devices, and
 * "disableDeviceEvents()" once it is no longer needed. Eventually there must be
 * one call to "disableDeviceEvents()" for each call to "enableDeviceEvents()",
 * but several clients can be active at the same time.
 *
 * Each element of "attributes.devices" is an object with the following fields:
 * - deviceId: the unique identifier for the device
 * - groupId: two or more devices have the same groupId if they belong to the
 *   same physical device
 * - kind: either "audioinput", "videoinput" or "audiooutput"
 * - label: a human readable identifier for the device
 * - fallbackLabel: a generated label if the actual label is empty
 *
 * Note that the list may not contain some kind of devices due to browser
 * limitations (for example, currently Firefox does not list all "audiooutput"
 * devices, but only input-output pairs, like headsets).
 *
 * In some browsers if persistent media permissions have not been granted and a
 * MediaStream is not active the list may contain at most one device of each
 * kind, and all of them with empty attributes except for the kind.
 *
 * In other browsers just the label may not be available if persistent media
 * permissions have not been granted and a MediaStream has not been active. In
 * those cases the fallback label can be used instead.
 *
 * "attributes.audioInputId" and "attributes.videoInputId" define the devices
 * that will be used when calling "getUserMedia(constraints)". Clients of this
 * class must modify them using "set('audioInputId', value)" and
 * "set('videoInputId', value)" to ensure that change events are triggered.
 * However, note that change events are not triggered when the devices are
 * modified.
 *
 * "attributes.audioOutputId" define the devices that will be used
 * when calling "AudioElement.setSinkId()".
 *
 * The selected devices will be automatically cleared if they are no longer
 * available, and they will be restored once they are again available
 * (immediately if events are enabled, or otherwise the next time that devices
 * are got). When no device of certain kind is selected and there are other
 * devices of that kind the selected device will fall back to the first one
 * found, or to the one with the "default" id (if any). It is possible to
 * explicitly disable devices of certain kind by setting xxxInputId to "null"
 * (in that case the fallback devices will not be taken into account).
 */
export default function MediaDevicesManager() {
	this._superEmitterMixin()

	this.attributes = reactive({
		devices: [],

		audioInputId: undefined,
		audioOutputId: undefined,
		videoInputId: undefined,
	})

	/**
	 * Returns whether selecting of audio output device is supported or not.
	 *
	 * Note that there are some browser limitations:
	 * Chrome:
	 * - supports AudioContext#setSinkId, but does not work with participant nodes (experimental feature, do not consider atm)
	 * Firefox:
	 * - does not list all "audiooutput" devices: https://bugzilla.mozilla.org/show_bug.cgi?id=1868750
	 * - does not support AudioContext#setSinkId (experimental feature, do not consider atm)
	 * - supports navigator.mediaDevices.selectAudioOutput() (experimental feature, do not consider atm)
	 * Safari:
	 * - does not support audio output selection: https://bugs.webkit.org/show_bug.cgi?id=216641
	 * - does not support AudioContext#setSinkId
	 *
	 * @return {boolean} true if supported, false otherwise.
	 */
	this.isAudioOutputSelectSupported = !!(new Audio().setSinkId)

	this._enabledCount = 0

	this._knownDevices = {}

	const audioInputPreferences = BrowserStorage.getItem('audioInputPreferences')
	this._preferenceAudioInputList = audioInputPreferences !== null ? JSON.parse(audioInputPreferences) : []

	const audioOutputPreferences = BrowserStorage.getItem('audioOutputPreferences')
	this._preferenceAudioOutputList = audioOutputPreferences !== null ? JSON.parse(audioOutputPreferences) : []

	const videoInputPreferences = BrowserStorage.getItem('videoInputPreferences')
	this._preferenceVideoInputList = videoInputPreferences !== null ? JSON.parse(videoInputPreferences) : []

	this._tracks = []

	this._updateDevicesBound = this._updateDevices.bind(this)

	this._pendingEnumerateDevicesPromise = null

	if (BrowserStorage.getItem('audioInputId') === LOCAL_STORAGE_NULL_DEVICE_ID) {
		this.attributes.audioInputId = null
	}
	if (BrowserStorage.getItem('audioOutputId') === LOCAL_STORAGE_NULL_DEVICE_ID) {
		this.attributes.audioOutputId = null
	}
	if (BrowserStorage.getItem('videoInputId') === LOCAL_STORAGE_NULL_DEVICE_ID) {
		this.attributes.videoInputId = null
	}
}
MediaDevicesManager.prototype = {

	get(key) {
		return this.attributes[key]
	},

	set(key, value) {
		this.attributes[key] = value

		this._trigger('change:' + key, [value])

		this._storeDeviceId(key, value)

		console.debug('Storing device selection in the browser storage: ', key, value)
	},

	_storeDeviceId(key, value) {
		if (!['audioInputId', 'audioOutputId', 'videoInputId'].includes(key)) {
			return
		}

		if (value === null) {
			value = LOCAL_STORAGE_NULL_DEVICE_ID
		}

		if (value) {
			BrowserStorage.setItem(key, value)
		} else {
			BrowserStorage.removeItem(key)
		}
	},

	/**
	 * Returns whether getting user media and enumerating media devices is
	 * supported or not.
	 *
	 * Note that even if false is returned the MediaDevices interface could be
	 * technically supported by the browser but not available due to the page
	 * being loaded in an insecure context.
	 *
	 * @return {boolean} true if MediaDevices interface is supported, false
	 *          otherwise.
	 */
	isSupported() {
		return navigator && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && navigator.mediaDevices.enumerateDevices
	},

	enableDeviceEvents() {
		if (!this.isSupported()) {
			return
		}

		this._enabledCount++

		this._updateDevices()

		navigator.mediaDevices.addEventListener('devicechange', this._updateDevicesBound)
	},

	disableDeviceEvents() {
		if (!this.isSupported()) {
			return
		}

		this._enabledCount--

		if (!this._enabledCount) {
			navigator.mediaDevices.removeEventListener('devicechange', this._updateDevicesBound)
		}
	},

	_updateDevices() {
		this._pendingEnumerateDevicesPromise = navigator.mediaDevices.enumerateDevices().then((devices) => {
			const previousAudioInputId = this.attributes.audioInputId
			const previousAudioOutputId = this.attributes.audioOutputId
			const previousVideoInputId = this.attributes.videoInputId
			const previousFirstAvailableAudioInputId = getFirstAvailableMediaDevice(this.attributes.devices, this._preferenceAudioInputList)
			const previousFirstAvailableAudioOutputId = getFirstAvailableMediaDevice(this.attributes.devices, this._preferenceAudioOutputList)
			const previousFirstAvailableVideoInputId = getFirstAvailableMediaDevice(this.attributes.devices, this._preferenceVideoInputList)

			const removedDevices = this.attributes.devices.filter((oldDevice) => !devices.find((device) => oldDevice.deviceId === device.deviceId && oldDevice.kind === device.kind))
			removedDevices.forEach((removedDevice) => {
				this._removeDevice(removedDevice)
			})

			devices.forEach((device) => {
				this._updateOrAddDevice(device)
			})

			this._populatePreferences(devices)

			// Selecting preferred device in case it was removed/unplugged, or it is a first initialization after reload,
			// or we add/plug preferred device and overwriting automatic selection
			// If the preference list is empty the default device falls back to
			// the first device of that kind found. This can happen, for
			// example, when no permissions were given yet. In that case,
			// according to the spec, a single device of each kind (if at least
			// one device of that kind is available) with an empty deviceId is
			// returned, which will not be registered in the preference list.
			let deviceIdChanged = false
			if (this.attributes.audioInputId === undefined || this.attributes.audioInputId === previousFirstAvailableAudioInputId) {
				this.attributes.audioInputId = getFirstAvailableMediaDevice(devices, this._preferenceAudioInputList) || devices.find((device) => device.kind === 'audioinput')?.deviceId
				deviceIdChanged = true
			}
			if (this.attributes.audioOutputId === undefined || this.attributes.audioOutputId === previousFirstAvailableAudioOutputId) {
				this.attributes.audioOutputId = getFirstAvailableMediaDevice(devices, this._preferenceAudioOutputList) || devices.find((device) => device.kind === 'audiooutput')?.deviceId
				deviceIdChanged = true
			}
			if (this.attributes.videoInputId === undefined || this.attributes.videoInputId === previousFirstAvailableVideoInputId) {
				this.attributes.videoInputId = getFirstAvailableMediaDevice(devices, this._preferenceVideoInputList) || devices.find((device) => device.kind === 'videoinput')?.deviceId
				deviceIdChanged = true
			}

			if (deviceIdChanged) {
				console.debug(listMediaDevices(this.attributes, this._preferenceAudioInputList, this._preferenceAudioOutputList, this._preferenceVideoInputList))
			}

			// Trigger change events after all the devices are processed to
			// prevent change events for intermediate states.
			if (previousAudioInputId !== this.attributes.audioInputId) {
				this._trigger('change:audioInputId', [this.attributes.audioInputId])
			}
			if (previousAudioOutputId !== this.attributes.audioOutputId) {
				this._trigger('change:audioOutputId', [this.attributes.audioOutputId])
			}
			if (previousVideoInputId !== this.attributes.videoInputId) {
				this._trigger('change:videoInputId', [this.attributes.videoInputId])
			}

			this._pendingEnumerateDevicesPromise = null
		}).catch(function(error) {
			console.error('Could not update known media devices: ' + error.name + ': ' + error.message)

			this._pendingEnumerateDevicesPromise = null
		})
	},

	_populatePreferences(devices) {
		const { newAudioInputList, newAudioOutputList, newVideoInputList } = populateMediaDevicesPreferences(
			devices,
			this._preferenceAudioInputList,
			this._preferenceAudioOutputList,
			this._preferenceVideoInputList,
		)

		if (newAudioInputList) {
			this._preferenceAudioInputList = newAudioInputList
			BrowserStorage.setItem('audioInputPreferences', JSON.stringify(this._preferenceAudioInputList))
		}
		if (newAudioOutputList) {
			this._preferenceAudioOutputList = newAudioOutputList
			BrowserStorage.setItem('audioOutputPreferences', JSON.stringify(this._preferenceAudioOutputList))
		}
		if (newVideoInputList) {
			this._preferenceVideoInputList = newVideoInputList
			BrowserStorage.setItem('videoInputPreferences', JSON.stringify(this._preferenceVideoInputList))
		}
	},

	updatePreferences(kind) {
		if (kind === 'audioinput') {
			const newAudioInputList = promoteMediaDevice({
				kind,
				devices: this.attributes.devices,
				inputList: this._preferenceAudioInputList,
				inputId: this.attributes.audioInputId,
			})

			if (newAudioInputList) {
				this._preferenceAudioInputList = newAudioInputList
				BrowserStorage.setItem('audioInputPreferences', JSON.stringify(newAudioInputList))
			}
		} else if (kind === 'audiooutput') {
			const newAudioOutputList = promoteMediaDevice({
				kind,
				devices: this.attributes.devices,
				inputList: this._preferenceAudioOutputList,
				inputId: this.attributes.audioOutputId,
			})

			if (newAudioOutputList) {
				this._preferenceAudioOutputList = newAudioOutputList
				BrowserStorage.setItem('audioOutputPreferences', JSON.stringify(newAudioOutputList))
			}
			if (!BrowserStorage.getItem('audioOutputDevicePreferred')) {
				BrowserStorage.setItem('audioOutputDevicePreferred', true)
			}
		} else if (kind === 'videoinput') {
			const newVideoInputList = promoteMediaDevice({
				kind,
				devices: this.attributes.devices,
				inputList: this._preferenceVideoInputList,
				inputId: this.attributes.videoInputId,
			})

			if (newVideoInputList) {
				this._preferenceVideoInputList = newVideoInputList
				BrowserStorage.setItem('videoInputPreferences', JSON.stringify(newVideoInputList))
			}
		}
	},

	/**
	 * List all registered devices in order of their preferences
	 * Show whether device is currently unplugged or selected, if information is available
	 */
	listDevices() {
		if (this.attributes.devices.length) {
			console.info(listMediaDevices(this.attributes, this._preferenceAudioInputList, this._preferenceAudioOutputList, this._preferenceVideoInputList))
		} else {
			navigator.mediaDevices.enumerateDevices().then((devices) => {
				console.info(listMediaDevices(
					{
						devices,
						audioInputId: this.attributes.audioInputId,
						audioOutputId: this.attributes.audioOutputId,
						videoInputId: this.attributes.videoInputId,
					},
					this._preferenceAudioInputList,
					this._preferenceAudioOutputList,
					this._preferenceVideoInputList,
				))
			})
		}
	},

	_removeDevice(removedDevice) {
		const removedDeviceIndex = this.attributes.devices.findIndex((oldDevice) => oldDevice.deviceId === removedDevice.deviceId && oldDevice.kind === removedDevice.kind)
		if (removedDeviceIndex >= 0) {
			this.attributes.devices = this.attributes.devices.splice(removedDeviceIndex, 1)
		}
		if (removedDevice.kind === 'audioinput' && removedDevice.deviceId === this.attributes.audioInputId) {
			this.attributes.audioInputId = undefined
		} else if (removedDevice.kind === 'audiooutput' && removedDevice.deviceId === this.attributes.audioOutputId) {
			this.attributes.audioOutputId = undefined
		} else if (removedDevice.kind === 'videoinput' && removedDevice.deviceId === this.attributes.videoInputId) {
			this.attributes.videoInputId = undefined
		}
	},

	_updateOrAddDevice(updatedDevice) {
		const oldDevice = this.attributes.devices.find((oldDevice) => oldDevice.deviceId === updatedDevice.deviceId && oldDevice.kind === updatedDevice.kind)
		if (!oldDevice) {
			this._addDevice(updatedDevice)
			return
		}

		// Only update the label if it has a value, as it may have been
		// removed if there is currently no active stream.
		if (updatedDevice.label) {
			oldDevice.label = updatedDevice.label
		}

		// These should not have changed, but just in case
		oldDevice.groupId = updatedDevice.groupId
		oldDevice.kind = updatedDevice.kind
	},

	_addDevice(addedDevice) {
		// Copy the device to add, as its properties are read only and
		// thus they can not be updated later.
		const newDevice = {
			deviceId: addedDevice.deviceId,
			groupId: addedDevice.groupId,
			kind: addedDevice.kind,
			label: addedDevice.label,
		}

		const knownDevice = this._knownDevices[newDevice.kind + '-' + newDevice.deviceId]
		if (knownDevice) {
			newDevice.fallbackLabel = knownDevice.fallbackLabel
			// If the added device has a label keep it; otherwise use
			// the previously known one, if any.
			newDevice.label = newDevice.label ? newDevice.label : knownDevice.label
		} else {
			// Generate a fallback label to be used when the actual label is
			// not available.
			if (newDevice.deviceId === 'default' || newDevice.deviceId === '') {
				newDevice.fallbackLabel = t('spreed', 'Default')
			} else if (newDevice.kind === 'audioinput') {
				newDevice.fallbackLabel = t('spreed', 'Microphone {number}', { number: Object.values(this._knownDevices).filter((device) => device.kind === 'audioinput' && device.deviceId !== '').length + 1 })
			} else if (newDevice.kind === 'videoinput') {
				newDevice.fallbackLabel = t('spreed', 'Camera {number}', { number: Object.values(this._knownDevices).filter((device) => device.kind === 'videoinput' && device.deviceId !== '').length + 1 })
			} else if (newDevice.kind === 'audiooutput') {
				newDevice.fallbackLabel = t('spreed', 'Speaker {number}', { number: Object.values(this._knownDevices).filter((device) => device.kind === 'audioutput' && device.deviceId !== '').length + 1 })
			}
		}

		// Always refresh the known device with the latest values.
		this._knownDevices[newDevice.kind + '-' + newDevice.deviceId] = newDevice
		this.attributes.devices = [...this.attributes.devices, newDevice]
	},

	/**
	 * Wrapper for MediaDevices.getUserMedia to use the selected audio and video
	 * input devices.
	 *
	 * The selected audio and video input devices are used only if the
	 * constraints do not specify a device already. Otherwise the devices in the
	 * constraints are respected.
	 *
	 * For compatibility with older browsers "finally" should not be used on the
	 * returned Promise.
	 *
	 * @param {object} constraints the constraints specifying
	 *        the media to request.
	 * @return {Promise} resolved with a MediaStream object when successful, or
	 *          rejected with a DOMException in case of error.
	 */
	getUserMedia(constraints) {
		if (!this.isSupported()) {
			return new Promise((resolve, reject) => {
				reject(new DOMException('MediaDevicesManager is not supported', 'NotSupportedError'))
			})
		}

		if (!this._pendingEnumerateDevicesPromise) {
			return this._getUserMediaInternal(constraints)
		}

		return this._pendingEnumerateDevicesPromise.then(() => {
			return this._getUserMediaInternal(constraints)
		}).catch(() => {
			return this._getUserMediaInternal(constraints)
		})
	},

	_getUserMediaInternal(constraints) {
		if (constraints.audio && !constraints.audio.deviceId) {
			if (this.attributes.audioInputId) {
				if (!(constraints.audio instanceof Object)) {
					constraints.audio = {}
				}
				constraints.audio.deviceId = { exact: this.attributes.audioInputId }
			} else if (this.attributes.audioInputId === null) {
				constraints.audio = false
			}
		}

		if (constraints.video && !constraints.video.deviceId) {
			if (this.attributes.videoInputId) {
				if (!(constraints.video instanceof Object)) {
					constraints.video = {}
				}
				constraints.video.deviceId = { exact: this.attributes.videoInputId }
			} else if (this.attributes.videoInputId === null) {
				constraints.video = false
			}
		}

		this._stopIncompatibleTracks(constraints)

		return navigator.mediaDevices.getUserMedia(constraints).then((stream) => {
			this._registerStream(stream)

			// In Firefox the dialog to grant media permissions allows the user
			// to change the device to use, overriding the device that was
			// originally requested.
			this._updateSelectedDevicesFromGetUserMediaResult(stream)

			// The list of devices is always updated when a stream is started as
			// that is the only time at which the full device information is
			// guaranteed to be available.
			this._updateDevices()

			return stream
		}).catch((error) => {
			// The list of devices is also updated in case of failure, as even
			// if getting the stream failed the permissions may have been
			// permanently granted.
			this._updateDevices()

			throw error
		})
	},

	_stopIncompatibleTracks(constraints) {
		this._tracks.forEach((track) => {
			if (constraints.audio && constraints.audio.deviceId && track.kind === 'audio') {
				const constraintsAudioDeviceId = constraints.audio.deviceId.exact || constraints.audio.deviceId.ideal || constraints.audio.deviceId
				const settings = track.getSettings()
				if (settings && settings.deviceId !== constraintsAudioDeviceId) {
					track.stop()
				}
			}

			if (constraints.video && constraints.video.deviceId && track.kind === 'video') {
				const constraintsVideoDeviceId = constraints.video.deviceId.exact || constraints.video.deviceId.ideal || constraints.video.deviceId
				const settings = track.getSettings()
				if (settings && settings.deviceId !== constraintsVideoDeviceId) {
					track.stop()
				}
			}
		})
	},

	_registerStream(stream) {
		stream.getTracks().forEach((track) => {
			this._registerTrack(track)
		})
	},

	_registerTrack(track) {
		this._tracks.push(track)

		track.addEventListener('ended', () => {
			const index = this._tracks.indexOf(track)
			if (index >= 0) {
				this._tracks.splice(index, 1)
			}
		})

		track.addEventListener('cloned', (event) => {
			this._registerTrack(event.detail)
		})
	},

	_updateSelectedDevicesFromGetUserMediaResult(stream) {
		if (this.attributes.audioInputId) {
			const audioTracks = stream.getAudioTracks()
			const audioTrackSettings = audioTracks.length > 0 ? audioTracks[0].getSettings() : null
			if (audioTrackSettings && audioTrackSettings.deviceId && this.attributes.audioInputId !== audioTrackSettings.deviceId) {
				console.debug('Input audio device overridden in getUserMedia: Expected: ' + this.attributes.audioInputId + ' Found: ' + audioTrackSettings.deviceId)

				this.set('audioInputId', audioTrackSettings.deviceId)
			}
		}

		if (this.attributes.videoInputId) {
			const videoTracks = stream.getVideoTracks()
			const videoTrackSettings = videoTracks.length > 0 ? videoTracks[0].getSettings() : null
			if (videoTrackSettings && videoTrackSettings.deviceId && this.attributes.videoInputId !== videoTrackSettings.deviceId) {
				console.debug('Input video device overridden in getUserMedia: Expected: ' + this.attributes.videoInputId + ' Found: ' + videoTrackSettings.deviceId)

				this.set('videoInputId', videoTrackSettings.deviceId)
			}
		}
	},
}

EmitterMixin.apply(MediaDevicesManager.prototype)
