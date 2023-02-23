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

import TrackSinkMixin from './TrackSinkMixin.js'
import TrackSourceMixin from './TrackSourceMixin.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 * Base class for nodes that act both as a sink and as a source (a processing
 * node).
 *
 * See TrackSinkMixin and TrackSourceMixin documentation for details.
 *
 * EmitterMixin is already applied, so subclasses do not need to apply it.
 *        -----------------
 *  ---> |                 | --->
 *  ...  | TrackSinkSource | ...
 *  ---> |                 | --->
 *        -----------------
 */
export default class TrackSinkSource {

	constructor() {
		this._superEmitterMixin()
		this._superTrackSinkMixin()
		this._superTrackSourceMixin()
	}

}

EmitterMixin.apply(TrackSinkSource.prototype)
TrackSinkMixin.apply(TrackSinkSource.prototype)
TrackSourceMixin.apply(TrackSinkSource.prototype)
