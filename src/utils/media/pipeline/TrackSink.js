/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import TrackSinkMixin from './TrackSinkMixin.js'

/**
 * Base class for sink nodes of tracks.
 *
 * See TrackSinkMixin documentation for details.
 *
 *        -----------
 *  ---> |           |
 *  ...  | TrackSink |
 *  ---> |           |
 *        -----------
 */
export default class TrackSink {
	constructor() {
		this._superTrackSinkMixin()
	}
}

TrackSinkMixin.apply(TrackSink.prototype)
