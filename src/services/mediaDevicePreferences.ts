/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type InputId = string | undefined | null
type InputListUpdated = MediaDeviceInfo[] | null
type InputLists = {
	newAudioInputList: InputListUpdated
	newAudioOutputList: InputListUpdated
	newVideoInputList: InputListUpdated
}
type Attributes = {
	devices: MediaDeviceInfo[]
	audioInputId: InputId
	audioOutputId: InputId
	videoInputId: InputId
}

enum DeviceKind {
	AudioInput = 'audioinput',
	VideoInput = 'videoinput',
	AudioOutput = 'audiooutput',
}

type PromotePayload = {
	kind: DeviceKind
	devices: MediaDeviceInfo[]
	inputList: MediaDeviceInfo[]
	inputId: InputId
}

/**
 * List all registered devices in order of their preferences
 * Show whether device is currently unplugged or selected, if information is available
 *
 * @param attributes MediaDeviceManager attributes
 * @param audioInputList list of registered audio devices in order of preference
 * @param audioOutputList list of registered speaker devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 * @return preference list in readable format
 */
function listMediaDevices(
	attributes: Attributes,
	audioInputList: MediaDeviceInfo[],
	audioOutputList: MediaDeviceInfo[],
	videoInputList: MediaDeviceInfo[],
): string {
	const availableDevices = attributes.devices.map((device) => device.deviceId)

	const getDeviceString = (device: MediaDeviceInfo, index: number) => {
		const isUnplugged = !availableDevices.includes(device.deviceId) ? ' (unplugged)' : ''
		const isSelected = () => {
			if (device.kind === DeviceKind.AudioInput) {
				return device.deviceId === attributes.audioInputId ? ' (selected)' : ''
			} else if (device.kind === DeviceKind.AudioOutput) {
				return device.deviceId === attributes.audioOutputId ? ' (selected)' : ''
			} else if (device.kind === DeviceKind.VideoInput) {
				return device.deviceId === attributes.videoInputId ? ' (selected)' : ''
			}
		}
		return `    ${index + 1}. ${device.label} | ${device.deviceId}` + isUnplugged + isSelected()
	}

	return (`Media devices:
  Audio input:
${audioInputList.map(getDeviceString).join('\n')}

  Audio output:
${audioOutputList.map(getDeviceString).join('\n')}

  Video input:
${videoInputList.map(getDeviceString).join('\n')}
`)
}

/**
 * Get the first available device from the preference list.
 *
 * Returns id of device from the list
 *
 * @param devices list of available devices
 * @param inputList list of registered audio/video devices in order of preference
 * @return first available (plugged) device id
 */
function getFirstAvailableMediaDevice(devices: MediaDeviceInfo[], inputList: MediaDeviceInfo[]): string | undefined {
	return inputList.find((device) => devices.some((d) => d.kind === device.kind && d.deviceId === device.deviceId))?.deviceId
}

/**
 * Modify devices list.
 *
 * @param device device
 * @param devicesList list of registered devices in order of preference
 * @return updated devices list
 */
function registerNewMediaDevice(device: MediaDeviceInfo, devicesList: MediaDeviceInfo[]): MediaDeviceInfo[] {
	console.debug('Registering new device:', device)
	return [...devicesList, device]
}

/**
 * Promote device in the preference list.
 * Regardless if new unknown or registered device was selected, we promote it in the list:
 * - if first item is plugged, then we prefer device out of all options and put it on the first place;
 * - if first item is unplugged, then we don't consider it, and compare with the next in the list;
 * - if second item is unplugged, compare with next in the list;
 * - ...
 * - if N-th item is plugged, then we prefer device to it and put it on the Nth place.
 *
 * Returns changed preference lists for audio / video devices (null, if it hasn't been changed)
 *
 * @param data the wrapping object
 * @param data.kind kind of device
 * @param data.devices list of available devices
 * @param data.inputList list of registered audio/video devices in order of preference
 * @param data.inputId id of currently selected input
 * @return updated devices list (null, if it has not been changed)
 */
function promoteMediaDevice({ kind, devices, inputList, inputId }: PromotePayload): InputListUpdated {
	if (!inputId) {
		return null
	}

	// Get the index of the first plugged device
	const availableDevices = devices.filter((device) => device.kind === kind)
	const deviceToPromote = availableDevices.find((device) => device.deviceId === inputId)
	if (!deviceToPromote) {
		return null
	}

	const firstPluggedIndex = inputList.findIndex((device) => availableDevices.some((d) => d.deviceId === device.deviceId))
	const insertPosition = firstPluggedIndex === -1 ? inputList.length : firstPluggedIndex

	// Get the index of the currently selected device
	const currentDevicePosition = inputList.findIndex((device) => device.deviceId === inputId)

	if (currentDevicePosition === insertPosition) {
		// preferences list is unchanged
		return null
	}

	const newInputList = inputList.slice()

	if (currentDevicePosition > 0) {
		// Extract promoted device it from preferences list
		newInputList.splice(currentDevicePosition, 1)
	}

	newInputList.splice(insertPosition, 0, deviceToPromote)
	return newInputList
}

/**
 * Populate devices preferences. If device has not yet been registered in preference list, it will be added.
 *
 * Returns changed preference lists for audio / video devices (null, if it hasn't been changed)
 *
 * @param devices list of available devices
 * @param audioInputList list of registered audio devices in order of preference
 * @param audioOutputList list of registered speaker devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 * @return object with updated devices lists (null, if they have not been changed)
 */
function populateMediaDevicesPreferences(
	devices: MediaDeviceInfo[],
	audioInputList: MediaDeviceInfo[],
	audioOutputList: MediaDeviceInfo[],
	videoInputList: MediaDeviceInfo[],
): InputLists {
	let newAudioInputList = null
	let newAudioOutputList = null
	let newVideoInputList = null

	for (const device of devices) {
		if (device.deviceId && device.kind === DeviceKind.AudioInput) {
			// Add to the list of known devices
			if (!audioInputList.some((input) => input.deviceId === device.deviceId)) {
				newAudioInputList = registerNewMediaDevice(device, newAudioInputList ?? audioInputList)
			}
		} else if (device.deviceId && device.kind === DeviceKind.AudioOutput) {
			// Add to the list of known devices
			if (!audioOutputList.some((input) => input.deviceId === device.deviceId)) {
				newAudioOutputList = registerNewMediaDevice(device, newAudioOutputList ?? audioOutputList)
			}
		} else if (device.deviceId && device.kind === DeviceKind.VideoInput) {
			// Add to the list of known devices
			if (!videoInputList.some((input) => input.deviceId === device.deviceId)) {
				newVideoInputList = registerNewMediaDevice(device, newVideoInputList ?? videoInputList)
			}
		}
	}

	return {
		newAudioInputList,
		newAudioOutputList,
		newVideoInputList,
	}
}

export {
	getFirstAvailableMediaDevice,
	listMediaDevices,
	populateMediaDevicesPreferences,
	promoteMediaDevice,
}
