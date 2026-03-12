/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	destroyNoiseSuppressionWorklet,
	processNoiseSuppression,
	registerNoiseSuppressionWorklet,
} from '../../suppressNoise.ts'
import TrackSinkSource from './TrackSinkSource.js'

/**
 * Processor node to enable or disable noise suppression.
 *
 * A single input track slot with the default id is accepted. A single output
 * track slot with the default id is provided.
 *
 * The track can be enabled and disabled by calling "setEnabled(bool)".
 *
 * When enabled, the node processes the input audio track to suppress noise and
 * outputs a new, processed track. When disabled, it passes the original audio
 * track through without modification.
 *
 * The node manages the lifecycle of the underlying noise suppression worklet,
 * registering a consumer when enabled and destroying it when disabled.
 *
 *        -----------------
 *       |                 |
 *  ---> | NoiseSuppressor | --->
 *       |                 |
 *        -----------------
 */
export default class NoiseSuppressor extends TrackSinkSource {
	constructor() {
		super()

		this._addInputTrackSlot()
		this._addOutputTrackSlot()

		this._enabled = false
		this.noiseSuppressionConsumer = null
	}

	isEnabled() {
		return this._enabled
	}

	async setEnabled(enabled) {
		if (this._enabled === enabled) {
			return
		}

		this._enabled = enabled

		if (enabled) {
			this.noiseSuppressionConsumer = await registerNoiseSuppressionWorklet()
		} else if (this.noiseSuppressionConsumer) {
			await destroyNoiseSuppressionWorklet(this.noiseSuppressionConsumer)
			this.noiseSuppressionConsumer = null
		}

		const track = this.getInputTrack('default')
		this._handleInputTrack('default', track) // todo
	}

	/**
	_startEffect() {
		init
		set output
	}

	_stopEffect() {
		timeout
		destroy
		set output
	}
		**/

	_handleInputTrack(trackId, track) {
		if (!this._enabled || !track) {
			this._setOutputTrack('default', track)
			return
		}

		const isOriginalTrackEnabled = track.enabled

		const inputStream = new MediaStream([track])
		const processedStream = processNoiseSuppression(inputStream, this._enabled)
		const processedTrack = processedStream.getAudioTracks()[0]
		processedTrack.enabled = isOriginalTrackEnabled // todo

		this._setOutputTrack('default', processedTrack)
	}

	_handleInputTrackEnabled(trackId, enabled) {
		this._setOutputTrackEnabled('default', enabled)
	}
}
