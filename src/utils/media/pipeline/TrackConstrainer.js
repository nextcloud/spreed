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

import TrackSinkSource from './TrackSinkSource.js'

/**
 * Processor node to apply constraints on its track.
 *
 * A single input track slot with the default id is accepted. A single output
 * track slot with the default id is provided.
 *
 * The constraints can be applied by calling "applyConstraints(constraints)".
 * The output track is set again to the same track whenever the constraints are
 * successfully applied.
 *
 * Note that the input and output tracks are the same track (the output is not
 * cloned), so the constraints are applied on the input track. Therefore, this
 * is a special case of processor node that modifies its input.
 *
 *        ------------------
 *       |                  |
 *  ---> | TrackConstrainer | --->
 *       |                  |
 *        ------------------
 */
export default class TrackConstrainer extends TrackSinkSource {

	constructor() {
		super()

		this._addInputTrackSlot()
		this._addOutputTrackSlot()
	}

	async applyConstraints(constraints) {
		if (!this.getOutputTrack()) {
			return
		}

		await this.getOutputTrack().applyConstraints(constraints)

		this._setOutputTrack('default', this.getOutputTrack())
	}

	_handleInputTrack(trackId, track) {
		this._setOutputTrack('default', track)
	}

	_handleInputTrackEnabled(trackId, enabled) {
		this._setOutputTrackEnabled('default', enabled)
	}

}
