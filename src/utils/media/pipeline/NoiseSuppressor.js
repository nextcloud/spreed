/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	processNoiseSuppression,
	registerNoiseSuppressionWorklet,
	unregisterNoiseSuppressionWorklet,
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

		/** Whether to send processed or unmodified stream to the output */
		this._enabled = false
		/** Whether the current input audio track is enabled */
		this._audioEnabled = false
		/** Timeout for worklets destroying and garbage collection (in case of reconnect) */
		this._stopTimer = null
		/** Unique consumer symbol to track worklet subscribers */
		this._noiseSuppressionConsumer = null
		/** Pending registration promise to keep _startEffect synchronous */
		this._noiseSuppressionRegistrationPromise = null
		/** Input stream */
		this._inputStream = null
		/** Output stream */
		this._outputStream = null
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

	_handleInputTrack(trackId, track) {
		if (this._enabled) {
			this._startEffect()
		} else {
			this._stopEffect()
		}
	}

	_handleInputTrackEnabled(trackId, enabled) {
		this._audioEnabled = enabled
		this._setOutputTrackEnabled('default', enabled)
	}

	_startEffect() {
		if (this._stopTimer) {
			clearTimeout(this._stopTimer)
			this._stopTimer = null
		}

		const track = this.getInputTrack()
		this._audioEnabled = track?.enabled ?? false
		if (!track) {
			this._stopEffect()
			return
		}

		if (!this._noiseSuppressionConsumer) {
			// Start initializing the worklet if not already in progress. While waiting, set default track as an output.
			if (!this._noiseSuppressionRegistrationPromise) {
				this._noiseSuppressionRegistrationPromise = registerNoiseSuppressionWorklet()
					.then((consumer) => {
						this._noiseSuppressionRegistrationPromise = null
						this._noiseSuppressionConsumer = consumer

						if (consumer && this._enabled) {
							this._startEffect()
						} else {
							this._stopEffect()
						}
					})
			}

			this._setOutputTrack('default', track)
			return
		}

		this._inputStream = new MediaStream([track])
		this._outputStream = processNoiseSuppression(this._inputStream, this._noiseSuppressionConsumer, true)
		const processedTrack = this._outputStream.getAudioTracks()[0]
		processedTrack.enabled = this._audioEnabled

		this._setOutputTrack('default', processedTrack)
	}

	_stopEffect() {
		if (!this._stopTimer) {
			this._stopTimer = setTimeout(async () => {
				if (this._noiseSuppressionConsumer) {
					await unregisterNoiseSuppressionWorklet(this._noiseSuppressionConsumer)
					this._noiseSuppressionConsumer = null
				}
				this._stopTimer = null
			}, 3_000)
		}

		const track = this.getInputTrack()
		this._setOutputTrack('default', track)
		this._inputStream = null
		this._outputStream?.getTracks().forEach((track) => {
			this._disableRemoveTrackWhenEnded(track)
			track.stop()
		})
		this._outputStream = null
	}
}
