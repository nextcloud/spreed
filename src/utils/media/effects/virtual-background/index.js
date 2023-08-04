import * as wasmCheck from 'wasm-check'

import JitsiStreamBackgroundEffect from './JitsiStreamBackgroundEffect.js'
import createTFLiteSIMDModule from './vendor/tflite/tflite-simd.js'
import createTFLiteModule from './vendor/tflite/tflite.js'

const models = {
	modelLandscape: 'libs/selfie_segmentation_landscape.tflite',
}

const segmentationDimensions = {
	modelLandscape: {
		height: 144,
		width: 256,
	},
}

/**
 * Creates a new instance of JitsiStreamBackgroundEffect. This loads the Meet background model that is used to
 * extract person segmentation.
 *
 * @param {object} virtualBackground - The virtual object that contains the background image source and
 * the isVirtualBackground flag that indicates if virtual image is activated.
 * @param {Function} dispatch - The Redux dispatch function.
 * @return {Promise<JitsiStreamBackgroundEffect>}
 */
export async function createVirtualBackgroundEffect(virtualBackground, dispatch) {
	if (!MediaStreamTrack.prototype.getSettings && !MediaStreamTrack.prototype.getConstraints) {
		throw new Error('JitsiStreamBackgroundEffect not supported!')
	}
	let tflite

	// Checks if WebAssembly feature is supported or enabled by/in the browser.
	// Conditional import of wasm-check package is done to prevent
	// the browser from crashing when the user opens the app.
	try {
		if (wasmCheck?.feature?.simd) {
			tflite = await createTFLiteSIMDModule()
		} else {
			tflite = await createTFLiteModule()
		}
	} catch (err) {
		console.error('Looks like WebAssembly is disabled or not supported on this browser')

		return
	}

	const modelBufferOffset = tflite._getModelBufferMemoryOffset()
	const modelResponse = await fetch(models.modelLandscape)

	if (!modelResponse.ok) {
		throw new Error('Failed to download tflite model!')
	}

	const model = await modelResponse.arrayBuffer()

	tflite.HEAPU8.set(new Uint8Array(model), modelBufferOffset)

	tflite._loadModel(model.byteLength)

	const options = {
		...segmentationDimensions.modelLandscape,
		virtualBackground,
	}

	return new JitsiStreamBackgroundEffect(tflite, options)
}
