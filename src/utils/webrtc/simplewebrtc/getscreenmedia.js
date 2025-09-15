/**
 * SPDX-FileCopyrightText: Henrik Joreteg &yet, LLC.
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */

/**
 *
 * @param {string} mode screen or window
 * @param {object} constraints media constraints
 * @param {Function} cb callback
 */
export default function(mode, constraints, cb) {
	const hasConstraints = arguments.length === 3
	const callback = hasConstraints ? cb : constraints
	let error

	if (typeof window === 'undefined' || window.location.protocol === 'http:') {
		error = new Error('NavigatorUserMediaError')
		error.name = 'HTTPS_REQUIRED'
		return callback(error)
	}

	if (IS_DESKTOP) {
		return window.OCA.Talk.Desktop.getDesktopMediaSource()
			.then(({ sourceId }) => {
				if (!sourceId) {
					// User canceled
					const error = new Error('NavigatorUserMediaError')
					error.name = 'PERMISSION_DENIED'
					throw error
				}

				// Special case for sharing all the screens with desktop audio in Electron
				// In this case, it must have exactly these constraints
				// "entire-desktop:0:0" is a custom sourceId for this specific case
				const constraints = (sourceId === 'entire-desktop:0:0')
					? {
							audio: {
								mandatory: {
									chromeMediaSource: 'desktop',
								},
							},
							video: {
								mandatory: {
									chromeMediaSource: 'desktop',
								},
							},
						}
					: {
							audio: false,
							video: {
								mandatory: {
									chromeMediaSource: 'desktop',
									chromeMediaSourceId: sourceId,
								},
							},
						}
				return navigator.mediaDevices.getUserMedia(constraints)
			})
			.then((stream) => callback(null, stream))
			.catch((error) => callback(error))
	} else if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
		navigator.mediaDevices.getDisplayMedia({
			video: true,
			// Disable default audio optimizations, as they are meant to be used
			// with a microphone input.
			audio: {
				echoCancellation: false,
				autoGainControl: false,
				noiseSuppression: false,
			},
		}).then(function(stream) {
			callback(null, stream)
		}).catch(function(error) {
			callback(error, null)
		})
	} else {
		error = new Error('MediaStreamError')
		error.name = 'NotSupportedError'
		callback(error)
	}
}
