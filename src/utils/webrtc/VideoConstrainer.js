/**
 *
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
 * @param {LocalMediaModel} localMediaModel the model for the local media.
 */
function VideoConstrainer(localMediaModel) {
	this._localMediaModel = localMediaModel

	// By default the constraints used when getting the video try to get the
	// highest quality
	this._currentQuality = QUALITY.HIGH

	this._knownValidConstraintsForQuality = {}
}
VideoConstrainer.prototype = {

	applyConstraints: async function(quality) {
		if (quality === this._currentQuality) {
			return
		}

		const localStream = this._localMediaModel.get('localStream')
		if (!localStream) {
			console.warn('No local stream to adjust its video quality found')
			return
		}

		const localVideoTracks = localStream.getVideoTracks()
		if (localVideoTracks.length === 0) {
			console.warn('No local video track to adjust its quality found')
			return
		}

		if (localVideoTracks.length > 1) {
			console.warn('More than one local video track to adjust its quality found: ' + localVideoTracks.length)
			return
		}

		await this._applyRoughConstraints(localVideoTracks[0], quality)

		// Quality may not actually match the default constraints, but it is the
		// best that can be done.
		this._currentQuality = quality
	},

	_applyRoughConstraints: async function(localVideoTrack, quality) {
		let constraints = this._knownValidConstraintsForQuality[quality]
		if (!constraints) {
			constraints = this._getConstraintsForQuality(quality)
		}

		try {
			await localVideoTrack.applyConstraints(constraints)

			this._knownValidConstraintsForQuality[quality] = constraints

			console.debug('Changed quality to ' + quality)
		} catch (error) {
			console.warn('Failed to set quality ' + quality, error)

			const resolutionConstraints = {
				width: constraints.width,
				height: constraints.height,
			}

			await this._applyRoughResolutionConstraints(localVideoTrack, resolutionConstraints)

			const frameRateConstraints = {
				width: constraints.width,
				height: constraints.height,
				frameRate: constraints.frameRate,
			}

			try {
				await this._applyRoughFrameRateConstraints(localVideoTrack, frameRateConstraints)

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

	_applyRoughResolutionConstraints: async function(localVideoTrack, constraints) {
		try {
			await localVideoTrack.applyConstraints(constraints)

			console.debug('Changed resolution', constraints)
		} catch (error) {
			console.warn('Failed to set resolution', constraints, error)

			if (!this._increaseMaxResolution(constraints) && !this._decreaseMinResolution(constraints)) {
				console.warn('Resolution range can not be further increased')
				throw error
			}

			this._applyRoughResolutionConstraints(localVideoTrack, constraints)
		}
	},

	_applyRoughFrameRateConstraints: async function(localVideoTrack, constraints) {
		try {
			await localVideoTrack.applyConstraints(constraints)

			console.debug('Changed frame rate', constraints)
		} catch (error) {
			console.warn('Failed to set frame rate', constraints, error)

			if (!this._increaseMaxFrameRate(constraints) && !this._decreaseMinFrameRate(constraints)) {
				console.warn('Frame rate range can not be further increased')
				throw error
			}

			this._applyRoughFrameRateConstraints(localVideoTrack, constraints)
		}
	},

	_getConstraintsForQuality: function(quality) {
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
		}
	},

	_increaseMaxResolution: function(constraints) {
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

	_decreaseMinResolution: function(constraints) {
		let changed = false

		if (constraints.width && constraints.width.min) {
			const previousWidthMin = constraints.width.min
			constraints.width.min = Math.min(Math.round(constraints.width.min / 1.5), 64)
			changed = previousWidthMin !== constraints.width.min
		}

		if (constraints.height && constraints.height.min) {
			const previousHeightMin = constraints.height.min
			constraints.height.min = Math.min(Math.round(constraints.height.min / 1.5), 64)
			changed = previousHeightMin !== constraints.height.min
		}

		return changed
	},

	_increaseMaxFrameRate: function(constraints) {
		let changed = false

		if (constraints.frameRate && constraints.frameRate.max) {
			const previousFrameRateMax = constraints.frameRate.max
			constraints.frameRate.max = Math.min(Math.round(constraints.frameRate.max * 1.5), 60)
			changed = previousFrameRateMax !== constraints.frameRate.max
		}

		return changed
	},

	_decreaseMinFrameRate: function(constraints) {
		let changed = false

		if (constraints.frameRate && constraints.frameRate.min) {
			const previousFrameRateMin = constraints.frameRate.max
			constraints.frameRate.min = Math.min(Math.round(constraints.frameRate.min / 1.5), 1)
			changed = previousFrameRateMin !== constraints.frameRate.min
		}

		return changed
	},

}

export {
	QUALITY,
	VideoConstrainer,
}
