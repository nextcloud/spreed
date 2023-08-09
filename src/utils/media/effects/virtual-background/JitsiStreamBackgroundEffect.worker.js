// TODO: FIXME configure eslint
// eslint-disable-next-line import/no-unresolved
import landscape from './vendor/models/selfie_segmentation_landscape.tflite?url'
import createTFLiteSIMDModule from './vendor/tflite/tflite-simd.js'
// TODO: FIXME configure eslint
// eslint-disable-next-line import/no-unresolved
import withSIMD from './vendor/tflite/tflite-simd.wasm?url'
import createTFLiteModule from './vendor/tflite/tflite.js'
// TODO: FIXME configure eslint
// eslint-disable-next-line import/no-unresolved
import withoutSIMD from './vendor/tflite/tflite.wasm?url'

const models = {
	modelLandscape: landscape.split('/').pop(),
}

self.compiled = false

self.onmessage = (e) => {
	const message = e.data.message
	switch (message) {
	case 'makeTFLite':
		self.segmentationPixelCount = e.data.segmentationPixelCount
		makeTFLite(e.data.simd)
		break
	case 'resizeSource':
		if (!self.compiled) return
		resizeSource(e.data.imageData, e.data.frameId)
		break
	case 'runInference':
		runInference()
		break
	default:
		console.error('JitsiStreamBackgroundEffect.worker: Message unknown.')
		console.error(message)
		break
	}
}

/**
 * @param {boolean} isSimd whether WebAssembly SIMD is available or not
 */
async function makeTFLite(isSimd) {
	try {
		switch (isSimd) {
		case true:
			self.wasmUrl = withSIMD.split('/').pop()
			self.tflite = await createTFLiteSIMDModule({ locateFile: (path) => { return self.wasmUrl } })
			break
		case false:
			self.wasmUrl = withoutSIMD.split('/').pop()
			self.tflite = await createTFLiteModule({ locateFile: (path) => { return self.wasmUrl } })
			break
		default:
			return
		}
		self.modelBufferOffset = self.tflite._getModelBufferMemoryOffset()
		self.modelResponse = await fetch(models.modelLandscape)

		if (!self.modelResponse.ok) {
			throw new Error('Failed to download tflite model!')
		}
		self.model = await self.modelResponse.arrayBuffer()

		self.tflite.HEAPU8.set(new Uint8Array(self.model), self.modelBufferOffset)

		await self.tflite._loadModel(self.model.byteLength)

		// Even if the wrong tflite file is downloaded (for example, if an HTML
		// error is downloaded instead of the file) loading the model will
		// succeed. However, if the model does not have certain values it could
		// be assumed that the model failed to load.
		if (!self.tflite._getInputWidth() || !self.tflite._getInputHeight()
			|| !self.tflite._getOutputWidth() || !self.tflite._getOutputHeight()) {
			throw new Error('Failed to load tflite model!')
		}

		self.compiled = true

		self.postMessage({ message: 'loaded' })

	} catch (error) {
		console.error(error)
		console.error('JitsiStreamBackgroundEffect.worker: tflite compilation failed. The web server may not be properly configured to send wasm and/or tflite files.')

		self.postMessage({ message: 'loadFailed' })
	}
}

/**
 * @param {ImageData} imageData the image data from the canvas
 * @param {number} frameId the ID of the frame that the image data belongs to
 */
function resizeSource(imageData, frameId) {
	const inputMemoryOffset = self.tflite._getInputMemoryOffset() / 4
	for (let i = 0; i < self.segmentationPixelCount; i++) {
		self.tflite.HEAPF32[inputMemoryOffset + (i * 3)] = imageData.data[i * 4] / 255
		self.tflite.HEAPF32[inputMemoryOffset + (i * 3) + 1] = imageData.data[(i * 4) + 1] / 255
		self.tflite.HEAPF32[inputMemoryOffset + (i * 3) + 2] = imageData.data[(i * 4) + 2] / 255
	}
	runInference(frameId)
}

/**
 * @param {number} frameId the ID of the frame that the image data belongs to
 */
function runInference(frameId) {
	self.tflite._runInference()
	const outputMemoryOffset = self.tflite._getOutputMemoryOffset() / 4
	const segmentationMaskData = []
	// All consts in Worker in obj array.
	for (let i = 0; i < self.segmentationPixelCount; i++) {

		const person = self.tflite.HEAPF32[outputMemoryOffset + i]

		segmentationMaskData.push({
			person,
		})
	}
	self.postMessage({ message: 'inferenceRun', segmentationResult: segmentationMaskData, frameId })
}

// This is needed to make the linter happy, but even if nothing is actually
// exported the worker is loaded as expected.
export default null
