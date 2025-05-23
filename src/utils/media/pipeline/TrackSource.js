/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import TrackSourceMixin from './TrackSourceMixin.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 * Base class for source nodes of tracks.
 *
 * See TrackSourceMixin documentation for details.
 *
 * EmitterMixin is already applied, so subclasses do not need to apply it.
 *
 *        -------------
 *       |             | --->
 *       | TrackSource | ...
 *       |             | --->
 *        -------------
 */
export default class TrackSource {
	constructor() {
		this._superEmitterMixin()
		this._superTrackSourceMixin()
	}
}

EmitterMixin.apply(TrackSource.prototype)
TrackSourceMixin.apply(TrackSource.prototype)
