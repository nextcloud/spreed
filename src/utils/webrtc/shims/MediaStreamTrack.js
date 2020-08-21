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

			if (!this._listeners.hasOwnProperty(type)) {
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
