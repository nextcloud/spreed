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
			this.dispatchEvent(new MediaStreamTrackEvent('addtrack', { track: track }))
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
			this.dispatchEvent(new MediaStreamTrackEvent('removetrack', { track: track }))
		}
	}

	// Event implementations do not support advanced parameters like "options"
	// or "useCapture".
	const originalMediaStreamDispatchEvent = window.MediaStream.prototype.dispatchEvent
	const originalMediaStreamAddEventListener = window.MediaStream.prototype.addEventListener
	const originalMediaStreamRemoveEventListener = window.MediaStream.prototype.removeEventListener

	window.MediaStream.prototype.dispatchEvent = function(event) {
		if (this._listeners && this._listeners[event.type]) {
			this._listeners[event.type].forEach(listener => {
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

			if (!this._listeners.hasOwnProperty(type)) {
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
