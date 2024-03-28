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

/**
 * List all registered devices in order of their preferences
 * Show whether device is currently unplugged or selected, if information is available
 *
 * @param devices list of available devices
 * @param audioInputId id of currently selected audio input
 * @param videoInputId id of currently selected video input
 * @param audioInputList list of registered audio devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 */
function listMediaDevices(devices: MediaDeviceInfo[], audioInputId: string, videoInputId: string, audioInputList: MediaDeviceInfo[], videoInputList: MediaDeviceInfo[]) {
	const availableDevices = devices.map(device => device.deviceId).filter(id => id !== 'default')

	const getDeviceString = (device: MediaDeviceInfo, index: number) => {
		const isUnplugged = !availableDevices.includes(device.deviceId) ? ' (unplugged)' : ''
		const isSelected = () => {
			if (device.kind === 'audioinput') {
				return device.deviceId === audioInputId ? ' (selected)' : ''
			} else if (device.kind === 'videoinput') {
				return device.deviceId === videoInputId ? ' (selected)' : ''
			}
		}
		return `    ${index + 1}. ${device.label || device.deviceId}` + isUnplugged + isSelected()
	}

	// eslint-disable-next-line no-console
	console.log(`Media devices:
  Audio input:
${audioInputList.map(getDeviceString).join('\n')}

  Video input:
${videoInputList.map(getDeviceString).join('\n')}
`)
}

/**
 * Modify devices list.
 *
 * @param device device
 * @param devicesList list of registered devices in order of preference
 * @param promote whether device should be promoted (to be used in updateMediaDevicesPreferences)
 */
function registerNewDevice(device: MediaDeviceInfo, devicesList: MediaDeviceInfo[], promote: boolean = false) {
	const newDevicesList = devicesList.slice()
	console.debug('Registering new device:', device)

	if (promote) {
		newDevicesList.unshift(device)
	} else {
		newDevicesList.push(device)
	}

	return newDevicesList
}

/**
 * Populate devices preferences. If device has not yet been registered in preference list, it will be added.
 *
 * Returns changed preference lists for audio / video devices (null, if it hasn't been changed)
 *
 * @param devices list of available devices
 * @param audioInputList list of registered audio devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 */
function populateMediaDevicesPreferences(devices: MediaDeviceInfo[], audioInputList: MediaDeviceInfo[], videoInputList: MediaDeviceInfo[]) {
	let audioHasChanged = false
	let videoHasChanged = false
	const newAudioInputList = audioInputList.slice()
	const newVideoInputList = videoInputList.slice()

	for (const device of devices) {
		if (device.kind === 'audioinput') {
			// Add to the list of known devices
			if (device.deviceId !== 'default' && !newAudioInputList.some(input => input.deviceId === device.deviceId)) {
				registerNewDevice(device, newAudioInputList)
				audioHasChanged = true
			}
		} else if (device.kind === 'videoinput') {
			// Add to the list of known devices
			if (device.deviceId !== 'default' && !newVideoInputList.some(input => input.deviceId === device.deviceId)) {
				registerNewDevice(device, newVideoInputList)
				videoHasChanged = true
			}
		}
	}

	return {
		newAudioInputList: audioHasChanged ? newAudioInputList : null,
		newVideoInputList: videoHasChanged ? newVideoInputList : null,
	}
}

/**
 * Update devices preferences. Assuming that preferred devices were selected, should be called after applying the selection:
 * so either with joining the call or changing device during the call
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
 * @param audioInputId id of currently selected audio input
 * @param videoInputId id of currently selected video input
 * @param audioInputList list of registered audio devices in order of preference
 * @param videoInputList list of registered video devices in order of preference
 */
function updateMediaDevicesPreferences(devices: MediaDeviceInfo[], audioInputId: string, videoInputId: string, audioInputList: MediaDeviceInfo[], videoInputList: MediaDeviceInfo[]) {
	let audioHasChanged = false
	let videoHasChanged = false
	const newAudioInputList = audioInputList.slice()
	const newVideoInputList = videoInputList.slice()
	const availableDevices = devices.map(device => device.deviceId).filter(id => id !== 'default')

	const audioDeviceRank = newAudioInputList.findIndex(device => device.deviceId === audioInputId)
	if (audioDeviceRank === -1 && audioInputId !== 'default' && audioInputId !== null) {
		const device = devices.find(device => device.deviceId === audioInputId)
		if (device) {
			console.debug('Registering new audio device:', device)
			newAudioInputList.unshift(device)
			audioHasChanged = true
		}
	} else if (audioDeviceRank > 0) {
		const device = newAudioInputList.splice(audioDeviceRank, 1)[0]
		const pluggedIndex = newAudioInputList.findIndex(device => availableDevices.includes(device.deviceId))
		const insertPosition = pluggedIndex === -1 ? newAudioInputList.length : pluggedIndex
		newAudioInputList.splice(insertPosition, 0, device)
		audioHasChanged = true
	}

	const videoDeviceRank = newVideoInputList.findIndex(device => device.deviceId === videoInputId)
	if (videoDeviceRank === -1 && videoInputId !== 'default' && videoInputId !== null) {
		const device = devices.find(device => device.deviceId === videoInputId)
		if (device) {
			console.debug('Registering new video device:', device)
			newVideoInputList.unshift(device)
			videoHasChanged = true
		}
	} else if (videoDeviceRank > 0) {
		const device = newVideoInputList.splice(videoDeviceRank, 1)[0]
		const pluggedIndex = newVideoInputList.findIndex(device => availableDevices.includes(device.deviceId))
		const insertPosition = pluggedIndex === -1 ? newVideoInputList.length : pluggedIndex
		newVideoInputList.splice(insertPosition, 0, device)
		videoHasChanged = true
	}

	return {
		newAudioInputList: audioHasChanged ? newAudioInputList : null,
		newVideoInputList: videoHasChanged ? newVideoInputList : null,
	}
}

export {
	listMediaDevices,
	populateMediaDevicesPreferences,
	updateMediaDevicesPreferences,
}
