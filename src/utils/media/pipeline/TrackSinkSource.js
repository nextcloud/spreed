/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
