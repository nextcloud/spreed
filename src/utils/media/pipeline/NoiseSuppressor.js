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
		this._audioEnabled = true
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
		this._handleInputTrack('default', track)
	}

	_handleInputTrack(trackId, track) {
		if (track) {
			this._audioEnabled = track.enabled
		}
		if (!this._enabled || !track) {
			this._setOutputTrack('default', track)
			return
		}

		const inputStream = new MediaStream([track])
		const processedStream = processNoiseSuppression(inputStream, true)
		const processedTrack = processedStream.getAudioTracks()[0]
		processedTrack.enabled = this._audioEnabled

		this._setOutputTrack('default', processedTrack)
	}

	_handleInputTrackEnabled(trackId, enabled) {
		if (enabled !== this._audioEnabled) {
			this._audioEnabled = enabled
			this._setOutputTrackEnabled('default', enabled)
		}
	}
}
