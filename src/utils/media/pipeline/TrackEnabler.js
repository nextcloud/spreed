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

import TrackSinkSource from './TrackSinkSource'

/**
 * Processor node to enable or disable its track.
 *
 * A single input track slot with the default id is accepted. A single output
 * track slot with the default id is provided.
 *
 * The track can be enabled and disabled by calling "setEnabled(bool)".
 *
 * Note that the input and output tracks are the same track (the output is not
 * cloned), so the input track is enabled and disabled. Therefore, this is a
 * special case of processor node that modifies its input.
 *
 * The enabled state of the track will try to be enforced. That is, if the
 * enabled state of the input track changes it will be automatically set again
 * to the expected enabled state.
 *
 *        --------------
 *       |              |
 *  ---> | TrackEnabler | --->
 *       |              |
 *        --------------
 */
export default class TrackEnabler extends TrackSinkSource {

	constructor() {
		super()

		this._addInputTrackSlot()
		this._addOutputTrackSlot()

		this._enabled = true
	}

	isEnabled() {
		return this._enabled
	}

	setEnabled(enabled) {
		this._enabled = enabled

		this._setOutputTrackEnabled('default', enabled)
	}

	_handleInputTrack(trackId, track) {
		// Ignore the enabled state of the input and force the desired state by
		// the node. The state must be forced before setting the output track to
		// ensure that it will have the desired state from the start (and thus
		// "_setOutputTrackEnabled" can not be used).
		if (track && track.enabled !== this._enabled) {
			track.enabled = this._enabled
		}

		this._setOutputTrack('default', track)
	}

	_handleInputTrackEnabled(trackId, enabled) {
		// Ignore the enabled state of the input and force the desired state by
		// the node.
		if (enabled !== this._enabled) {
			this._setOutputTrackEnabled('default', this._enabled)
		}
	}

}
