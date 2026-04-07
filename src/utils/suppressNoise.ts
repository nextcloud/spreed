/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import bbbaWasmUrl from './wasm/BBBA-mapi.wasm'
import bbbaNosimdWasmUrl from './wasm/BBBA-nosimd-mapi.wasm'
import bbbaJsRaw from './wasm/BBBA-mapi.js?raw'
import bbbaNosimdJsRaw from './wasm/BBBA-nosimd-mapi.js?raw'
import mapiProcRaw from './wasm/mapi-proc.js?raw'

/** Compat interface that mimics MessagePort for ScriptProcessorNode fallback */
interface CompatMessagePort {
	onmessage: (event: MessageEvent) => void
	postMessage: (data: unknown) => void
}

/** ScriptProcessorNode extended with a MessagePort-like object for fallback on Android */
interface CompatScriptProcessorNode extends ScriptProcessorNode {
	port?: CompatMessagePort
}

interface MAPIModule {
	_malloc: (size: number) => number
	_mapi_process: (handle: number, inputPtr: number, outputPtr: number, numFrames: number) => void
	_mapi_set_parameter: (handle: number, symbol: number, value: number) => void
	_mapi_create: (sampleRate: number, bufferSize: number) => number
	lengthBytesUTF8: (str: string) => number
	stringToUTF8: (str: string, buffer: number, bufferSize: number) => void
	HEAPF32: Float32Array & { BYTES_PER_ELEMENT: number }
	HEAPU32: Uint32Array & { BYTES_PER_ELEMENT: number }
}

/** Cached loaded BBBA assets, shared across all consumers */
const loadedFiles: {
	error?: string
	wasmBlob?: ArrayBuffer
	wasmJS?: string
	worklet?: string
} = {}

/**
 * Checks whether the browser supports WebAssembly SIMD instructions.
 */
function supportsSIMD(): boolean {
	try {
		return WebAssembly.validate(
			// eslint-disable-next-line max-len
			new Uint8Array([0, 97, 115, 109, 1, 0, 0, 0, 1, 5, 1, 96, 0, 1, 123, 3, 2, 1, 0, 10, 10, 1, 8, 0, 65, 0, 253, 15, 253, 98, 11]),
		)
	} catch {
		return false
	}
}

/**
 * Loads the BBBA WASM binary, Emscripten JS loader, and AudioWorklet processor.
 * Results are cached so subsequent calls resolve immediately.
 */
async function loadBBBAFiles(): Promise<void> {
	if (loadedFiles.error !== undefined) {
		throw new Error(loadedFiles.error)
	}
	if (loadedFiles.wasmBlob && loadedFiles.wasmJS && loadedFiles.worklet) {
		return
	}

	if (typeof WebAssembly === 'undefined') {
		loadedFiles.error = 'WebAssembly unsupported'
		throw new Error(loadedFiles.error)
	}

	const simd = supportsSIMD()
	const wasmUrl = simd ? bbbaWasmUrl : bbbaNosimdWasmUrl
	const jsText = simd ? bbbaJsRaw : bbbaNosimdJsRaw

	const wasmResponse = await fetch(wasmUrl)
	if (!wasmResponse.ok) {
		loadedFiles.error = `Failed to fetch BBBA WASM: ${wasmResponse.status}`
		throw new Error(loadedFiles.error)
	}

	loadedFiles.wasmBlob = await wasmResponse.arrayBuffer()
	loadedFiles.wasmJS = jsText
	loadedFiles.worklet = mapiProcRaw
}

/**
 * Creates a ScriptProcessorNode that emulates the BBBA AudioWorklet API.
 * Used as a fallback on Android where AudioWorklet block size is constrained.
 */
function createScriptProcessorNode(ctx: AudioContext): Promise<CompatScriptProcessorNode> {
	return new Promise<CompatScriptProcessorNode>((resolve, reject) => {
		// eslint-disable-next-line no-new-func
		const createModuleBBBA = new Function(loadedFiles.wasmJS + 'return mapi_bbba;').call(undefined)

		const bufferSize = 4096
		const processor: CompatScriptProcessorNode = ctx.createScriptProcessor(bufferSize, 1, 1)
		processor.port = {
			onmessage: () => {},
			postMessage: () => {},
		}

		createModuleBBBA({
			instantiateWasm: (imports: WebAssembly.Imports, successCallback: (instance: WebAssembly.Instance, module: WebAssembly.Module) => void) => {
				WebAssembly.instantiate(loadedFiles.wasmBlob!, imports)
					.then(output => successCallback(output.instance, output.module))
					.catch(reject)
				return {}
			},
			postRun: (module: MAPIModule) => {
				const handle = module._mapi_create(ctx.sampleRate, bufferSize)
				const audioData = module._malloc(module.HEAPF32.BYTES_PER_ELEMENT * bufferSize)
				const audioPtrs = module._malloc(module.HEAPU32.BYTES_PER_ELEMENT)
				module.HEAPU32[audioPtrs + (0 << 2) >> 2] = audioData

				const maxSymbolLength = 255
				const csymbolData = module._malloc(maxSymbolLength)
				const csymbol = (symbol: string) => {
					const len = Math.min(maxSymbolLength, module.lengthBytesUTF8(symbol) + 1)
					module.stringToUTF8(symbol, csymbolData, len)
					return csymbolData
				}

				let enabled = true
				processor.onaudioprocess = (e) => {
					if (!enabled) {
						e.outputBuffer.copyToChannel(e.inputBuffer.getChannelData(0), 0)
						return
					}
					const buffer = e.inputBuffer.getChannelData(0)
					for (let i = 0; i < bufferSize; ++i) {
						module.HEAPF32[audioData + (i << 2) >> 2] = buffer[i]
					}
					module._mapi_process(handle, audioPtrs, audioPtrs, bufferSize)
					const out = e.outputBuffer.getChannelData(0)
					for (let i = 0; i < bufferSize; ++i) {
						out[i] = module.HEAPF32[audioData + (i << 2) >> 2]
					}
				}

				processor.port!.postMessage = (data: unknown) => {
					const msg = data as { type: string, enable?: boolean, symbol?: string, value?: number }
					switch (msg.type) {
					case 'init':
						processor.port!.onmessage({ data: { type: 'loaded' } } as MessageEvent)
						break
					case 'enable':
						enabled = !!msg.enable
						break
					case 'param':
						if (msg.symbol !== undefined && msg.value !== undefined) {
							module._mapi_set_parameter(handle, csymbol(msg.symbol), msg.value)
						}
						break
					}
				}

				resolve(processor)
			},
		})
	})
}

let audioContext: AudioContext | null = null
let noiseSuppressionNode: AudioWorkletNode | CompatScriptProcessorNode | null = null
const workletConsumers = new Set<symbol>()
const workletCleanupCallbackMap = new Map<symbol, () => void>()

/**
 * Initializes the BBBA noise suppression node (AudioWorklet or ScriptProcessor fallback)
 * and configures it with voice optimization parameters.
 * The node's WASM module is initialized asynchronously; audio passes through silently until loaded.
 */
async function initNoiseSuppressionNode(ctx: AudioContext): Promise<AudioWorkletNode | CompatScriptProcessorNode> {
	let node: AudioWorkletNode | CompatScriptProcessorNode

	if (!navigator.userAgent.match(/Android/i)) {
		// Primary path: use AudioWorklet
		const processorBlob = new Blob([loadedFiles.worklet!], { type: 'text/javascript' })
		const processorURL = URL.createObjectURL(processorBlob)
		await ctx.audioWorklet.addModule(processorURL)
		URL.revokeObjectURL(processorURL)
		node = new AudioWorkletNode(ctx, 'mapi-proc')

		// Catch unhandled exceptions thrown by the processor (e.g. new Function CSP failure)
		node.onprocessorerror = (event) => {
			console.error('BBBA AudioWorklet processor error:', event)
		}
	} else {
		// Fallback: ScriptProcessorNode for Android
		node = await createScriptProcessorNode(ctx)
	}

	node.port!.onmessage = (event: MessageEvent) => {
		if (event.data?.type === 'loaded') {
			node.port!.postMessage({ type: 'param', symbol: 'intensity', value: 100 })
			node.port!.postMessage({ type: 'param', symbol: 'leveler_target', value: -18 })
			node.port!.postMessage({ type: 'param', symbol: 'sb_strength', value: 60 })
			node.port!.postMessage({ type: 'param', symbol: 'mb_strength', value: 60 })
			node.port!.postMessage({ type: 'param', symbol: 'pre_gain', value: 2 })
			node.port!.postMessage({ type: 'param', symbol: 'post_gain', value: 0 })
		} else if (event.data?.type === 'error') {
			console.error('BBBA worklet error:', event.data.error)
		}
	}
	node.port!.postMessage({ type: 'init', wasm: loadedFiles.wasmBlob, js: loadedFiles.wasmJS })

	return node
}

/**
 * Creates and registers a global BBBA noise suppression node and AudioContext.
 *
 * @returns A promise that resolves with a unique symbol
 * representing the consumer. This symbol must be passed to
 * `unregisterNoiseSuppressionWorklet` when the consumer is done.
 */
export async function registerNoiseSuppressionWorklet(): Promise<symbol | null> {
	const consumer = Symbol('noise-suppression-consumer')
	workletConsumers.add(consumer)

	if (audioContext && noiseSuppressionNode) {
		if (audioContext.state === 'suspended') {
			await audioContext.resume()
		}
		// Already initialized
		return consumer
	}

	try {
		audioContext = new AudioContext()

		// Resume before worklet init: a suspended AudioContext may pause the audio
		// rendering thread, preventing worklet message delivery in some browsers
		if (audioContext.state === 'suspended') {
			await audioContext.resume()
		}

		await loadBBBAFiles()
		noiseSuppressionNode = await initNoiseSuppressionNode(audioContext)

		return consumer
	} catch (error) {
		console.error('Error initializing BBBA noise suppression:', error)
		await destroyNoiseSuppressionWorklet()
		workletConsumers.delete(consumer)
		return null
	}
}

/**
 * Unregister consumer from the global noise suppression node and AudioContext
 * if there are no other worklet consumers left.
 *
 * @param consumer The symbol returned by `registerNoiseSuppressionWorklet`.
 */
export async function unregisterNoiseSuppressionWorklet(consumer: symbol) {
	if (!workletConsumers.has(consumer)) {
		return
	}

	cleanupNoiseSuppressionWorklet(consumer)
	workletConsumers.delete(consumer)

	if (workletConsumers.size > 0) {
		return
	} else {
		await destroyNoiseSuppressionWorklet()
	}
}

/**
 * Destroys the global noise suppression node and AudioContext.
 */
export async function destroyNoiseSuppressionWorklet() {
	if (noiseSuppressionNode) {
		try {
			noiseSuppressionNode.port?.postMessage({ type: 'destroy' })
			noiseSuppressionNode.disconnect()
		} catch (error) {
			console.error(error)
		}
		noiseSuppressionNode = null
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
 * Requires that the noise suppression worklet has been asynchronously registered beforehand.
 *
 * @param stream - MediaStream to process
 * @param consumer - Unique consumer id returned by `registerNoiseSuppressionWorklet`
 * @param enabled - Whether noise suppression is enabled
 */
export function processNoiseSuppression(stream: MediaStream, consumer: symbol | null, enabled = false): MediaStream {
	if (!enabled) {
		// No noise suppression requested; return the original stream
		console.log(1)
		return stream
	}

	if (!stream.getAudioTracks().length) {
		// No audio tracks to process; return the original stream
		console.log(2)
		return stream
	}

	if (!audioContext || !noiseSuppressionNode || !consumer) {
		console.log(3)
		return stream
	}

	console.log(4)
	cleanupNoiseSuppressionWorklet(consumer)
	return processBBBA(stream, consumer)
}

/**
 * Connects the BBBA noise suppression node to the given MediaStream
 * and returns a new MediaStream with noise suppression applied.
 *
 * @param stream - MediaStream to process
 * @param consumer - Unique consumer id returned by `registerNoiseSuppressionWorklet`
 */
export function processBBBA(stream: MediaStream, consumer: symbol): MediaStream {
	try {
		console.log('processing')
		const mediaStreamAudioSource = audioContext!.createMediaStreamSource(stream)
		const mediaStreamAudioDestinationNode = audioContext!.createMediaStreamDestination()

		mediaStreamAudioSource.connect(noiseSuppressionNode!)
		noiseSuppressionNode!.connect(mediaStreamAudioDestinationNode)

		const processedAudioTrack = mediaStreamAudioDestinationNode.stream.getAudioTracks()[0]
		if (!processedAudioTrack) {
			return stream
		}

		// Replace existing audio tracks with the processed track
		for (const track of stream.getAudioTracks()) {
			stream.removeTrack(track)
		}
		stream.addTrack(processedAudioTrack)

		workletCleanupCallbackMap.set(consumer, () => {
			try {
				mediaStreamAudioSource.disconnect(noiseSuppressionNode!)
				noiseSuppressionNode!.disconnect(mediaStreamAudioDestinationNode)
				mediaStreamAudioDestinationNode.disconnect()
			} catch (error) {
				console.error(error)
			}
			processedAudioTrack.stop()
		})
		console.log('processed')
	} catch (error) {
		console.error('Error processing BBBA noise suppression:', error)
	}

	return stream
}

/**
 * Cleans up the processing nodes currently associated with a consumer.
 *
 * @param consumer - Unique consumer id returned by `registerNoiseSuppressionWorklet`
 */
function cleanupNoiseSuppressionWorklet(consumer: symbol) {
	if (workletCleanupCallbackMap.has(consumer)) {
		workletCleanupCallbackMap.get(consumer)!()
		workletCleanupCallbackMap.delete(consumer)
	}
}
