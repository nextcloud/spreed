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

		const constraints = this._getConstraintsForQuality(quality)

		try {
			await localVideoTracks[0].applyConstraints(constraints)
			console.debug('Changed quality to ' + quality)
			this._currentQuality = quality
		} catch (error) {
			console.warn('Failed to set quality ' + quality, error)
			throw error
		}
	},

	_getConstraintsForQuality: function(quality) {
		if (quality === QUALITY.HIGH) {
			return {
				video: true,
				// The frame rate needs to be explicitly set; otherwise the
				// browser may keep the previous stream when changing to a laxer
				// constraint.
				frameRate: {
					max: 30,
				},
			}
		}

		if (quality === QUALITY.MEDIUM) {
			return {
				width: {
					max: 640,
				},
				height: {
					max: 480,
				},
				frameRate: {
					max: 24,
				},
			}
		}

		if (quality === QUALITY.LOW) {
			return {
				width: {
					max: 480,
				},
				height: {
					max: 320,
				},
				frameRate: {
					max: 15,
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

}

export {
	QUALITY,
	VideoConstrainer,
}
