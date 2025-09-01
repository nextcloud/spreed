/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

if (window.MediaStream) {
	const originalMediaStreamAddTrack = window.MediaStream.prototype.addTrack
	window.MediaStream.prototype.addTrack = function(track) {
		let addTrackEventDispatched = false
		const testAddTrackEvent = () => {
			addTrackEventDispatched = true
		}
		this.addEventListener('addtrack', testAddTrackEvent)

		originalMediaStreamAddTrack.apply(this, arguments)

		this.removeEventListener('addtrack', testAddTrackEvent)

		if (!addTrackEventDispatched) {
			this.dispatchEvent(new MediaStreamTrackEvent('addtrack', { track }))
		}
	}

	const originalMediaStreamRemoveTrack = window.MediaStream.prototype.removeTrack
	window.MediaStream.prototype.removeTrack = function(track) {
		let removeTrackEventDispatched = false
		const testRemoveTrackEvent = () => {
			removeTrackEventDispatched = true
		}
		this.addEventListener('removetrack', testRemoveTrackEvent)

		originalMediaStreamRemoveTrack.apply(this, arguments)

		this.removeEventListener('removetrack', testRemoveTrackEvent)

		if (!removeTrackEventDispatched) {
			this.dispatchEvent(new MediaStreamTrackEvent('removetrack', { track }))
		}
	}

	// Event implementations do not support advanced parameters like "options"
	// or "useCapture".
	const originalMediaStreamDispatchEvent = window.MediaStream.prototype.dispatchEvent
	const originalMediaStreamAddEventListener = window.MediaStream.prototype.addEventListener
	const originalMediaStreamRemoveEventListener = window.MediaStream.prototype.removeEventListener

	window.MediaStream.prototype.dispatchEvent = function(event) {
		if (this._listeners && this._listeners[event.type]) {
			this._listeners[event.type].forEach((listener) => {
				listener.apply(this, [event])
			})
		}

		return originalMediaStreamDispatchEvent.apply(this, arguments)
	}

	let isMediaStreamDispatchEventSupported

	window.MediaStream.prototype.addEventListener = function(type, listener) {
		if (isMediaStreamDispatchEventSupported === undefined) {
			isMediaStreamDispatchEventSupported = false
			const testDispatchEventSupportHandler = () => {
				isMediaStreamDispatchEventSupported = true
			}
			originalMediaStreamAddEventListener.apply(this, ['test-dispatch-event-support', testDispatchEventSupportHandler])
			originalMediaStreamDispatchEvent.apply(this, [new Event('test-dispatch-event-support')])
			originalMediaStreamRemoveEventListener(this, ['test-dispatch-event-support', testDispatchEventSupportHandler])

			console.debug('Is MediaStream.dispatchEvent() supported?: ', isMediaStreamDispatchEventSupported)
		}

		if (!isMediaStreamDispatchEventSupported) {
			if (!this._listeners) {
				this._listeners = []
			}

			if (!Object.hasOwn(this._listeners, type)) {
				this._listeners[type] = [listener]
			} else if (!this._listeners[type].includes(listener)) {
				this._listeners[type].push(listener)
			}
		}

		return originalMediaStreamAddEventListener.apply(this, arguments)
	}

	window.MediaStream.prototype.removeEventListener = function(type, listener) {
		if (this._listeners && this._listeners[type]) {
			const index = this._listeners[type].indexOf(listener)
			if (index >= 0) {
				this._listeners[type].splice(index, 1)
			}
		}

		return originalMediaStreamRemoveEventListener.apply(this, arguments)
	}
}
