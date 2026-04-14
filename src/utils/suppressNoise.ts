/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadRnnoise, RnnoiseWorkletNode } from '@sapphi-red/web-noise-suppressor'

let audioContext: AudioContext | null = null
let rnnoiseWorklet: RnnoiseWorkletNode | null = null
const workletConsumers = new Set<symbol>()

/**
 * Creates and registers global RNNoiseWorkletNode and AudioContext.
 *
 * @returns A promise that resolves with a unique symbol
 * representing the consumer. This symbol must be passed to
 * `destroyNoiseSuppressionWorklet` when the consumer is done.
 */
export async function registerNoiseSuppressionWorklet(): Promise<symbol> {
	const consumer = Symbol('noise-suppression-consumer')
	workletConsumers.add(consumer)

	if (audioContext && rnnoiseWorklet) {
		// Already registered
		return consumer
	}

	audioContext = new AudioContext()
	const rnnoiseWasmBinary = await loadRnnoise({
		url: new URL(
			'../../node_modules/@sapphi-red/web-noise-suppressor/dist/rnnoise.wasm',
			import.meta.url,
		).pathname,
		simdUrl: new URL(
			'../../node_modules/@sapphi-red/web-noise-suppressor/dist/rnnoise_simd.wasm',
			import.meta.url,
		).pathname,
	})
	await audioContext.audioWorklet.addModule(new URL(
		'../../node_modules/@sapphi-red/web-noise-suppressor/dist/rnnoise/workletProcessor.js',
		import.meta.url,
	).pathname)
	rnnoiseWorklet = new RnnoiseWorkletNode(audioContext, {
		wasmBinary: rnnoiseWasmBinary,
		maxChannels: 2,
	})

	return consumer
}

/**
 * Destroys the global RNNoiseWorkletNode and AudioContext
 * if there are no other worklet consumers left.
 *
 * @param consumer The symbol returned by `registerNoiseSuppressionWorklet`.
 */
export async function destroyNoiseSuppressionWorklet(consumer: symbol) {
	if (!workletConsumers.has(consumer)) {
		return
	}

	workletConsumers.delete(consumer)

	if (workletConsumers.size > 0) {
		return
	}

	if (rnnoiseWorklet) {
		try {
			rnnoiseWorklet?.disconnect()
		} catch (error) {
			console.error(error)
		}
		rnnoiseWorklet = null
	}

	if (audioContext) {
		try {
			await audioContext.close()
		} catch (error) {
			console.error(error)
		}
		audioContext = null
	}
}

/**
 * Processes the given MediaStream with noise suppression if enabled.
 * Requires that RNNoiseWorklet has been asynchronously registered beforehand.
 *
 * @param stream - MediaStream to process
 * @param enabled - Whether noise suppression is enabled
 */
export function processNoiseSuppression(stream: MediaStream, enabled = false): MediaStream {
	if (!enabled) {
		// No noise suppression requested; return the original stream
		return stream
	}

	if (!stream.getAudioTracks().length) {
		// No audio tracks to process; return the original stream
		return stream
	}

	if (!audioContext || !rnnoiseWorklet) {
		return stream
	}

	return processRnnoise(stream)
}

/**
 * Connects the RNNoiseWorklet to the given MediaStream and returns a new MediaStream with noise suppression applied.
 *
 * @param stream - MediaStream to process
 */
export function processRnnoise(stream: MediaStream): MediaStream {
	try {
		const mediaStreamAudioSource = audioContext!.createMediaStreamSource(stream)
		const mediaStreamAudioDestinationNode = audioContext!.createMediaStreamDestination()
		mediaStreamAudioSource.connect(rnnoiseWorklet!)
		rnnoiseWorklet!.connect(mediaStreamAudioDestinationNode)

		const processedAudioTrack = mediaStreamAudioDestinationNode.stream.getAudioTracks()[0]
		if (!processedAudioTrack) {
			return stream
		}

		// Remove existing audio tracks from the original stream and add only the processed track
		for (const track of stream.getAudioTracks()) {
			stream.removeTrack(track)
		}
		stream.addTrack(processedAudioTrack)
	} catch (error) {
		console.error('Error processing noise suppression:', error)
	}

	return stream
}
