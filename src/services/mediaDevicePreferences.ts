/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

type InputId = string | undefined | null
type InputListUpdated = MediaDeviceInfo[] | null
type InputLists = {
	newAudioInputList: InputListUpdated,
	newVideoInputList: InputListUpdated,
}
type Attributes = {
	devices: MediaDeviceInfo[],
	audioInputId: InputId,
	videoInputId: InputId,
}

enum DeviceKind {
	AudioInput = 'audioinput',
	VideoInput = 'videoinput',
	AudioOutput = 'audiooutput',
}

/**
 * List all registered devices in order of their preferences
 * Show whether device is currently unplugged or selected, if information is available
 *
 * @param attributes MediaDeviceManager attributes
 * @param audioInputList list of registered audio devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 * @return {string} preference list in readable format
 */
function listMediaDevices(attributes: Attributes, audioInputList: MediaDeviceInfo[], videoInputList: MediaDeviceInfo[]): string {
	const availableDevices = attributes.devices.map(device => device.deviceId)

	const getDeviceString = (device: MediaDeviceInfo, index: number) => {
		const isUnplugged = !availableDevices.includes(device.deviceId) ? ' (unplugged)' : ''
		const isSelected = () => {
			if (device.kind === DeviceKind.AudioInput) {
				return device.deviceId === attributes.audioInputId ? ' (selected)' : ''
			} else if (device.kind === DeviceKind.VideoInput) {
				return device.deviceId === attributes.videoInputId ? ' (selected)' : ''
			}
		}
		return `    ${index + 1}. ${device.label} | ${device.deviceId}` + isUnplugged + isSelected()
	}

	return (`Media devices:
  Audio input:
${audioInputList.map(getDeviceString).join('\n')}

  Video input:
${videoInputList.map(getDeviceString).join('\n')}
`)
}

/**
 * Get the first available device from the preference list.
 *
 * Returns id of device from the list / provided fallback id / 'default' id
 *
 * @param devices list of available devices
 * @param inputList list of registered audio/video devices in order of preference
 * @param [fallbackId] id of currently selected input
 * @return first available (plugged) device id or fallback
 */
function getFirstAvailableMediaDevice(devices: MediaDeviceInfo[], inputList: MediaDeviceInfo[], fallbackId?: string): string | undefined {
	const availableDevices = devices.map(device => device.deviceId).filter(id => id !== 'default')
	return inputList.find(device => availableDevices.includes(device.deviceId))?.deviceId ?? fallbackId
}

/**
 * Modify devices list.
 *
 * @param device device
 * @param devicesList list of registered devices in order of preference
 * @return {MediaDeviceInfo[]} updated devices list
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
 * @param devices list of available devices
 * @param inputList list of registered audio/video devices in order of preference
 * @param inputId id of currently selected input
 * @return {InputListUpdated} updated devices list (null, if it has not been changed)
 */
function promoteMediaDevice(devices: MediaDeviceInfo[], inputList: MediaDeviceInfo[], inputId: InputId): InputListUpdated {
	const newInputList = inputList.slice()

	// Get the index of the first plugged device
	const availableDevices = devices.map(device => device.deviceId).filter(id => id !== 'default')
	const firstPluggedIndex = newInputList.findIndex(device => availableDevices.includes(device.deviceId))
	const insertPosition = firstPluggedIndex === -1 ? newInputList.length : firstPluggedIndex

	// Get the index of the currently selected device
	const currentDevicePosition = newInputList.findIndex(device => device.deviceId === inputId)

	if (currentDevicePosition === insertPosition) {
		// preferences list is unchanged
		return null
	}

	let deviceToPromote = null
	if (currentDevicePosition === -1 && inputId !== 'default' && inputId !== null) {
		// If device was not registered in preferences list, get it from devices list
		deviceToPromote = devices.find(device => device.deviceId === inputId)
	} else if (currentDevicePosition > 0) {
		// Otherwise extract it from preferences list
		deviceToPromote = newInputList.splice(currentDevicePosition, 1)[0]
	}

	if (deviceToPromote) {
		// Put the device at the new position
		newInputList.splice(insertPosition, 0, deviceToPromote)
		return newInputList
	} else {
		return null
	}
}

/**
 * Populate devices preferences. If device has not yet been registered in preference list, it will be added.
 *
 * Returns changed preference lists for audio / video devices (null, if it hasn't been changed)
 *
 * @param devices list of available devices
 * @param audioInputList list of registered audio devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 * @return {InputLists} object with updated devices lists (null, if they have not been changed)
 */
function populateMediaDevicesPreferences(devices: MediaDeviceInfo[], audioInputList: MediaDeviceInfo[], videoInputList: MediaDeviceInfo[]): InputLists {
	let newAudioInputList = null
	let newVideoInputList = null

	for (const device of devices) {
		if (device.deviceId && device.kind === DeviceKind.AudioInput) {
			// Add to the list of known devices
			if (device.deviceId !== 'default' && !audioInputList.some(input => input.deviceId === device.deviceId)) {
				newAudioInputList = registerNewMediaDevice(device, newAudioInputList ?? audioInputList)
			}
		} else if (device.deviceId && device.kind === DeviceKind.VideoInput) {
			// Add to the list of known devices
			if (device.deviceId !== 'default' && !videoInputList.some(input => input.deviceId === device.deviceId)) {
				newVideoInputList = registerNewMediaDevice(device, newVideoInputList ?? videoInputList)
			}
		}
	}

	return {
		newAudioInputList,
		newVideoInputList,
	}
}

/**
 * Update devices preferences. Assuming that preferred devices were selected, should be called after applying the selection:
 * so either with joining the call or changing device during the call
 *
 * Returns changed preference lists for audio / video devices (null, if it hasn't been changed)
 *
 * @param devices list of available devices
 * @param audioInputId id of currently selected audio input
 * @param videoInputId id of currently selected video input
 * @param audioInputList list of registered audio devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 * @return {InputLists} object with updated devices lists (null, if they have not been changed)
 */
function updateMediaDevicesPreferences(devices: MediaDeviceInfo[], audioInputId: InputId, videoInputId: InputId, audioInputList: MediaDeviceInfo[], videoInputList: MediaDeviceInfo[]): InputLists {
	return {
		newAudioInputList: promoteMediaDevice(devices, audioInputList, audioInputId),
		newVideoInputList: promoteMediaDevice(devices, videoInputList, videoInputId),
	}
}

export {
	getFirstAvailableMediaDevice,
	listMediaDevices,
	populateMediaDevicesPreferences,
	updateMediaDevicesPreferences,
}
