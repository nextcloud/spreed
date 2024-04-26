/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import TrackSinkSource from './TrackSinkSource.js'

/**
 * Processor node to enforce zero-information-content on a disabled or ended
 * video track.
 *
 * A single input track slot with the default id is accepted. A single output
 * track slot with the default id is provided. The input track must be a video
 * track. The output track will be a video track.
 *
 * When the input track is enabled it is just bypassed to the output. However,
 * if the input track is disabled or stopped a black video track is generated
 * and set as the output instead; the black video track will be initially
 * enabled and later automatically disabled, unless anything changes in the
 * input and causes a different output track, even another black video track, to
 * be set (a previous black video track is not reused, a new one is always
 * generated). If the input track is removed the black video will be initially
 * set as the output too, but then it will be also removed instead of disabled.
 *
 *        --------------------
 *       |                    |
 *  ---> | BlackVideoEnforcer | --->
 *       |                    |
 *        --------------------
 */
export default class BlackVideoEnforcer extends TrackSinkSource {

	constructor() {
		super()

		this._addInputTrackSlot()
		this._addOutputTrackSlot()
	}

	_handleInputTrack(trackId, newTrack, oldTrack) {
		if (oldTrack && this._startBlackVideoWhenTrackEndedHandler) {
			oldTrack.removeEventListener('ended', this._startBlackVideoWhenTrackEndedHandler)
			this._startBlackVideoWhenTrackEndedHandler = null
		}

		if (newTrack) {
			this._disableRemoveTrackWhenEnded(newTrack)

			this._startBlackVideoWhenTrackEndedHandler = () => {
				this._startBlackVideo(newTrack.getSettings())
			}
			newTrack.addEventListener('ended', this._startBlackVideoWhenTrackEndedHandler)
		}

		this._stopBlackVideo()

		if (newTrack && newTrack.enabled) {
			this._setOutputTrack('default', this.getInputTrack())

			return
		}

		const trackSettings = newTrack ? newTrack.getSettings() : oldTrack?.getSettings()
		this._startBlackVideo(trackSettings)
	}

	_handleInputTrackEnabled(trackId, enabled) {
		// Same enabled state as before, nothing to do
		if ((enabled && !this._outputStream)
			|| (!enabled && this._outputStream)) {
			return
		}

		if (enabled) {
			this._stopBlackVideo()

			this._setOutputTrack('default', this.getInputTrack())

			return
		}

		if (this._outputStream) {
			this._setOutputTrackEnabled('default', false)

			return
		}

		this._startBlackVideo(this.getInputTrack().getSettings())
	}

	_startBlackVideo(trackSettings) {
		if (this._outputStream) {
			return
		}

		const { width, height } = trackSettings ?? { width: 640, height: 480 }

		const outputCanvasElement = document.createElement('canvas')
		outputCanvasElement.width = parseInt(width, 10)
		outputCanvasElement.height = parseInt(height, 10)
		const outputCanvasContext = outputCanvasElement.getContext('2d')

		this._outputStream = outputCanvasElement.captureStream()

		outputCanvasContext.fillStyle = 'black'
		outputCanvasContext.fillRect(0, 0, outputCanvasElement.width, outputCanvasElement.height)

		// Sometimes Chromium does not render one or more frames to the stream
		// captured from a canvas, so repeat the drawing several times for
		// several seconds to work around that.
		this._renderInterval = setInterval(() => {
			outputCanvasContext.fillRect(0, 0, outputCanvasElement.width, outputCanvasElement.height)
		}, 100)

		this._setOutputTrack('default', this._outputStream.getVideoTracks()[0])

		this._disableOrRemoveOutputTrackTimeout = setTimeout(() => {
			clearTimeout(this._disableOrRemoveOutputTrackTimeout)
			this._disableOrRemoveOutputTrackTimeout = null

			clearInterval(this._renderInterval)
			this._renderInterval = null

			if (this.getInputTrack()) {
				this._setOutputTrackEnabled('default', false)
			} else {
				this._stopBlackVideo()
				this._setOutputTrack('default', null)
			}
		}, 5000)
	}

	_stopBlackVideo() {
		if (!this._outputStream) {
			return
		}

		clearTimeout(this._disableOrRemoveOutputTrackTimeout)
		this._disableOrRemoveOutputTrackTimeout = null

		clearInterval(this._renderInterval)
		this._renderInterval = null

		this._outputStream.getTracks().forEach(track => {
			this._disableRemoveTrackWhenEnded(track)

			track.stop()
		})

		this._outputStream = null
	}

}
