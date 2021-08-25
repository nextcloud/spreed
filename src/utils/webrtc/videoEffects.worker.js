import createTFLiteModule from '../virtual-background/vendor/tflite/tflite'
import createTFLiteSIMDModule from '../virtual-background/vendor/tflite/tflite-simd'
import withoutSIMD from '../virtual-background/vendor/tflite/tflite.wasm'
import withSIMD from '../virtual-background/vendor/tflite/tflite-simd.wasm'
import v681 from '../virtual-background/vendor/models/segm_lite_v681.tflite'
import v679 from '../virtual-background/vendor/models/segm_full_v679.tflite'
import { Console } from 'console'

const models = {
	model96: v681.split('/').pop(),
	model144: v679.split('/').pop(),
}

self.compiled = false

self.onmessage =  (e) => {
	console.log('Worker: Message received.')
	console.dir(e)
	const message = e.data.message
	switch (message) {
		case 'makeTFLite':
			self.segmentationPixelCount = e.data.segmentationPixelCount
			makeTFLite(e.data.simd)			
			break;
		case 'resizeSource':
			if (!self.compiled) return
			resizeSource(e.data.imageData)
			break;
		case 'runInference':
			runInference()
			break;
		default:
			console.error('videoEffects.worker: Message unknown.')
			console.error(message)
			break;
	}
}

async function makeTFLite(message) {
	try {
		switch (message) {
		case true:
			console.log('worker simd')
			self.wasmUrl = withSIMD.split('/').pop()
			console.log(self.wasmUrl)
			self.tflite = await createTFLiteSIMDModule()
			console.log('worker after createTFLiteSIMDModule()')
			break
		case false:
			console.log('worker wasm')
			self.wasmUrl = withoutSIMD.split('/').pop()
			self.tflite = await createTFLiteModule()
			break
		default:
			console.log('worker returns')
			return
		}
		console.log('worker after switch ' + self.wasmUrl)
		self.modelBufferOffset = self.tflite._getModelBufferMemoryOffset()
		self.modelResponse = await fetch(message === true ? models.model144 : models.model96)

		if (!self.modelResponse.ok) {
			throw new Error('Failed to download tflite model!')
		}
		self.model = await self.modelResponse.arrayBuffer()

		self.tflite.HEAPU8.set(new Uint8Array(self.model), self.modelBufferOffset)

		await self.tflite._loadModel(self.model.byteLength)

		self.compiled = true

		self.postMessage({message: 'loaded'})

	} catch (error) {
		console.error(error)
		console.error('videoEffects.worker: tflite compilation failed.')
	}
}

function resizeSource(imageData) {
	const inputMemoryOffset = self.tflite._getInputMemoryOffset() / 4
	console.log(inputMemoryOffset)
	for (let i = 0; i < self.segmentationPixelCount; i++) {
		self.tflite.HEAPF32[inputMemoryOffset + (i * 3)] = imageData.data[i * 4] / 255
		// console.log(imageData.data[i * 4])
		// console.log(imageData.data[i * 4] / 255)
		self.tflite.HEAPF32[inputMemoryOffset + (i * 3) + 1] = imageData.data[(i * 4) + 1] / 255
		self.tflite.HEAPF32[inputMemoryOffset + (i * 3) + 2] = imageData.data[(i * 4) + 2] / 255
	}
	// self.postMessage({ message: 'sourceResized' })
	runInference()
}

function runInference() {
	self.tflite._runInference()
	const outputMemoryOffset = self.tflite._getOutputMemoryOffset() / 4
	const segmentationMaskData = []
	// All consts in Worker in obj array.
	for (let i = 0; i < self.segmentationPixelCount; i++) {

		const background = self.tflite.HEAPF32[outputMemoryOffset + (i * 2)]
		const person = self.tflite.HEAPF32[outputMemoryOffset + (i * 2) + 1]
		const shift = Math.max(background, person)

		segmentationMaskData.push({
			background: background,
			person: person,
			shift: shift,
			backgroundExp: Math.exp(background - shift),
			personExp: Math.exp(person - shift),
		})
	}
	self.postMessage({ message: 'inferenceRun', segmentationResult: segmentationMaskData })
}
