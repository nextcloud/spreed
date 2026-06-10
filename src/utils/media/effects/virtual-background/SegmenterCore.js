/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { FilesetResolver, ImageSegmenter } from '@mediapipe/tasks-vision'

// Cache MediaPipe resources to avoid loading them multiple times.
let _WasmFileset = null

/**
 * Build URLs for MediaPipe wasm filesets and the segmentation model.
 *
 * Must be called on the main thread, where the public path is configured,
 * so the resulting URLs can be passed to a worker.
 *
 * @return {object} asset config with simd/nosimd filesets and model path.
 */
export function getSegmenterAssetConfig() {
	return {
		simd: {
			wasmLoaderPath: new URL(
				'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_internal.js',
				import.meta.url,
			).pathname,
			wasmBinaryPath: new URL(
				'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_internal.wasm',
				import.meta.url,
			).pathname,
		},
		nosimd: {
			wasmLoaderPath: new URL(
				'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_nosimd_internal.js',
				import.meta.url,
			).pathname,
			wasmBinaryPath: new URL(
				'../../../../../node_modules/@mediapipe/tasks-vision/wasm/vision_wasm_nosimd_internal.wasm',
				import.meta.url,
			).pathname,
		},
		modelAssetPath: new URL(
			'./vendor/models/selfie_segmenter.tflite',
			import.meta.url,
		).pathname,
	}
}

/**
 * Environment-agnostic MediaPipe segmenter: runs identically inside a worker
 * and on the main thread.
 *
 * @class
 */
export default class SegmenterCore {
	constructor() {
		this._imageSegmenter = null
		this._lastTimestamp = -1
	}

	/**
	 * Initialize the MediaPipe image segmenter.
	 *
	 * @param {object} assets - Asset config from getSegmenterAssetConfig().
	 * @return {Promise<void>}
	 */
	async init(assets) {
		if (!_WasmFileset) {
			// Checks inside, if SIMD is supported to load the appropriate fileset
			_WasmFileset = (await FilesetResolver.isSimdSupported()) ? assets.simd : assets.nosimd
		}

		this._imageSegmenter = await ImageSegmenter.createFromOptions(_WasmFileset, {
			baseOptions: {
				modelAssetPath: assets.modelAssetPath,
				delegate: 'GPU',
			},
			runningMode: 'VIDEO',
			outputCategoryMask: false,
			outputConfidenceMasks: true,
		})
	}

	/**
	 * Run segmentation inference on the given frame.
	 *
	 * Takes ownership of the frame: an ImageBitmap is closed when done.
	 *
	 * @param {ImageBitmap|HTMLVideoElement} frame - Frame to segment.
	 * @param {number} timestampMs - Capture timestamp in milliseconds.
	 * @return {Promise<{data: Uint8Array, width: number, height: number}|null>} the mask, or null if none.
	 */
	async segment(frame, timestampMs) {
		// MediaPipe VIDEO mode requires strictly increasing timestamps
		this._lastTimestamp = timestampMs > this._lastTimestamp ? timestampMs : this._lastTimestamp + 1

		let segmentationResult
		try {
			segmentationResult = await this._imageSegmenter.segmentForVideo(frame, this._lastTimestamp)

			const mask = segmentationResult.confidenceMasks?.[0]
			if (!mask) {
				return null
			}

			// Copy before transferring: MPMask may return an internally cached array
			return {
				data: new Uint8Array(mask.getAsUint8Array()),
				width: mask.width,
				height: mask.height,
			}
		} finally {
			segmentationResult?.categoryMask?.close()
			segmentationResult?.confidenceMasks?.forEach((mask) => mask.close())
			frame.close?.()
		}
	}

	/**
	 * Release the segmenter resources.
	 *
	 * @return {void}
	 */
	close() {
		this._imageSegmenter?.close()
		this._imageSegmenter = null
	}
}
