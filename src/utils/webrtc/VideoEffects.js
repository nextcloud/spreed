import * as bodyPix from '@tensorflow-models/body-pix'
// import * as tf from '@tensorflow/tfjs'
import '@tensorflow/tfjs'
import { blur } from '../videofx/src/core/vanilla/blur'
import segmFull from '../videofx/public/models/segm_full_v679.tflite'
import segmLite from '../videofx/public/models/segm_lite_v681.tflite'
import mlKit from '../videofx/public/models/selfiesegmentation_mlkit-256x256-2021_01_19-v1215.f16.tflite'
// import tfLiteWasm from '../videofx/public/tflite/tflite.wasm'
// import tfLiteSimdWasm from '../videofx/public/tflite/tflite-simd.wasm'
// import videoEffectsWorker from './videoEffectsWorker.js'
import { VIRTUAL_BACKGROUND_TYPE } from '../virtual-background/constants'
import JitsiStreamBackgroundEffect from '../virtual-background/JitsiStreamBackgroundEffect'
// import '../videofx/public/tflite/tflite-nosimd.js'
// import '../videofx/public/tflite/tflite-simd.js'
// import Worker from './videoEffects.worker.js'

export default function VideoEffects() {
	this._videoSource = document.createElement('video')
	this._videoSource.muted = 'muted'
	this._temporaryCanvas = document.createElement('canvas')
	this._playing = false
	this._stopStreamBound = this._stopStream.bind(this)
	this._canvasBlurredStream = false
}

VideoEffects.prototype = {
	getBlurredVideoStream(stream, model = 2) {
		console.log('getBlurredStream()')
		window.switchStream = false
		this._model = model
		// this._configureStreams(stream)
		switch (model) {
		case 0:
			this._useBodyPix(stream)
			break
		case 1:
			this._useTfLite(stream)
			break
		case 2:
			this._canvasBlurredStream = this._useJitsi(stream)
			break
		default:
			this._useBodyPix(stream)
			break
		}
		if (!this._canvasBlurredStream) this._canvasBlurredStream = this._temporaryCanvas.captureStream()
		if (!this._canvasBlurredStream.getAudioTracks().length) this._attachAudio()
		// mainStreamEnded is sent in localmedia
		this._canvasBlurredStream.addEventListener('mainStreamEnded', this._stopStreamBound)
		return this._canvasBlurredStream
	},

	_configureStreams(stream) {
		this._stream = stream
		this._videoSource.height = this._stream.getVideoTracks()[0].getSettings().height
		this._videoSource.width = this._stream.getVideoTracks()[0].getSettings().width
		this._temporaryCanvas.height = this._stream.getVideoTracks()[0].getSettings().height
		this._temporaryCanvas.width = this._stream.getVideoTracks()[0].getSettings().width
		this._videoSource.srcObject = this._stream
		this._videoSource.play()
	},

	_stopStream() {
		switch (this._model) {
		case 0:
			this._stopBodyPixStream()
			break
		case 1:
			this._stopTfLiteStream()
			break
		default:
			this._stopBodyPixStream()
			break
		}
	},

	_stopBodyPixStream() {
		this._playing = false
		this._videoSource.removeEventListener('loadeddata', this._videoSourceListener)
		this._attachment = null
		this._temporaryCanvas = null
		const tracks = this._stream.getTracks()
		tracks.forEach(track => {
			track.stop()
		})
		this._stream = null
	},

	_stopTfLiteStream() {
		this._playing = false
		window.stopBlur = true
		this._attachment = null
		this._temporaryCanvas = null
		const tracks = this._stream.getTracks()
		tracks.forEach(track => {
			track.stop()
		})
		this._stream = null
	},

	_loadBodyPix() {

		const options = {
			architecture: 'MobileNetV1',
			multiplier: 0.5,
			outputStride: 16,
			// stride: 32,
			quantBytes: 2,
			internalResolution: 'low',
		}
		bodyPix.load(options)
			.then(net => this._perform(net))
			.catch(err => {
				throw err
			})
	},

	async _perform(net) {

		while (this._playing === true) {
			const segmentation = await net.segmentPerson(this._videoSource, {
				flipHorizontal: false,
				internalResolution: 'low',
				segmentationThreshold: 0.7,
			})
			const backgroundBlurAmount = 15
			const edgeBlurAmount = 2
			const flipHorizontal = false
			bodyPix.drawBokehEffect(
				this._temporaryCanvas, this._videoSource, segmentation, backgroundBlurAmount,
				edgeBlurAmount, flipHorizontal)
			if (window.switchStream === true) {
				window.switchStream = false
				this._playing = false
				this._switchModel(1)
			}
		}
	},

	_videoSourceListener(e) {
		this._loadBodyPix()
	},

	_attachAudio() {
		let extractedAudio = false
		extractedAudio = this._stream.getTracks().filter(function(track) {
			return track.kind === 'audio'
		})[0]
		if (extractedAudio) {
			this._canvasBlurredStream.addTrack(extractedAudio)
		}
	},

	_useBodyPix() {
		this._videoSource.addEventListener('loadeddata', (this._videoSourceListener.bind(this)))
		this._playing = true
	},

	_useTfLite() {
		window.stopBlur = false
		window.segmFull = segmFull.split('/').pop()
		window.segmLite = segmLite.split('/').pop()
		window.mlKit = mlKit.split('/').pop()
		window.tfLiteWasm = tfLiteWasm.split('/').pop()
		window.tfLiteSimdWasm = tfLiteSimdWasm.split('/').pop()
		blur(this._videoSource, this._temporaryCanvas)

	},

	_useJitsi(stream) {

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
		// DONE?: Figure out message for model.
		let isSimd = false
		try {

			const wasmCheck = require('wasm-check')
			if (wasmCheck?.feature?.simd) {
				this.wasmVersion = 'simd'
				isSimd = true
			} else {
				this.wasmVersion = 'wasm'
				isSimd = false
			}
		} catch (err) {
			console.error('Looks like WebAssembly is disabled or not supported on this browser')
			return
		}
		console.log('wasm version: ' + this.wasmVersion)

		const virtualBackground = {
			type: VIRTUAL_BACKGROUND_TYPE.NONE,
		}
		console.log('isSimd:')
		console.log(isSimd)
		const options = {
			...this.wasmVersion === 'simd' ? segmentationDimensions.model144 : segmentationDimensions.model96,
			virtualBackground,
			simd: isSimd,
		}

		const blurFx = new JitsiStreamBackgroundEffect(options)
		return blurFx.startEffect(stream)
	},

	_switchModel(model) {
		if (model === 0) {
			this._useBodyPix()
		} else {
			this._useTfLite()
		}
	},

	_stream: null,

	_canvasBlurredStream: null,

	_context: null,

	_stopBound: null,

}
