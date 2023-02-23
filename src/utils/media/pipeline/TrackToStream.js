/**
 *
 * @copyright Copyright (c) 2021, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import TrackSink from './TrackSink.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 * Sink node to add one or more tracks to a single stream.
 *
 * By default no slots are defined. Clients of this class need to call
 * "addInputTrackSlot(trackId)" to add them as needed.
 *
 * The stream can be got with "getStream()". No stream is returned if all the
 * input tracks are null. The stream will be recreated (so it will be a
 * different object) if all the input tracks are set to null and then an input
 * track is set again.
 *
 * Note that the order of the tracks in the stream is undefined, and unrelated
 * to the order in which the input slots were added.
 *
 * The following events are emitted:
 * - "streamSet", with "newStream" and "oldStream" parameters
 * - "trackReplaced", with "newTrack" and "oldTrack" parameters
 * - "trackEnabled", with "track" and "enabled" parameters
 *
 * See EmitterMixin documentation for details about the API.
 *
 *        ---------------
 *  ---> |               |
 *  ...  | TrackToStream |
 *  ---> |               |
 *        ---------------
 */
export default class TrackToStream extends TrackSink {

	constructor() {
		super()
		this._superEmitterMixin()

		this._stream = null

		this._trackEnabledStates = {}
	}

	addInputTrackSlot(trackId) {
		this._addInputTrackSlot(trackId)
	}

	getStream() {
		return this._stream
	}

	_handleInputTrack(trackId, newTrack, oldTrack) {
		// Only constraints changed, nothing to do
		if (newTrack === oldTrack) {
			// But trigger "trackEnabled" if the state changed
			if (newTrack && this._trackEnabledStates[trackId] !== newTrack.enabled) {
				this._trackEnabledStates[trackId] = newTrack.enabled

				this._trigger('trackEnabled', [newTrack, newTrack.enabled])
			}

			return
		}

		if (!this._stream && newTrack) {
			this._stream = new MediaStream()

			this._trigger('streamSet', [this._stream, null])
		}

		if (this._stream && oldTrack) {
			this._stream.removeTrack(oldTrack)
		}

		if (this._stream && newTrack) {
			this._stream.addTrack(newTrack)
		}

		this._trackEnabledStates[trackId] = newTrack?.enabled

		this._trigger('trackReplaced', [newTrack, oldTrack])

		if (this._stream && this._stream.getTracks().length === 0) {
			const oldStream = this._stream

			this._stream = null

			this._trigger('streamSet', [null, oldStream])
		}
	}

	_handleInputTrackEnabled(trackId, enabled) {
		this._trackEnabledStates[trackId] = enabled

		this._trigger('trackEnabled', [this.getInputTrack(trackId), enabled])
	}

}

EmitterMixin.apply(TrackToStream.prototype)
