/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import * as wasmCheck from 'wasm-check'
import { VIRTUAL_BACKGROUND } from '../../../constants.ts'
import JitsiStreamBackgroundEffect from '../effects/virtual-background/JitsiStreamBackgroundEffect.js'
import TrackSinkSource from './TrackSinkSource.js'

/**
 * Processor node to set a virtual background on a video track.
 *
 * A single input track slot with the default id is accepted. The input track
 * must be a video track. A single output track slot with the default id is
 * provided. The output track will be a video track.
 *
 * The virtual background node requires Web Assembly to be enabled in the
 * browser as well as support for canvas filters. Whether the virtual background
 * is available or not can be checked by calling
 * "VirtualBackground.isSupported()". Besides that, it needs to
 * download and compile a Tensor Flow Lite model; until the model has not
 * finished loading or failed to load it is not possible to know for sure if
 * virtual background is available, so if it is supported it is assumed to be
 * available unless the model fails to load. In that case "loadFailed" is
 * emitted and the virtual background is disabled. Whether the virtual
 * background is available or not can be checked by calling "isAvailable()" on
 * the object. If a virtual background node is tried to be used when it is not
 * available its input will be just bypassed to its output.
 *
 * The virtual background is automatically stopped and started again when the
 * input track is disabled and enabled (which changes the output track). The
 * virtual background will be restarted whenever an input track is set in order
 * to refresh the data if the track constraints, like its width or height,
 * change.
 *
 * The background can be the real background, but blurred, or an image or video
 * that fully replaces the real background. By default a blurred background is
 * used, but this can be changed by calling "setVirtualBackground()".
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
		return this.isWasmSupported() && this.isCanvasFilterSupported()
	}

	static _checkWasmSupport() {
		if (!wasmCheck.support()) {
			this._wasmSupported = false

			console.error('Looks like WebAssembly is disabled or not supported on this browser, virtual background will not be available')

			return
		}

		this._wasmSupported = true

		this._wasmSimd = wasmCheck.feature.simd
	}

	static isWasmSupported() {
		if (this._wasmSupported === undefined) {
			this._checkWasmSupport()
		}

		return this._wasmSupported
	}

	/**
	 * Returns whether SIMD instructions are available in WebAssembly or not.
	 *
	 * @return {boolean} undefined if WebAssembly is not supported, true if SIMD
	 *         instructions are available in WebAssembly, or false otherwise.
	 */
	static isWasmSimd() {
		if (this._wasmSupported === undefined) {
			this._checkWasmSupport()
		}

		return this._wasmSimd
	}

	static isCanvasFilterSupported() {
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
			modelLandscape: {
				height: 144,
				width: 256,
			},
		}

		if (!VirtualBackground.isWasmSupported()) {
			return
		}

		const isSimd = VirtualBackground.isWasmSimd()

		const virtualBackground = {
			backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
			blurValue: VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT,
		}
		const options = {
			...segmentationDimensions.modelLandscape,
			virtualBackground,
			simd: isSimd,
		}

		this._jitsiStreamBackgroundEffect = new JitsiStreamBackgroundEffect(options)
		this._jitsiStreamBackgroundEffect.load().catch(() => {
			this._trigger('loadFailed')

			this.setEnabled(false)
		})
	}

	isAvailable() {
		if (!VirtualBackground.isSupported()) {
			return false
		}

		// If VirtualBackground is supported it is assumed to be available
		// unless the load has failed (so it is seen as available even when
		// still loading).
		return !this._jitsiStreamBackgroundEffect.didLoadFail()
	}

	isEnabled() {
		return this._enabled
	}

	setEnabled(enabled) {
		if (!this.isAvailable()) {
			enabled = false
		}

		if (this.enabled === enabled) {
			return
		}

		this._enabled = enabled

		if (!enabled) {
			this._stopEffect()

			// If not enabled the input track is just bypassed to the output.
			if (this.getOutputTrack() !== this.getInputTrack()) {
				this._setOutputTrack('default', this.getInputTrack())
			}

			return
		}

		if (!this.getInputTrack() || !this.getInputTrack().enabled) {
			return
		}

		this._startEffect()
	}

	_handleInputTrack(trackId, newTrack, oldTrack) {
		// If not available or enabled the input track is just bypassed to the
		// output.
		if (!this.isAvailable() || !this._enabled) {
			this._setOutputTrack('default', newTrack)

			return
		}

		if (newTrack === oldTrack && newTrack !== null && newTrack.enabled) {
			this._jitsiStreamBackgroundEffect.updateInputStream()

			return
		}

		this._stopEffect()

		if (!newTrack || !newTrack.enabled) {
			this._setOutputTrack('default', this.getInputTrack())

			return
		}

		this._startEffect()
	}

	_handleInputTrackEnabled(trackId, enabled) {
		// If not available or enabled the input track is just bypassed to the
		// output.
		if (!this.isAvailable() || !this._enabled) {
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
		this._outputStream.getTracks().forEach((track) => {
			this._disableRemoveTrackWhenEnded(track)

			track.stop()
		})

		this._inputStream = null
		this._outputStream = null
	}

	/**
	 * Gets the virtual background properties.
	 *
	 * @return {object|undefined} undefined if WebAssembly is not supported, an object
	 *         with the virtual background properties otherwise.
	 */
	getVirtualBackground() {
		if (!this.isAvailable()) {
			return undefined
		}

		return this._jitsiStreamBackgroundEffect.getVirtualBackground()
	}

	/**
	 * Sets the virtual background properties.
	 *
	 * Nothing is set if the virtual background is not available.
	 *
	 * @param {object} virtualBackground the virtual background properties; see
	 *        JitsiStreamBackgroundEffect.setVirtualBackground().
	 */
	setVirtualBackground(virtualBackground) {
		if (!this.isAvailable()) {
			return
		}

		this._jitsiStreamBackgroundEffect.setVirtualBackground(virtualBackground)
	}

	/**
	 * Destroys the VirtualBackground instance and releases all resources.
	 */
	destroy() {
		this._stopEffect()
		this._jitsiStreamBackgroundEffect.destroy()
		this._jitsiStreamBackgroundEffect = null
	}
}
