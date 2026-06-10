/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import SegmenterCore, { getSegmenterAssetConfig } from './SegmenterCore.js'

let _workerSupported

/**
 * Returns whether segmentation can run in a worker: requires Worker support,
 * createImageBitmap to capture frames, and an OffscreenCanvas with WebGL2 for
 * the MediaPipe GPU delegate inside the worker.
 *
 * @return {boolean} true if the worker segmenter is supported.
 */
export function isWorkerSegmenterSupported() {
	if (_workerSupported === undefined) {
		try {
			_workerSupported = typeof Worker !== 'undefined'
				&& typeof createImageBitmap === 'function'
				&& typeof OffscreenCanvas !== 'undefined'
				&& !!new OffscreenCanvas(1, 1).getContext('webgl2')
		} catch (e) {
			_workerSupported = false
		}
	}

	return _workerSupported
}

/**
 * Creates a segmenter running in a worker when supported, falling back to
 * main thread inference otherwise.
 *
 * Both implementations expose the same interface:
 * - init(): Promise<void>
 * - segment(frame, timestampMs): Promise<{data, width, height}|null> - takes ownership of frame
 * - destroy(): void
 *
 * @return {WorkerSegmenter|LocalSegmenter} the segmenter client.
 */
export function createSegmenter() {
	return isWorkerSegmenterSupported() ? new WorkerSegmenter() : new LocalSegmenter()
}

/**
 * Segmenter client running inference in a web worker.
 *
 * @class
 */
class WorkerSegmenter {
	constructor() {
		this._worker = null
		this._pendingSegment = null
	}

	/**
	 * Spawn the worker and initialize MediaPipe inside it.
	 *
	 * @return {Promise<void>} resolved once the model is loaded in the worker.
	 */
	init() {
		return new Promise((resolve, reject) => {
			this._worker = new Worker(
				new URL('./Segmenter.worker.js', import.meta.url),
				{ name: 'Virtual background segmenter' },
			)
			this._worker.onerror = (error) => {
				reject(error)
				this._resolvePendingSegment(null)
			}
			this._worker.onmessage = ({ data }) => {
				switch (data.type) {
					case 'initDone': {
						resolve()
						break
					}
					case 'initError': {
						reject(new Error(data.message))
						break
					}
					case 'segmentDone': {
						this._resolvePendingSegment(data.mask)
						break
					}
					case 'segmentError': {
						console.error('MediaPipe inference failed:', data.message)
						this._resolvePendingSegment(null)
						break
					}
				}
			}
			this._worker.postMessage({ type: 'init', assets: getSegmenterAssetConfig() })
		})
	}

	/**
	 * Run inference on the given frame in the worker.
	 *
	 * Single-flight: rejects if a previous segmentation is still pending.
	 *
	 * @param {ImageBitmap} frame - Frame to segment, transferred to the worker.
	 * @param {number} timestampMs - Capture timestamp in milliseconds.
	 * @return {Promise<{data: Uint8Array, width: number, height: number}|null>} the mask, or null.
	 */
	segment(frame, timestampMs) {
		return new Promise((resolve, reject) => {
			if (this._pendingSegment) {
				reject(new Error('Segmentation is already in progress'))
				return
			}
			if (!this._worker) {
				resolve(null)
				return
			}

			this._pendingSegment = resolve
			this._worker.postMessage({ type: 'segment', frame, timestampMs }, [frame])
		})
	}

	/**
	 * Terminate the worker and settle any pending segmentation.
	 *
	 * @return {void}
	 */
	destroy() {
		if (!this._worker) {
			return
		}

		this._worker.postMessage({ type: 'close' })
		this._worker.onmessage = null
		this._worker.onerror = null
		this._worker.terminate()
		this._worker = null

		this._resolvePendingSegment(null)
	}

	/**
	 * @private
	 * @param {object|null} mask - Result to settle the pending segmentation with.
	 * @return {void}
	 */
	_resolvePendingSegment(mask) {
		const resolve = this._pendingSegment
		this._pendingSegment = null
		resolve?.(mask)
	}
}

/**
 * Segmenter client running inference on the main thread (fallback).
 *
 * @class
 */
class LocalSegmenter {
	constructor() {
		this._core = new SegmenterCore()
	}

	/**
	 * @return {Promise<void>} resolved once the model is loaded.
	 */
	init() {
		return this._core.init(getSegmenterAssetConfig())
	}

	/**
	 * @param {ImageBitmap|HTMLVideoElement} frame - Frame to segment.
	 * @param {number} timestampMs - Capture timestamp in milliseconds.
	 * @return {Promise<{data: Uint8Array, width: number, height: number}|null>} the mask, or null.
	 */
	async segment(frame, timestampMs) {
		try {
			return await this._core.segment(frame, timestampMs)
		} catch (error) {
			console.error('MediaPipe inference failed:', error)
			return null
		}
	}

	/**
	 * @return {void}
	 */
	destroy() {
		this._core.close()
	}
}
