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

const STOP_DELAY = 3000

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
		this._noiseSuppressionConsumer = null
		this._stopTimer = null
	}

	isEnabled() {
		return this._enabled
	}

	setEnabled(enabled) {
		if (this._enabled === enabled) {
			return
		}

		this._enabled = enabled

		if (enabled) {
			this._startEffect()
		} else {
			this._stopEffect()
		}
	}

	async _startEffect() {
		if (this._stopTimer) {
			clearTimeout(this._stopTimer)
			this._stopTimer = null
		}

		if (!this._noiseSuppressionConsumer) {
			this._noiseSuppressionConsumer = await registerNoiseSuppressionWorklet()
		}

		const track = this.getInputTrack('default')
		if (!track) {
			this._setOutputTrack('default', null)
			return
		}

		const oldTrack = this.getOutputTrack('default')
		if (oldTrack && oldTrack !== track && !oldTrack.unprocessed) {
			oldTrack.stop()
		}

		const inputStream = new MediaStream([track])
		const processedStream = processNoiseSuppression(inputStream, true)
		const processedTrack = processedStream.getAudioTracks()[0]
		processedTrack.enabled = this._audioEnabled

		this._setOutputTrack('default', processedTrack)
	}

	_stopEffect() {
		this._stopTimer = setTimeout(async() => {
			if (this._noiseSuppressionConsumer) {
				await destroyNoiseSuppressionWorklet(this._noiseSuppressionConsumer)
				this._noiseSuppressionConsumer = null
			}
			this._stopTimer = null
		}, STOP_DELAY)

		const track = this.getInputTrack('default')
		if (track) {
			track.unprocessed = true
		}
		this._setOutputTrack('default', track)
	}

	_handleInputTrack(trackId, track) {
		if (this._enabled) {
			this._startEffect()
		} else {
			this._stopEffect()
		}
	}

	_handleInputTrackEnabled(trackId, enabled) {
		this._setOutputTrackEnabled('default', enabled)
	}
}
