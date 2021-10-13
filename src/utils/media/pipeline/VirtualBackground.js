/**
 *
 * @copyright Copyright (c) 2021, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @copyright Copyright (c) 2021, Immanuel Pasanec (immanuel.pasanec@compaso.de)
 *
 * @license AGPL-3.0-or-later
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

import TrackSinkSource from './TrackSinkSource'
import { VIRTUAL_BACKGROUND_TYPE } from '../effects/virtual-background/constants'
import JitsiStreamBackgroundEffect from '../effects/virtual-background/JitsiStreamBackgroundEffect'

/**
 * Processor node to set a virtual background on a video track.
 *
 * A single input track slot with the default id is accepted. The input track
 * must be a video track. A single output track slot with the default id is
 * provided. The output track will be a video track.
 *
 * The virtual background node requires Web Assembly to be enabled in the
 * browser as well as support for canvas filters. Whether the virtual background
 * is supported or not can be checked by calling
 * "VirtualBackground.isSupported()". If a virtual background node is tried to
 * be used when it is not supported its input will be just bypassed to its
 * output.
 *
 * The virtual background is automatically stopped and started again when the
 * input track is disabled and enabled (which changes the output track). The
 * virtual background will be restarted whenever an input track is set in order
 * to refresh the data if the track constraints, like its width or height,
 * change.
 *
 *        -------------------
 *       |                   |
 *  ---> | VirtualBackground | --->
 *       |                   |
 *        -------------------
 */
export default class VirtualBackground extends TrackSinkSource {

	static _wasmSupported
	static _wasmSimd
	static _canvasFilterSupported

	static isSupported() {
		return this._isWasmSupported() && this._isCanvasFilterSupported()
	}

	static _isWasmSupported() {
		if (this._wasmSupported === undefined) {
			try {
				const wasmCheck = require('wasm-check')
				this._wasmSupported = true

				if (wasmCheck?.feature?.simd) {
					this._wasmSimd = true
				} else {
					this._wasmSimd = false
				}
			} catch (error) {
				this._wasmSupported = false

				console.error('Looks like WebAssembly is disabled or not supported on this browser, virtual background will not be available')
			}
		}

		return this._wasmSupported
	}

	static _isCanvasFilterSupported() {
		if (this._canvasFilterSupported === undefined) {
			const canvas = document.createElement('canvas')
			const context = canvas.getContext('2d')

			this._canvasFilterSupported = context.filter !== undefined

			canvas.remove()
		}

		return this._canvasFilterSupported
	}

	constructor() {
		super()

		this._addInputTrackSlot()
		this._addOutputTrackSlot()

		this._initJitsiStreamBackgroundEffect()

		// JitsiStreamBackgroundEffect works with tracks internally, but
		// requires and provides streams externally
		this._inputStream = null
		this._outputStream = null

		this._enabled = true
	}

	_initJitsiStreamBackgroundEffect() {
		const segmentationDimensions = {
			model96: {
				height: 96,
				width: 160,
			},
			model144: {
				height: 144,
				width: 256,
			},
		}

		if (!VirtualBackground._isWasmSupported()) {
			return
		}

		const isSimd = VirtualBackground._wasmSimd

		const virtualBackground = {
			type: VIRTUAL_BACKGROUND_TYPE.NONE,
			blurValue: 32,
		}
		const options = {
			...isSimd ? segmentationDimensions.model144 : segmentationDimensions.model96,
			virtualBackground,
			simd: isSimd,
		}

		this._jitsiStreamBackgroundEffect = new JitsiStreamBackgroundEffect(options)
	}

	isEnabled() {
		return this._enabled
	}

	setEnabled(enabled) {
		if (this.enabled === enabled) {
			return
		}

		this._enabled = enabled

		if (!enabled) {
			this._stopEffect()

			// If not enabled the input track is just bypassed to the output.
			this._setOutputTrack('default', this.getInputTrack())

			return
		}

		if (!this.getInputTrack()) {
			return
		}

		this._startEffect()
	}

	_handleInputTrack(trackId, newTrack, oldTrack) {
		// If not supported or enabled the input track is just bypassed to the
		// output.
		if (!VirtualBackground.isSupported() || !this._enabled) {
			this._setOutputTrack('default', newTrack)

			return
		}

		this._stopEffect()

		if (!newTrack) {
			this._setOutputTrack('default', null)

			return
		}

		this._startEffect()
	}

	_handleInputTrackEnabled(trackId, enabled) {
		// If not supported or enabled the input track is just bypassed to the
		// output.
		if (!VirtualBackground.isSupported() || !this._enabled) {
			this._setOutputTrackEnabled('default', enabled)

			return
		}

		// Stop and resume the effect if the track is disabled and enabled, as
		// there is no need to apply the effect (and consume CPU) on a disabled
		// track.
		if (!enabled) {
			this._stopEffect()

			this._setOutputTrack('default', this.getInputTrack())

			return
		}

		this._startEffect()
	}

	_startEffect() {
		if (this._inputStream) {
			return
		}

		this._inputStream = new MediaStream()
		this._inputStream.addTrack(this.getInputTrack())

		this._outputStream = this._jitsiStreamBackgroundEffect.startEffect(this._inputStream)

		this._setOutputTrack('default', this._outputStream.getVideoTracks()[0])
	}

	_stopEffect() {
		if (!this._outputStream) {
			return
		}

		this._jitsiStreamBackgroundEffect.stopEffect()
		this._outputStream.getTracks().forEach(track => {
			track.stop()
		})

		this._inputStream = null
		this._outputStream = null
	}

}
