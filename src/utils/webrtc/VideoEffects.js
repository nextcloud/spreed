import { VIRTUAL_BACKGROUND_TYPE } from '../virtual-background/constants'
import JitsiStreamBackgroundEffect from '../virtual-background/JitsiStreamBackgroundEffect'

export default function VideoEffects() {
	this._stopStreamBound = this._stopStream.bind(this)
}

VideoEffects.prototype = {
	getBlurredVideoStream(stream) {
		this._stream = stream
		this._canvasBlurredStream = this._useJitsi(stream)
		if (!this._canvasBlurredStream.getAudioTracks().length) this._attachAudio()
		// mainStreamEnded is sent in localmedia
		this._canvasBlurredStream.addEventListener('mainStreamEnded', this._stopStreamBound)
		return this._canvasBlurredStream
	},

	_stopStream() {
		this._stopJitsi()
	},

	_stopJitsi() {
		if (!window.blurFx) {
			return
		}

		window.blurFx.stopEffect()
		window.blurFx = null

		const tracks = this._stream.getTracks()
		tracks.forEach(track => {
			track.stop()
		})
		this._stream = null
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

		const virtualBackground = {
			type: VIRTUAL_BACKGROUND_TYPE.NONE,
			blurValue: 8,
		}
		const options = {
			...this.wasmVersion === 'simd' ? segmentationDimensions.model144 : segmentationDimensions.model96,
			virtualBackground,
			simd: isSimd,
		}

		window.blurFx = new JitsiStreamBackgroundEffect(options)
		return window.blurFx.startEffect(stream)
	},

	_stream: null,

	_canvasBlurredStream: null,

	_context: null,

	_stopBound: null,

}
