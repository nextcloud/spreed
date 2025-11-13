/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { FilesetResolver, ImageSegmenter } from '@mediapipe/tasks-vision'
import { generateFilePath } from '@nextcloud/router'
import { VIRTUAL_BACKGROUND } from '../../../../constants.ts'
import {
	CLEAR_TIMEOUT,
	SET_TIMEOUT,
	TIMEOUT_TICK,
	timerWorkerScript,
} from './TimerWorker.js'
import WebGLCompositor from './WebGLCompositor.js'

// Cache MediaPipe resources to avoid loading them multiple times.
let _WasmFileset = null

/**
 * Represents a modified MediaStream that applies virtual background effects
 * (blur, image, video, or video stream) using MediaPipe segmentation.
 *
 * @class
 */
export default class VideoStreamBackgroundEffect {
	// _options: Object;
	// _stream: MediaStream;
	// _segmentationPixelCount: number;
	// _inputVideoElement: HTMLVideoElement;
	// _onMaskFrameTimer: Function;
	// _maskFrameTimerWorker: Worker;
	// _outputCanvasElement: HTMLCanvasElement;
	// _outputCanvasCtx: CanvasRenderingContext2D;
	// _segmentationMaskCtx: CanvasRenderingContext2D;
	// _segmentationMask: ImageData;
	// _segmentationMaskCanvas: HTMLCanvasElement;
	// _renderMask: Function;
	// _virtualImage: HTMLImageElement;
	// _virtualVideo: HTMLVideoElement;

	/**
	 * Create a new background effect processor.
	 *
	 * @param {object} options - Options for the effect.
	 * @param {number} options.width - Segmentation mask width.
	 * @param {number} options.height - Segmentation mask height.
	 * @param {object} options.virtualBackground - Virtual background properties (see setVirtualBackground()).
	 * @param {boolean} options.webGL - Whether to use WebGL compositor instead of 2D canvas.
	 */
	constructor(options) {
		this._options = options
		this._loadPromise = new Promise((resolve, reject) => {
			this._loadPromiseResolve = resolve
			this._loadPromiseReject = reject
		})
		this._loaded = false
		this._loadFailed = false

		this._isFirstBgChange = true
		this.setVirtualBackground(this._options.virtualBackground)
		this._useWebGL = this._options.webGL

		this._segmentationPixelCount = this._options.width * this._options.height

		this._initMediaPipe().catch((e) => console.error(e))

		// Bind event handler so it is only bound once for every instance.
		this._onMaskFrameTimer = this._onMaskFrameTimer.bind(this)
		this._renderMask = this._renderMask.bind(this)

		// caches for mask processing
		this._tempImageData = null
		this._maskWidth = 0
		this._maskHeight = 0

		// Create canvas elements
		this._outputCanvasElement = document.createElement('canvas')
		if (!this._useWebGL) {
			this._outputCanvasElement.getContext('2d')
		}
		this._inputVideoElement = document.createElement('video')
		this._bgChanged = false
		this._prevBgMode = null
	}

	/**
	 * Initialize MediaPipe segmentation model.
	 *
	 * @private
	 * @return {Promise<void>}
	 */
	async _initMediaPipe() {
		try {
			/**
			 * Creates a fileset for the MediaPipe Vision tasks (object with paths to binaries)
			 * Checks inside, if SIMD is supported to load the appropriate fileset
			 */
			if (!_WasmFileset) {
				if (await FilesetResolver.isSimdSupported()) {
					_WasmFileset = {
						wasmLoaderPath: new URL(
							'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_internal.js',
							import.meta.url,
						).pathname,
						wasmBinaryPath: new URL(
							'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_internal.wasm',
							import.meta.url,
						).pathname,
					}
				} else {
					_WasmFileset = {
						wasmLoaderPath: new URL(
							'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_nosimd_internal.js',
							import.meta.url,
						).pathname,
						wasmBinaryPath: new URL(
							'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_nosimd_internal.wasm',
							import.meta.url,
						).pathname,
					}
				}
			}

			/**
			 * Loads binaries and create an image segmentation TaskRunner
			 */
			this._imageSegmenter = await ImageSegmenter.createFromOptions(_WasmFileset, {
				baseOptions: {
					modelAssetPath: new URL(
						'./vendor/models/selfie_segmenter.tflite',
						import.meta.url,
					).pathname,
					delegate: 'GPU',
				},
				runningMode: 'VIDEO',
				outputCategoryMask: false,
				outputConfidenceMasks: true,
			})

			this._loaded = true
			this._loadPromiseResolve()
		} catch (error) {
			console.error('MediaPipe Tasks initialization failed:', error)
			this._loadFailed = true
			this._loadPromiseReject(error)
		}
	}

	/**
	 * Run segmentation inference on the current video frame.
	 *
	 * @private
	 * @return {Promise<void>}
	 */
	async _runInference() {
		if (!this._imageSegmenter || !this._loaded) {
			return
		}

		let segmentationResult
		try {
			segmentationResult = await this._imageSegmenter.segmentForVideo(
				this._inputVideoElement,
				performance.now(),
			)

			if (segmentationResult.confidenceMasks && segmentationResult.confidenceMasks.length > 0) {
				this._processSegmentationResult(segmentationResult)
			}

			this.runPostProcessing()
			this._lastFrameId = this._frameId
		} catch (error) {
			console.error('MediaPipe inference failed:', error)
		} finally {
			if (segmentationResult?.categoryMask) {
				segmentationResult.categoryMask.close()
			}

			if (segmentationResult?.confidenceMasks?.length) {
				segmentationResult.confidenceMasks.forEach((mask) => mask.close())
			}
		}
	}

	/**
	 * Process MediaPipe segmentation result and update internal mask.
	 *
	 * @private
	 * @param {object} segmentationResult - The segmentation result from MediaPipe.
	 * @return {void}
	 */
	_processSegmentationResult(segmentationResult) {
		const confidenceMasks = segmentationResult.confidenceMasks
		if (!confidenceMasks || confidenceMasks.length === 0) {
			return
		}

		const mask = confidenceMasks[0]
		const maskData = !this._useWebGL ? mask.getAsFloat32Array() : mask
		const maskWidth = mask.width
		const maskHeight = mask.height

		if (!this._useWebGL) {
			// Prepare backing ImageData
			if (!this._segmentationMask
				|| this._segmentationMask.width !== this._options.width
				|| this._segmentationMask.height !== this._options.height) {
				this._segmentationMask = new ImageData(this._options.width, this._options.height)
			}

			// Convert float32 mask [0..1] → grayscale canvas
			if (this._tempCanvas.width !== maskWidth || this._tempCanvas.height !== maskHeight) {
				this._tempCanvas.width = maskWidth
				this._tempCanvas.height = maskHeight
			}
			const tempCanvas = this._tempCanvas
			const tempCtx = this._tempCanvasCtx

			if (!this._tempImageData
				|| this._maskWidth !== maskWidth
				|| this._maskHeight !== maskHeight) {
				this._tempImageData = new ImageData(maskWidth, maskHeight)
				this._maskWidth = maskWidth
				this._maskHeight = maskHeight
			}
			for (let i = 0; i < maskData.length; i++) {
				const v = Math.min(1.0, Math.max(0.0, maskData[i])) // clamp
				const gray = Math.round(v * 255)
				const idx = i * 4
				this._tempImageData.data[idx] = gray
				this._tempImageData.data[idx + 1] = gray
				this._tempImageData.data[idx + 2] = gray
				this._tempImageData.data[idx + 3] = 255
			}
			tempCtx.putImageData(this._tempImageData, 0, 0)

			// Resize into segmentation canvas
			this._segmentationMaskCtx.drawImage(
				tempCanvas,
				0,
				0,
				maskWidth,
				maskHeight,
				0,
				0,
				this._options.width,
				this._options.height,
			)

			// Extract resized alpha channel into _segmentationMask
			const resized = this._segmentationMaskCtx.getImageData(0, 0, this._options.width, this._options.height)
			for (let i = 0; i < this._segmentationPixelCount; i++) {
				this._segmentationMask.data[i * 4 + 3] = resized.data[i * 4] // R channel
			}

			// Update segmentation mask canvas
			this._segmentationMaskCtx.putImageData(this._segmentationMask, 0, 0)
		} else {
			this._lastMask = maskData
		}
	}

	/**
	 * Loop function to render the background mask and trigger inference.
	 *
	 * @private
	 * @return {void}
	 */
	_renderMask() {
		if (this._frameId < this._lastFrameId) {
			console.debug('Fixing frame id, this should not happen', this._frameId, this._lastFrameId)
			this._frameId = this._lastFrameId
		}

		// Run inference if ready
		if (this._loaded && this._frameId === this._lastFrameId) {
			this._frameId++
			this._runInference().catch((e) => console.error(e))
		} else if (this._useWebGL) {
			this.runPostProcessing()
		}

		// Schedule next frame
		this._maskFrameTimerWorker.postMessage({
			id: SET_TIMEOUT,
			timeMs: 1000 / this._frameRate,
			message: 'this._maskFrameTimerWorker',
		})
	}

	/**
	 * Handle timer worker ticks to schedule mask rendering.
	 *
	 * @private
	 * @param {MessageEvent} response - Message from the worker.
	 * @return {void}
	 */
	_onMaskFrameTimer(response) {
		if (response.data.id === TIMEOUT_TICK) {
			this._renderMask()
		}
	}

	/**
	 * Helper method to know when the model was loaded after creating the
	 * object.
	 *
	 * Note that it is not needed to call this method to actually load the
	 * effect; the load will automatically start as soon as the object is
	 * created, but it can be waited on this method to know once it has finished
	 * (or failed).
	 *
	 * @return {Promise} promise resolved or rejected once the load has finished
	 *         or failed.
	 */
	async load() {
		return this._loadPromise
	}

	/**
	 * Returns whether loading the TFLite model failed or not.
	 *
	 * @return {boolean} true if loading failed, false otherwise
	 */
	didLoadFail() {
		return this._loadFailed
	}

	/**
	 * Returns the virtual background properties.
	 *
	 * @return {object} the virtual background properties.
	 */
	getVirtualBackground() {
		return this._options.virtualBackground
	}

	/**
	 * Sets the virtual background properties to use.
	 *
	 * The virtual background can be modified while the effect is running.
	 *
	 * If an image or video URL is given it can be any URL accepted by the "src"
	 * attribute of HTML image or video elements, so it is possible to set a
	 * "real" URL or, for example, one generated with "URL.createObjectURL()".
	 *
	 * @param {object} virtualBackground an object with the virtual background
	 *        properties.
	 * @param {string} virtualBackground.backgroundType BLUR, IMAGE, VIDEO or
	 *        VIDEO_STREAM.
	 * @param {number} virtualBackground.blurValue the blur to apply on a 720p
	 *        video; it will be automatically scaled as needed.
	 *        Optional, only needed when background type is BLUR.
	 * @param {string|MediaStream} virtualBackground.virtualSource the URL to
	 *        the image or video, or a video stream.
	 *        Optional, only needed when background type is IMAGE, VIDEO or
	 *        VIDEO_STREAM.
	 */
	setVirtualBackground(virtualBackground) {
		if (this._options.virtualBackground.backgroundType !== this._options.virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE || (this._virtualImage?.complete && this._virtualImage?.naturalWidth > 0)) {
			this._prevBgMode = !this._isFirstBgChange ? this._options.virtualBackground.backgroundType : null
		}
		// Clear previous elements to allow them to be garbage collected
		this._virtualImage = null
		this._virtualVideo = null
		this._bgChanged = false
		this._isFirstBgChange = false
		this._options.virtualBackground = virtualBackground

		if (this._options.virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
			this._virtualImage = new Image()
			this._virtualImage.onload = () => {
				this._bgChanged = true
			}
			this._virtualImage.src = this._options.virtualBackground.virtualSource

			return
		}

		if (this._options.virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO) {
			this._virtualVideo = document.createElement('video')
			this._virtualVideo.crossOrigin = 'anonymous'
			this._virtualVideo.loop = true
			this._virtualVideo.muted = true
			this._virtualVideo.src = this._options.virtualBackground.virtualSource

			if (this._running) {
				this._virtualVideo.play()
			}

			return
		}

		if (this._options.virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO_STREAM) {
			this._virtualVideo = document.createElement('video')
			this._virtualVideo.srcObject = this._options.virtualBackground.virtualSource

			if (this._running) {
				this._virtualVideo.play()
			}
		}
	}

	/**
	 * Run background/foreground compositing.
	 *
	 * @return {void}
	 */
	runPostProcessing() {
		const height = this._inputVideoElement.videoHeight
		const width = this._inputVideoElement.videoWidth
		const { backgroundType } = this._options.virtualBackground

		const scaledBlurFactor = width / 720.0
		const backgroundBlurValue = this._options.virtualBackground.blurValue * scaledBlurFactor
		const edgesBlurValue = (backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE ? 4 : 8) * scaledBlurFactor

		if (!this._outputCanvasElement.width
			|| !this._outputCanvasElement.height) {
			return
		}

		this._outputCanvasElement.width = width
		this._outputCanvasElement.height = height

		if (this._useWebGL) {
			if (!this._glFx) {
				return
			}

			let mode = -1
			let bgSource = null
			let refreshBg = false

			if (this._lastMask) {
				mode = 1
				if (backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE
					|| backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO
					|| backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO_STREAM) {
					mode = 0
					if (backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
						if (this._virtualImage?.complete && this._virtualImage?.naturalWidth > 0) {
							// The background image is loaded, perform normal compositing and refresh the background if the image has changed
							bgSource = this._virtualImage
							refreshBg = this._bgChanged
							if (refreshBg) {
								this._bgChanged = false
							}
						} else if (this._prevBgMode === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR) {
							// The background image is not loaded yet, so keep using blur since this was the last effect used
							mode = 1
						} else if (this._prevBgMode === null) {
							// The background image is not loaded yet and there was no effect before, so just render the video as is
							mode = -1
						}
					} else {
						bgSource = this._virtualVideo
						refreshBg = true
					}
				}
			}

			this._glFx.render({
				videoEl: this._inputVideoElement,
				mask: this._lastMask,
				bgSource,
				mode,
				outW: width,
				outH: height,
				edgeFeatherPx: edgesBlurValue,
				refreshBg,
				showProgress: !this._lastMask || (backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE && !this._virtualImage?.complete),
			})
		} else {
			this._outputCanvasCtx.globalCompositeOperation = 'copy'

			// Draw segmentation mask.

			// Smooth out the edges.
			this._outputCanvasCtx.filter = `blur(${edgesBlurValue}px)`
			this._outputCanvasCtx.drawImage(
				this._segmentationMaskCanvas,
				0,
				0,
				this._options.width,
				this._options.height,
				0,
				0,
				this._inputVideoElement.videoWidth,
				this._inputVideoElement.videoHeight,
			)
			this._outputCanvasCtx.globalCompositeOperation = 'source-in'
			this._outputCanvasCtx.filter = 'none'

			// Draw the foreground video.

			this._outputCanvasCtx.drawImage(this._inputVideoElement, 0, 0)

			// Draw the background.

			this._outputCanvasCtx.globalCompositeOperation = 'destination-over'
			if (backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE
				|| backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO
				|| backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO_STREAM) {
				let source
				let sourceWidthOriginal
				let sourceHeightOriginal

				if (backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
					source = this._virtualImage
					sourceWidthOriginal = source.naturalWidth
					sourceHeightOriginal = source.naturalHeight
				} else {
					source = this._virtualVideo
					sourceWidthOriginal = source.videoWidth
					sourceHeightOriginal = source.videoHeight
				}

				const destinationWidth = this._outputCanvasElement.width
				const destinationHeight = this._outputCanvasElement.height

				const [sourceX, sourceY, sourceWidth, sourceHeight] = VideoStreamBackgroundEffect.getSourcePropertiesForDrawingBackgroundImage(sourceWidthOriginal, sourceHeightOriginal, destinationWidth, destinationHeight)

				this._outputCanvasCtx.drawImage(
					source,
					sourceX,
					sourceY,
					sourceWidth,
					sourceHeight,
					0,
					0,
					destinationWidth,
					destinationHeight,
				)
			} else {
				this._outputCanvasCtx.filter = `blur(${backgroundBlurValue}px)`
				this._outputCanvasCtx.drawImage(this._inputVideoElement, 0, 0)
			}
		}
	}

	/**
	 * Returns the coordinates, width and height to draw the background image
	 * onto the canvas.
	 *
	 * The background image is cropped and centered as needed to cover the whole
	 * canvas while maintaining the original aspect ratio of the background.
	 *
	 * @param {number} sourceWidth the width of the source image
	 * @param {number} sourceHeight the height of the source image
	 * @param {number} destinationWidth the width of the destination canvas
	 * @param {number} destinationHeight the height of the destination canvas
	 * @return {Array} the X and Y coordinates, width and height of the source
	 *         image after cropping and centering
	 */
	static getSourcePropertiesForDrawingBackgroundImage(sourceWidth, sourceHeight, destinationWidth, destinationHeight) {
		let croppedSourceX = 0
		let croppedSourceY = 0
		let croppedSourceWidth = sourceWidth
		let croppedSourceHeight = sourceHeight

		if (sourceWidth <= 0 || sourceHeight <= 0 || destinationWidth <= 0 || destinationHeight <= 0) {
			return [croppedSourceX, croppedSourceY, croppedSourceWidth, croppedSourceHeight]
		}

		const sourceAspectRatio = sourceWidth / sourceHeight
		const destinationAspectRatio = destinationWidth / destinationHeight

		if (sourceAspectRatio > destinationAspectRatio) {
			croppedSourceWidth = sourceHeight * destinationAspectRatio
			croppedSourceX = (sourceWidth - croppedSourceWidth) / 2
		} else {
			croppedSourceHeight = sourceWidth / destinationAspectRatio
			croppedSourceY = (sourceHeight - croppedSourceHeight) / 2
		}

		return [croppedSourceX, croppedSourceY, croppedSourceWidth, croppedSourceHeight]
	}

	/**
	 * Checks if the local track supports this effect.
	 *
	 * @param {object} jitsiLocalTrack - Track to apply effect.
	 * @return {boolean} - Returns true if this effect can run on the specified track
	 * false otherwise.
	 */
	isEnabled(jitsiLocalTrack) {
		return jitsiLocalTrack.isVideoTrack() && jitsiLocalTrack.videoType === 'camera'
	}

	/**
	 * Starts loop to capture video frame and render the segmentation mask.
	 *
	 * @param {MediaStream} stream - Stream to be used for processing.
	 * @return {MediaStream} - The stream with the applied effect.
	 */
	startEffect(stream) {
		this._running = true

		this._stream = stream
		this._maskFrameTimerWorker = new Worker(timerWorkerScript, { name: 'Blur effect worker' })
		this._maskFrameTimerWorker.onmessage = this._onMaskFrameTimer
		const firstVideoTrack = this._stream.getVideoTracks()[0]
		const { height, frameRate, width }
			= firstVideoTrack.getSettings ? firstVideoTrack.getSettings() : firstVideoTrack.getConstraints()

		this._frameRate = parseInt(frameRate, 10)

		this._outputCanvasElement.width = parseInt(width, 10)
		this._outputCanvasElement.height = parseInt(height, 10)

		if (this._useWebGL) {
			if (!this._glFx) {
				this._glFx = new WebGLCompositor(this._outputCanvasElement)
			}
		} else {
			this._outputCanvasCtx = this._outputCanvasElement.getContext('2d')
			this._segmentationMask = new ImageData(this._options.width, this._options.height)
			this._segmentationMaskCanvas = document.createElement('canvas')
			this._segmentationMaskCanvas.width = this._options.width
			this._segmentationMaskCanvas.height = this._options.height
			this._segmentationMaskCtx = this._segmentationMaskCanvas.getContext('2d', { willReadFrequently: true })

			this._tempCanvas = document.createElement('canvas')
			this._tempCanvasCtx = this._tempCanvas.getContext('2d', { willReadFrequently: true })
		}

		this._inputVideoElement.autoplay = true
		this._inputVideoElement.srcObject = this._stream
		this._inputVideoElement.onloadeddata = () => {
			this._maskFrameTimerWorker.postMessage({
				id: SET_TIMEOUT,
				timeMs: 1000 / this._frameRate,
				message: 'this._maskFrameTimerWorker',
			})
			this._inputVideoElement.onloadeddata = null
		}

		if (this._virtualVideo) {
			this._virtualVideo.play()
		}

		this._frameId = -1
		this._lastFrameId = -1

		this._bgChanged = true

		this._outputStream = this._outputCanvasElement.captureStream(this._frameRate)

		return this._outputStream
	}

	/**
	 * Update constraints (e.g. framerate) on the output stream when the input stream changes.
	 *
	 * @return {void}
	 */
	updateInputStream() {
		const firstVideoTrack = this._stream.getVideoTracks()[0]
		const { frameRate }
			= firstVideoTrack.getSettings ? firstVideoTrack.getSettings() : firstVideoTrack.getConstraints()

		this._frameRate = parseInt(frameRate, 10)

		this._outputStream.getVideoTracks()[0].applyConstraints({ frameRate: this._frameRate }).catch((error) => {
			console.error('Frame rate could not be adjusted in background effect', error)
		})

		this._frameId = -1
		this._lastFrameId = -1
	}

	/**
	 * Stop background effect and release resources.
	 *
	 * @return {void}
	 */
	stopEffect() {
		this._running = false

		if (this._maskFrameTimerWorker) {
			this._maskFrameTimerWorker.postMessage({
				id: CLEAR_TIMEOUT,
				message: 'stopEffect',
			})
			this._maskFrameTimerWorker.terminate()
		}

		if (this._virtualVideo) {
			this._virtualVideo.pause()
		}

		if (this._glFx) {
			this._glFx.dispose()
			this._glFx = null
		}

		this._segmentationMask = null
		this._segmentationMaskCanvas = null
		this._segmentationMaskCtx = null
		this._tempCanvas = null
		this._tempCanvasCtx = null
		this._isFirstBgChange = true
		this._prevBgMode = null
	}

	/**
	 * Destroys the VideoStreamBackgroundEffect instance and releases all resources.
	 */
	destroy() {
		this.stopEffect()
		this._imageSegmenter?.close()
		this._imageSegmenter = null
	}
}
