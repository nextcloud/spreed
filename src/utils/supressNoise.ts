import {
	RnnoiseWorkletNode,
	loadRnnoise,
} from '@sapphi-red/web-noise-suppressor'

import rnnoiseWorkletPath from '@sapphi-red/web-noise-suppressor/rnnoiseWorklet.js?url'
import rnnoiseWasmPath from '@sapphi-red/web-noise-suppressor/rnnoise.wasm?url'
import rnnoiseWasmSimdPath from '@sapphi-red/web-noise-suppressor/rnnoise_simd.wasm?url'

type NoiseSuppressorModel = 'rnnoise' | 'none'

export async function processNoiseSuppression(stream: MediaStream, model: NoiseSuppressorModel): Promise<MediaStream> {
	if (model === 'rnnoise') {
		const audioContext = new AudioContext()
		const rnnoiseWasmBinary = await loadRnnoise({
			url: rnnoiseWasmPath,
			simdUrl: rnnoiseWasmSimdPath
		})
		await audioContext.audioWorklet.addModule(rnnoiseWorkletPath)
		const mediaStreamAudioSource = audioContext.createMediaStreamSource(stream)
		const mediaStreamAudioDestinationNode = audioContext.createMediaStreamDestination()
		const rnnoise = new RnnoiseWorkletNode(audioContext, {
			wasmBinary: rnnoiseWasmBinary,
			maxChannels: 2
		})
		mediaStreamAudioSource.connect(rnnoise)
		rnnoise.connect(mediaStreamAudioDestinationNode)
		return mediaStreamAudioDestinationNode.stream
	}

	return stream
}
