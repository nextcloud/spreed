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

/**
 * Wrapper for MediaDevices to simplify its use.
 *
 * "attributes.audioInputId" and "attributes.videoInputId" define the devices
 * that will be used when calling "getUserMedia(constraints)".
 */
export default function MediaDevicesManager() {
	this.attributes = {
		audioInputId: undefined,
		videoInputId: undefined,
	}
}
MediaDevicesManager.prototype = {

	/**
	 * Returns whether getting user media and enumerating media devices is
	 * supported or not.
	 *
	 * Note that even if false is returned the MediaDevices interface could be
	 * technically supported by the browser but not available due to the page
	 * being loaded in an insecure context.
	 *
	 * @returns {boolean} true if MediaDevices interface is supported, false
	 *          otherwise.
	 */
	isSupported: function() {
		return navigator && navigator.mediaDevices && navigator.mediaDevices.getUserMedia && navigator.mediaDevices.enumerateDevices
	},

	/**
	 * Wrapper for MediaDevices.getUserMedia to use the selected audio and video
	 * input devices.
	 *
	 * The selected audio and video input devices are used only if the
	 * constraints do not specify a device already. Otherwise the devices in the
	 * constraints are respected.
	 *
	 * @param {MediaStreamConstraints} constraints the constraints specifying
	 *        the media to request
	 * @returns {Promise} resolved with a MediaStream object when successful, or
	 *          rejected with a DOMException in case of error
	 */
	getUserMedia: function(constraints) {
		if (!this.isSupported()) {
			return new Promise((resolve, reject) => {
				reject(new DOMException('MediaDevicesManager is not supported', 'NotSupportedError'))
			})
		}

		if (constraints.audio && !constraints.audio.deviceId && this.attributes.audioInputId) {
			if (!(constraints.audio instanceof Object)) {
				constraints.audio = {}
			}
			constraints.audio.deviceId = this.attributes.audioInputId
		}

		if (constraints.video && !constraints.video.deviceId && this.attributes.videoInputId) {
			if (!(constraints.video instanceof Object)) {
				constraints.video = {}
			}
			constraints.video.deviceId = this.attributes.videoInputId
		}

		return navigator.mediaDevices.getUserMedia(constraints)
	},
}
