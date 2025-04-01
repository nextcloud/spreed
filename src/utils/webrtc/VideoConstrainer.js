/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const QUALITY = {
	THUMBNAIL: 0,
	VERY_LOW: 1,
	LOW: 2,
	MEDIUM: 3,
	HIGH: 4,
}

/**
 * Helper to adjust the quality of a video stream.
 *
 * Despite having a common API, different browsers handle media constraints in
 * different ways. For example, if a video is constrained to a low maximum
 * resolution but then the constraint is relaxed with a higher maximum
 * resolution some browsers will increase the resolution, but others will just
 * stays with the previous resolution, as it still matches the constraints. In
 * other cases, setting a low frame rate may fail, but it may work if given a
 * little more room. This class tries to abstract all those details and provide
 * a simple interface to set the constraints based on some general quality
 * description.
 *
 * @param {object} trackConstrainer the track constrainer node on which apply
 *        the constraints.
 */
function VideoConstrainer(trackConstrainer) {
	this._trackConstrainer = trackConstrainer

	// The current quality is undefined until the constraints are applied at
	// least once.
	this._currentQuality = undefined

	this._knownValidConstraintsForQuality = {}
}
VideoConstrainer.prototype = {

	async applyConstraints(quality) {
		if (this._pendingApplyConstraintsCount) {
			console.debug('Deferring applying constraints for quality ' + quality)

			this._pendingApplyConstraintsCount++

			this._lastPendingQuality = quality

			return
		}

		this._pendingApplyConstraintsCount = 1

		// As "_applyConstraints" is asynchronous even if the current quality is
		// the same as the given one the call will not immediately return. Due
		// to this, even if the "applyConstraints(quality)" is called several
		// times in a row with the current quality the calls will still be
		// deferred, but this should not be a problem.
		await this._applyConstraints(quality)

		this._resetPendingApplyConstraintsCount()
	},

	_resetPendingApplyConstraintsCount() {
		const applyConstraintsAgain = this._pendingApplyConstraintsCount > 1

		this._pendingApplyConstraintsCount = 0

		if (applyConstraintsAgain) {
			this.applyConstraints(this._lastPendingQuality)
		}
	},

	async _applyConstraints(quality) {
		if (quality === this._currentQuality) {
			return
		}

		if (!this._trackConstrainer.getOutputTrack() || this._trackConstrainer.getOutputTrack().kind !== 'video') {
			console.warn('No video track to adjust its quality found')
			return
		}

		await this._applyRoughConstraints(this._trackConstrainer, quality)

		// Quality may not actually match the default constraints, but it is the
		// best that can be done.
		this._currentQuality = quality
	},

	async _applyRoughConstraints(trackConstrainer, quality) {
		let constraints = this._knownValidConstraintsForQuality[quality]
		if (!constraints) {
			constraints = this._getConstraintsForQuality(quality)
		}

		try {
			await trackConstrainer.applyConstraints(constraints)

			this._knownValidConstraintsForQuality[quality] = constraints

			console.debug('Changed quality to %d', quality)
		} catch (error) {
			console.warn('Failed to set quality %d', quality, error)

			const resolutionConstraints = {
				width: constraints.width,
				height: constraints.height,
			}

			await this._applyRoughResolutionConstraints(trackConstrainer, resolutionConstraints)

			const frameRateConstraints = {
				width: constraints.width,
				height: constraints.height,
				frameRate: constraints.frameRate,
			}

			try {
				await this._applyRoughFrameRateConstraints(trackConstrainer, frameRateConstraints)

				this._knownValidConstraintsForQuality[quality] = frameRateConstraints
			} catch (error) {
				// Frame rate could not be changed, but at least resolution
				// was. Do not fail in that case and settle for this little
				// victory.
				this._knownValidConstraintsForQuality[quality] = resolutionConstraints
			}

			console.debug('Changed quality to ' + quality)
		}
	},

	async _applyRoughResolutionConstraints(trackConstrainer, constraints) {
		try {
			await trackConstrainer.applyConstraints(constraints)

			console.debug('Changed resolution', constraints)
		} catch (error) {
			console.warn('Failed to set resolution', constraints, error)

			if (!this._increaseMaxResolution(constraints) && !this._decreaseMinResolution(constraints)) {
				console.warn('Resolution range can not be further increased')
				throw error
			}

			this._applyRoughResolutionConstraints(trackConstrainer, constraints)
		}
	},

	async _applyRoughFrameRateConstraints(trackConstrainer, constraints) {
		try {
			await trackConstrainer.applyConstraints(constraints)

			console.debug('Changed frame rate', constraints)
		} catch (error) {
			console.warn('Failed to set frame rate', constraints, error)

			if (!this._increaseMaxFrameRate(constraints) && !this._decreaseMinFrameRate(constraints)) {
				console.warn('Frame rate range can not be further increased')
				throw error
			}

			this._applyRoughFrameRateConstraints(trackConstrainer, constraints)
		}
	},

	_getConstraintsForQuality(quality) {
		if (quality === QUALITY.HIGH) {
			return {
				width: {
					ideal: 720,
					min: 640,
				},
				height: {
					ideal: 540,
					min: 480,
				},
				frameRate: {
					max: 30,
					ideal: 30,
					min: 20,
				},
				resizeMode: 'none',
			}
		}

		if (quality === QUALITY.MEDIUM) {
			return {
				width: {
					max: 640,
					ideal: 560,
					min: 480,
				},
				height: {
					max: 480,
					ideal: 420,
					min: 320,
				},
				frameRate: {
					max: 24,
					ideal: 24,
					min: 15,
				},
				resizeMode: 'none',
			}
		}

		if (quality === QUALITY.LOW) {
			return {
				width: {
					max: 480,
					ideal: 360,
					min: 320,
				},
				height: {
					max: 320,
					ideal: 270,
					min: 240,
				},
				frameRate: {
					max: 15,
					ideal: 15,
					min: 8,
				},
				resizeMode: 'none',
			}
		}

		if (quality === QUALITY.VERY_LOW) {
			return {
				width: {
					max: 320,
				},
				height: {
					max: 240,
				},
				frameRate: {
					max: 8,
				},
				resizeMode: 'none',
			}
		}

		return {
			width: {
				max: 320,
			},
			height: {
				max: 240,
			},
			frameRate: {
				max: 1,
			},
			resizeMode: 'none',
		}
	},

	_increaseMaxResolution(constraints) {
		let changed = false

		if (constraints.width && constraints.width.max) {
			const previousWidthMax = constraints.width.max
			constraints.width.max = Math.min(Math.round(constraints.width.max * 1.5), 1920)
			changed = previousWidthMax !== constraints.width.max
		}

		if (constraints.height && constraints.height.max) {
			const previousHeightMax = constraints.height.max
			constraints.height.max = Math.min(Math.round(constraints.height.max * 1.5), 1080)
			changed = previousHeightMax !== constraints.height.max
		}

		return changed
	},

	_decreaseMinResolution(constraints) {
		let changed = false

		if (constraints.width && constraints.width.min) {
			const previousWidthMin = constraints.width.min
			constraints.width.min = Math.max(Math.round(constraints.width.min / 1.5), 64)
			changed = previousWidthMin !== constraints.width.min
		}

		if (constraints.height && constraints.height.min) {
			const previousHeightMin = constraints.height.min
			constraints.height.min = Math.max(Math.round(constraints.height.min / 1.5), 64)
			changed = previousHeightMin !== constraints.height.min
		}

		return changed
	},

	_increaseMaxFrameRate(constraints) {
		let changed = false

		if (constraints.frameRate && constraints.frameRate.max) {
			const previousFrameRateMax = constraints.frameRate.max
			constraints.frameRate.max = Math.min(Math.round(constraints.frameRate.max * 1.5), 60)
			changed = previousFrameRateMax !== constraints.frameRate.max
		}

		return changed
	},

	_decreaseMinFrameRate(constraints) {
		let changed = false

		if (constraints.frameRate && constraints.frameRate.min) {
			const previousFrameRateMin = constraints.frameRate.min
			constraints.frameRate.min = Math.max(Math.round(constraints.frameRate.min / 1.5), 1)
			changed = previousFrameRateMin !== constraints.frameRate.min
		}

		return changed
	},

}

export {
	QUALITY,
	VideoConstrainer,
}
