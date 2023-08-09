/* global module, chrome */

// getScreenMedia helper by @HenrikJoreteg
const getUserMedia = function(constraints, callback) {
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
 * @param mode
 * @param constraints
 * @param cb
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

	if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
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
	} else if (navigator.webkitGetUserMedia) {
		const chromever = parseInt(window.navigator.userAgent.match(/Chrome\/(\d+)\./)[1], 10)
		let maxver = 33
		// Chrome 71 dropped support for "window.chrome.webstore;".
		const isCef = (chromever < 71) && !window.chrome.webstore
		// "known" crash in chrome 34 and 35 on linux
		if (window.navigator.userAgent.match('Linux')) {
			maxver = 35
		}

		// check that the extension is installed by looking for a
		// sessionStorage variable that contains the extension id
		// this has to be set after installation unless the contest
		// script does that
		if (sessionStorage.getScreenMediaJSExtensionId) {
			chrome.runtime.sendMessage(sessionStorage.getScreenMediaJSExtensionId,
				{ type: 'getScreen', id: 1 }, null,
				function(data) {
					if (!data || data.sourceId === '') { // user canceled
						const error = new Error('NavigatorUserMediaError')
						error.name = 'PERMISSION_DENIED'
						callback(error)
					} else {
						constraints = (hasConstraints && constraints) || {
							audio: false,
							video: {
								mandatory: {
									chromeMediaSource: 'desktop',
									maxWidth: window.screen.width,
									maxHeight: window.screen.height,
									maxFrameRate: 3,
								},
							},
						}
						constraints.video.mandatory.chromeMediaSourceId = data.sourceId
						getUserMedia(constraints, callback)
					}
				}
			)
		} else if (window.cefGetScreenMedia) {
			// window.cefGetScreenMedia is experimental - may be removed without notice
			window.cefGetScreenMedia(function(sourceId) {
				if (!sourceId) {
					const error = new Error('cefGetScreenMediaError')
					error.name = 'CEF_GETSCREENMEDIA_CANCELED'
					callback(error)
				} else {
					constraints = (hasConstraints && constraints) || {
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
					constraints.video.mandatory.chromeMediaSourceId = sourceId
					getUserMedia(constraints, callback)
				}
			})
		} else if (isCef || (chromever >= 26 && chromever <= maxver)) {
			// chrome 26 - chrome 33 way to do it -- requires bad chrome://flags
			// note: this is basically in maintenance mode and will go away soon
			constraints = (hasConstraints && constraints) || {
				video: {
					mandatory: {
						googLeakyBucket: true,
						maxWidth: window.screen.width,
						maxHeight: window.screen.height,
						maxFrameRate: 3,
						chromeMediaSource: 'screen',
					},
				},
			}
			getUserMedia(constraints, callback)
		} else {
			// chrome 34+ way requiring an extension
			const pending = window.setTimeout(function() {
				error = new Error('NavigatorUserMediaError')
				error.name = 'EXTENSION_UNAVAILABLE'
				return callback(error)
			}, 1000)
			cache[pending] = [callback, hasConstraints ? constraints : null]
			window.postMessage({ type: 'getScreen', id: pending }, '*')
		}
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
