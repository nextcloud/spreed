/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
