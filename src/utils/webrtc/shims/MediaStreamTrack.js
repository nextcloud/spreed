/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (window.MediaStreamTrack) {
	const originalMediaStreamTrackClone = window.MediaStreamTrack.prototype.clone
	window.MediaStreamTrack.prototype.clone = function() {
		const newTrack = originalMediaStreamTrackClone.apply(this, arguments)

		this.dispatchEvent(new CustomEvent('cloned', { detail: newTrack }))

		return newTrack
	}

	const originalMediaStreamTrackStop = window.MediaStreamTrack.prototype.stop
	window.MediaStreamTrack.prototype.stop = function() {
		const wasAlreadyEnded = this.readyState === 'ended'

		originalMediaStreamTrackStop.apply(this, arguments)

		if (!wasAlreadyEnded) {
			this.dispatchEvent(new Event('ended'))
		}
	}

	// Event implementations do not support advanced parameters like "options"
	// or "useCapture".
	const originalMediaStreamTrackDispatchEvent = window.MediaStreamTrack.prototype.dispatchEvent
	const originalMediaStreamTrackAddEventListener = window.MediaStreamTrack.prototype.addEventListener
	const originalMediaStreamTrackRemoveEventListener = window.MediaStreamTrack.prototype.removeEventListener

	window.MediaStreamTrack.prototype.dispatchEvent = function(event) {
		if (this._listeners && this._listeners[event.type]) {
			this._listeners[event.type].forEach(listener => {
				listener.apply(this, [event])
			})
		}

		return originalMediaStreamTrackDispatchEvent.apply(this, arguments)
	}

	let isMediaStreamTrackDispatchEventSupported

	window.MediaStreamTrack.prototype.addEventListener = function(type, listener) {
		if (isMediaStreamTrackDispatchEventSupported === undefined) {
			isMediaStreamTrackDispatchEventSupported = false
			const testDispatchEventSupportHandler = () => {
				isMediaStreamTrackDispatchEventSupported = true
			}
			originalMediaStreamTrackAddEventListener.apply(this, ['test-dispatch-event-support', testDispatchEventSupportHandler])
			originalMediaStreamTrackDispatchEvent.apply(this, [new Event('test-dispatch-event-support')])
			originalMediaStreamTrackRemoveEventListener(this, ['test-dispatch-event-support', testDispatchEventSupportHandler])

			console.debug('Is MediaStreamTrack.dispatchEvent() supported?: ', isMediaStreamTrackDispatchEventSupported)
		}

		if (!isMediaStreamTrackDispatchEventSupported) {
			if (!this._listeners) {
				this._listeners = []
			}

			if (!Object.prototype.hasOwnProperty.call(this._listeners, type)) {
				this._listeners[type] = [listener]
			} else if (!this._listeners[type].includes(listener)) {
				this._listeners[type].push(listener)
			}
		}

		return originalMediaStreamTrackAddEventListener.apply(this, arguments)
	}

	window.MediaStreamTrack.prototype.removeEventListener = function(type, listener) {
		if (this._listeners && this._listeners[type]) {
			const index = this._listeners[type].indexOf(listener)
			if (index >= 0) {
				this._listeners[type].splice(index, 1)
			}
		}

		return originalMediaStreamTrackRemoveEventListener.apply(this, arguments)
	}
}
