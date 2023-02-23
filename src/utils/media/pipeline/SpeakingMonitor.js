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

import hark from 'hark'

import TrackSink from './TrackSink.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 * Sink node to detect sound in its input track and emit "speaking" events.
 *
 * A single input track slot with the default id is accepted. The input track
 * must be an audio track.
 *
 * The monitor is automatically started when an input track is set and stopped
 * when set to null. The monitor does not stop if the input track ends; it is
 * assumed that the source node will remove its output track in that case.
 *
 * The following events are emitted:
 * - "speaking"
 * - "speakingWhileMuted"
 * - "stoppedSpeaking"
 * - "stoppedSpeakingWhileMuted"
 * - "volumeChange", with "volume" and "threshold" parameters
 *   - "volume" goes from -100 (silence) to 0 (loudest sound in the system)
 *   - "threshold" is the volume threshold to emit speaking events
 *
 * See EmitterMixin documentation for details about the API.
 *
 *        -----------------
 *       |                 |
 *  ---> | SpeakingMonitor |
 *       |                 |
 *        -----------------
 */
export default class SpeakingMonitor extends TrackSink {

	constructor() {
		super()
		this._superEmitterMixin()

		this._addInputTrackSlot()

		this._speaking = false
		this._audioEnabled = false
	}

	_handleInputTrack(trackId, track) {
		if (this._audioMonitor) {
			this._audioMonitor.stop()
			this._audioMonitor = null
		}
		if (this._clonedTrack) {
			this._clonedTrack.stop()
			this._clonedTrack = null
		}

		this._speaking = false
		this._audioEnabled = false

		if (!track) {
			return
		}

		let timeout

		this._audioEnabled = track.enabled

		// The audio monitor uses its own cloned track that is always enabled to
		// be able to analyze it even when the input track is muted. Note that
		// even if the input track was muted when cloned it is still possible to
		// unmute the clone.
		this._clonedTrack = track.clone()
		this._clonedTrack.enabled = true

		this._audioMonitor = hark(new MediaStream([this._clonedTrack]))

		this._audioMonitor.on('speaking', () => {
			if (timeout) {
				clearTimeout(timeout)
			}

			this._speaking = true

			if (this._audioEnabled) {
				this._trigger('speaking')
			} else {
				this._trigger('speakingWhileMuted')
			}
		})

		this._audioMonitor.on('stopped_speaking', () => {
			if (timeout) {
				clearTimeout(timeout)
			}

			timeout = setTimeout(() => {
				this._speaking = false

				if (this._audioEnabled) {
					this._trigger('stoppedSpeaking')
				} else {
					this._trigger('stoppedSpeakingWhileMuted')
				}
			}, 1000)
		})

		this._audioMonitor.on('volume_change', (volume, threshold) => {
			this._trigger('volumeChange', [volume, threshold])
		})
	}

	_handleInputTrackEnabled(trackId, enabled) {
		if (this._audioEnabled === enabled) {
			return
		}

		this._audioEnabled = enabled

		if (!this._speaking) {
			return
		}

		if (enabled) {
			this._trigger('stoppedSpeakingWhileMuted')
			this._trigger('speaking')
		} else {
			this._trigger('stoppedSpeaking')
			this._trigger('speakingWhileMuted')
		}
	}

}

EmitterMixin.apply(SpeakingMonitor.prototype)
