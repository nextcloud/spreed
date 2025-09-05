/**
 * SPDX-FileCopyrightText: Henrik Joreteg &yet, LLC.
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: MIT
 */
/* global chrome */

// getScreenMedia helper by @HenrikJoreteg
/**
 *
 * @param constraints
 * @param callback
 */
function getUserMedia(constraints, callback) {
	if (!window.navigator || !window.navigator.mediaDevices || !window.navigator.mediaDevices.getUserMedia) {
		const error = new Error('MediaStreamError')
		error.name = 'NotSupportedError'

		if (callback) {
			callback(error, null)
		}

		return
	}

	window.navigator.mediaDevices.getUserMedia(constraints).then(function(stream) {
		callback(null, stream)
	}).catch(function(error) {
		callback(error, null)
	})
}

// cache for constraints and callback
const cache = {}

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
	} else if (window.navigator.userAgent.match('Firefox')) {
		const ffver = parseInt(window.navigator.userAgent.match(/Firefox\/(.*)/)[1], 10)
		if (ffver >= 52) {
			mode = mode || 'window'
			constraints = (hasConstraints && constraints) || {
				video: {
					mozMediaSource: mode,
					mediaSource: mode,
				},
			}
			getUserMedia(constraints, function(err, stream) {
				callback(err, stream)
				if (err) {
					return
				}
				// workaround for https://bugzilla.mozilla.org/show_bug.cgi?id=1045810
				let lastTime = stream.currentTime
				const polly = window.setInterval(function() {
					if (!stream) {
						window.clearInterval(polly)
					}
					if (stream.currentTime === lastTime) {
						window.clearInterval(polly)
						if (stream.onended) {
							stream.onended()
						}
					}
					lastTime = stream.currentTime
				}, 500)
			})
		} else {
			error = new Error('NavigatorUserMediaError')
			error.name = 'FF52_REQUIRED'
			return callback(error)
		}
	}
}

typeof window !== 'undefined' && window.addEventListener('message', function(event) {
	if (event.origin !== window.location.origin && !event.isTrusted) {
		return
	}
	if (event.data.type === 'gotScreen' && cache[event.data.id]) {
		const data = cache[event.data.id]
		let constraints = data[1]
		const callback = data[0]
		delete cache[event.data.id]

		if (event.data.sourceId === '') { // user canceled
			const error = new Error('NavigatorUserMediaError')
			error.name = 'PERMISSION_DENIED'
			callback(error)
		} else {
			constraints = constraints || {
				audio: false,
				video: {
					mandatory: {
						chromeMediaSource: 'desktop',
						maxWidth: window.screen.width,
						maxHeight: window.screen.height,
						maxFrameRate: 3,
					},
					optional: [
						{ googLeakyBucket: true },
						{ googTemporalLayeredScreencast: true },
					],
				},
			}
			constraints.video.mandatory.chromeMediaSourceId = event.data.sourceId
			getUserMedia(constraints, callback)
		}
	} else if (event.data.type === 'getScreenPending') {
		window.clearTimeout(event.data.id)
	}
})
