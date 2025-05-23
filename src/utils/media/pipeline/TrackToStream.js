/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
