// @flow

import { VIRTUAL_BACKGROUND_TYPE } from './constants.js'
import WebWorker from './JitsiStreamBackgroundEffect.worker.js'

import {
	CLEAR_TIMEOUT,
	TIMEOUT_TICK,
	SET_TIMEOUT,
	timerWorkerScript,
} from './TimerWorker.js'

/**
 * Represents a modified MediaStream that adds effects to video background.
 * <tt>JitsiStreamBackgroundEffect</tt> does the processing of the original
 * video stream.
 */
export default class JitsiStreamBackgroundEffect {

	// _model: Object;
	// _options: Object;
	// _stream: Object;
	// _segmentationPixelCount: number;
	// _inputVideoElement: HTMLVideoElement;
	// _onMaskFrameTimer: Function;
	// _maskFrameTimerWorker: Worker;
	// _outputCanvasElement: HTMLCanvasElement;
	// _outputCanvasCtx: Object;
	// _segmentationMaskCtx: Object;
	// _segmentationMask: Object;
	// _segmentationMaskCanvas: Object;
	// _renderMask: Function;
	// _virtualImage: HTMLImageElement;
	// _virtualVideo: HTMLVideoElement;
	// isEnabled: Function;
	// startEffect: Function;
	// stopEffect: Function;

	/**
	 * Represents a modified video MediaStream track.
	 *
	 * @class
	 * @param {object} options - Segmentation dimensions.
	 * @param {number} options.virtualBackground.blurValue the blur to apply on
	 *                 a 720p video; it will be automatically scaled as needed.
	 */
	constructor(options) {
		const isSimd = options.simd
		this._options = options
		this._loadPromise = new Promise((resolve, reject) => {
			this._loadPromiseResolve = resolve
			this._loadPromiseReject = reject
		})
		this._loaded = false
		this._loadFailed = false

		if (this._options.virtualBackground.backgroundType === VIRTUAL_BACKGROUND_TYPE.IMAGE) {
			this._virtualImage = document.createElement('img')
			this._virtualImage.crossOrigin = 'anonymous'
			this._virtualImage.src = this._options.virtualBackground.virtualSource
		}
		if (this._options.virtualBackground.backgroundType === VIRTUAL_BACKGROUND_TYPE.DESKTOP_SHARE) {
			this._virtualVideo = document.createElement('video')
			this._virtualVideo.autoplay = true
			this._virtualVideo.srcObject = this._options?.virtualBackground?.virtualSource?.stream
		}
		const segmentationPixelCount = this._options.width * this._options.height
		this._segmentationPixelCount = segmentationPixelCount
		this._model = new WebWorker()
		this._model.postMessage({
			message: 'makeTFLite',
			segmentationPixelCount,
			simd: isSimd,
		})

		this._segmentationPixelCount = segmentationPixelCount

		// Bind event handler so it is only bound once for every instance.
		this._onMaskFrameTimer = this._onMaskFrameTimer.bind(this)
		this._startFx = this._startFx.bind(this)

		this._model.onmessage = this._startFx

		// Workaround for FF issue https://bugzilla.mozilla.org/show_bug.cgi?id=1388974
		this._outputCanvasElement = document.createElement('canvas')
		this._outputCanvasElement.getContext('2d')
		this._inputVideoElement = document.createElement('video')
	}

	/**
	 * EventHandler onmessage for the maskFrameTimerWorker WebWorker.
	 *
	 * @private
	 * @param {object} response - The onmessage EventHandler parameter.
	 * @return {void}
	 */
	_onMaskFrameTimer(response) {
		if (response.data.id === TIMEOUT_TICK) {
			this._renderMask()
		}
	}

	_startFx(e) {
		switch (e.data.message) {
		case 'inferenceRun':
			if (e.data.frameId === this._lastFrameId + 1) {
				this._lastFrameId = e.data.frameId

				this.runInference(e.data.segmentationResult)
				this.runPostProcessing()
			}
			break
		case 'loaded':
			this._loaded = true
			this._loadPromiseResolve()
			break
		case 'loadFailed':
			this._loadFailed = true
			this._loadPromiseReject()
			break
		default:
			console.error('_startFx: Something went wrong.')
			break
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
	 * Represents the run post processing.
	 *
	 * @return {void}
	 */
	runPostProcessing() {

		const height = this._inputVideoElement.videoHeight
		const width = this._inputVideoElement.videoWidth
		const { backgroundType } = this._options.virtualBackground

		const scaledBlurFactor = width / 720.0
		const backgroundBlurValue = this._options.virtualBackground.blurValue * scaledBlurFactor
		const edgesBlurValue = (backgroundType === VIRTUAL_BACKGROUND_TYPE.IMAGE ? 4 : 8) * scaledBlurFactor

		this._outputCanvasElement.height = height
		this._outputCanvasElement.width = width
		this._outputCanvasCtx.globalCompositeOperation = 'copy'

		// Draw segmentation mask.

		// Smooth out the edges.
		this._outputCanvasCtx.filter = `blur(${edgesBlurValue}px)`
		if (backgroundType === VIRTUAL_BACKGROUND_TYPE.DESKTOP_SHARE) {
			// Save current context before applying transformations.
			this._outputCanvasCtx.save()

			// Flip the canvas and prevent mirror behaviour.
			this._outputCanvasCtx.scale(-1, 1)
			this._outputCanvasCtx.translate(-this._outputCanvasElement.width, 0)
		}
		this._outputCanvasCtx.drawImage(
			this._segmentationMaskCanvas,
			0,
			0,
			this._options.width,
			this._options.height,
			0,
			0,
			this._inputVideoElement.videoWidth,
			this._inputVideoElement.videoHeight
		)
		if (backgroundType === VIRTUAL_BACKGROUND_TYPE.DESKTOP_SHARE) {
			this._outputCanvasCtx.restore()
		}
		this._outputCanvasCtx.globalCompositeOperation = 'source-in'
		this._outputCanvasCtx.filter = 'none'

		// Draw the foreground video.
		if (backgroundType === VIRTUAL_BACKGROUND_TYPE.DESKTOP_SHARE) {
			// Save current context before applying transformations.
			this._outputCanvasCtx.save()

			// Flip the canvas and prevent mirror behaviour.
			this._outputCanvasCtx.scale(-1, 1)
			this._outputCanvasCtx.translate(-this._outputCanvasElement.width, 0)
		}
		this._outputCanvasCtx.drawImage(this._inputVideoElement, 0, 0)
		if (backgroundType === VIRTUAL_BACKGROUND_TYPE.DESKTOP_SHARE) {
			this._outputCanvasCtx.restore()
		}

		// Draw the background.

		this._outputCanvasCtx.globalCompositeOperation = 'destination-over'
		if (backgroundType === VIRTUAL_BACKGROUND_TYPE.IMAGE
            || backgroundType === VIRTUAL_BACKGROUND_TYPE.DESKTOP_SHARE) {
			this._outputCanvasCtx.drawImage(
				backgroundType === VIRTUAL_BACKGROUND_TYPE.IMAGE
					? this._virtualImage
					: this._virtualVideo,
				0,
				0,
				this._outputCanvasElement.width,
				this._outputCanvasElement.height
			)
		} else {
			this._outputCanvasCtx.filter = `blur(${backgroundBlurValue}px)`
			this._outputCanvasCtx.drawImage(this._inputVideoElement, 0, 0)
		}
	}

	/**
	 * Represents the run Tensorflow Interference.
	 * Worker partly
	 *
	 * @param {Array} data the segmentation result
	 * @return {void}
	 */
	runInference(data) {
		// All consts in Worker in obj array.
		for (let i = 0; i < this._segmentationPixelCount; i++) {
			this._segmentationMask.data[(i * 4) + 3] = 255 * data[i].person
		}
		this._segmentationMaskCtx.putImageData(this._segmentationMask, 0, 0)
	}

	/**
	 * Loop function to render the background mask.
	 *
	 * @private
	 * @return {void}
	 */
	_renderMask() {
		if (this._frameId < this._lastFrameId) {
			console.debug('Fixing frame id, this should not happen', this._frameId, this._lastFrameId)

			this._frameId = this._lastFrameId
		}

		// Calculate segmentation data only if the previous one finished
		// already.
		if (this._loaded && this._frameId === this._lastFrameId) {
			this._frameId++

			this.resizeSource()
		}

		this._maskFrameTimerWorker.postMessage({
			id: SET_TIMEOUT,
			timeMs: 1000 / this._frameRate,
			message: 'this._maskFrameTimerWorker',
		})
	}

	/**
	 * Represents the resize source process.
	 * Worker partly
	 *
	 * @return {void}
	 */
	resizeSource() {
		this._segmentationMaskCtx.drawImage(
			this._inputVideoElement,
			0,
			0,
			this._inputVideoElement.videoWidth,
			this._inputVideoElement.videoHeight,
			0,
			0,
			this._options.width,
			this._options.height
		)

		const imageData = this._segmentationMaskCtx.getImageData(
			0,
			0,
			this._options.width,
			this._options.height
		)

		this._model.postMessage({ message: 'resizeSource', imageData, frameId: this._frameId })
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
		this._stream = stream
		this._maskFrameTimerWorker = new Worker(timerWorkerScript, { name: 'Blur effect worker' })
		this._maskFrameTimerWorker.onmessage = this._onMaskFrameTimer
		const firstVideoTrack = this._stream.getVideoTracks()[0]
		const { height, frameRate, width }
            = firstVideoTrack.getSettings ? firstVideoTrack.getSettings() : firstVideoTrack.getConstraints()

		this._frameRate = parseInt(frameRate, 10)

		this._segmentationMask = new ImageData(this._options.width, this._options.height)
		this._segmentationMaskCanvas = document.createElement('canvas')
		this._segmentationMaskCanvas.width = this._options.width
		this._segmentationMaskCanvas.height = this._options.height
		this._segmentationMaskCtx = this._segmentationMaskCanvas.getContext('2d')

		this._outputCanvasElement.width = parseInt(width, 10)
		this._outputCanvasElement.height = parseInt(height, 10)
		this._outputCanvasCtx = this._outputCanvasElement.getContext('2d')
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

		this._frameId = -1
		this._lastFrameId = -1

		this._outputStream = this._outputCanvasElement.captureStream(this._frameRate)

		return this._outputStream
	}

	updateInputStream() {
		const firstVideoTrack = this._stream.getVideoTracks()[0]
		const { frameRate }
            = firstVideoTrack.getSettings ? firstVideoTrack.getSettings() : firstVideoTrack.getConstraints()

		this._frameRate = parseInt(frameRate, 10)

		this._outputStream.getVideoTracks()[0].applyConstraints({ frameRate: this._frameRate }).catch(error => {
			console.error('Frame rate could not be adjusted in background effect', error)
		})

		this._frameId = -1
		this._lastFrameId = -1
	}

	/**
	 * Stops the capture and render loop.
	 *
	 * @return {void}
	 */
	stopEffect() {
		this._maskFrameTimerWorker.postMessage({
			id: CLEAR_TIMEOUT,
			message: 'stopEffect',
		})

		this._maskFrameTimerWorker.terminate()
	}

}
