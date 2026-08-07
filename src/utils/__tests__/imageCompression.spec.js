/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { compressImage } from '../imageCompression.ts'

/** Fake 2D rendering context shared by all canvases of a single test */
let context
/** Whether getContext() should return a context at all */
let contextAvailable
/** All canvases created during a test, in order of creation */
let canvases
/** Options the canvas encoder was called with, in order */
let encodeCalls
/** Descriptor of the blob returned by the canvas encoder */
let encoded
/** The bitmap returned by the mocked createImageBitmap() */
let bitmap

/**
 * Builds a blob of the given type and byte size
 *
 * @param descriptor the blob descriptor
 * @param descriptor.type the MIME type of the blob
 * @param descriptor.size the byte size of the blob
 */
function makeBlob({ type, size }) {
	return new Blob([new Uint8Array(size)], { type })
}

/**
 * Creates a File of the given type and byte size
 *
 * @param name the file name
 * @param options the file options
 * @param options.type the MIME type of the file
 * @param options.size the byte size of the file
 * @param options.lastModified the last modification timestamp
 */
function makeFile(name, { type = 'image/png', size = 4096, lastModified = 1600000000000 } = {}) {
	return new File([new Uint8Array(size)], name, { type, lastModified })
}

describe('imageCompression', () => {
	beforeEach(() => {
		context = {
			imageSmoothingQuality: undefined,
			fillStyle: undefined,
			fillRect: vi.fn(),
			drawImage: vi.fn(),
		}
		contextAvailable = true
		canvases = []
		encodeCalls = []
		encoded = { type: 'image/webp', size: 512 }
		bitmap = { width: 800, height: 600, close: vi.fn() }

		global.createImageBitmap = vi.fn(() => Promise.resolve(bitmap))

		// Adjust the OffscreenCanvas mocked in test-setup.js
		vi.spyOn(OffscreenCanvas.prototype, 'getContext').mockImplementation(function() {
			canvases.push(this)
			return contextAvailable ? context : null
		})
		vi.spyOn(OffscreenCanvas.prototype, 'convertToBlob').mockImplementation(({ type, quality }) => {
			encodeCalls.push({ type, quality })
			return Promise.resolve(makeBlob(encoded))
		})
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete global.createImageBitmap
	})

	describe('compressImage', () => {
		it('re-encodes the image as WebP and adjusts the extension', async () => {
			const file = makeFile('pngimage.png', { type: 'image/png', size: 4096 })

			const result = await compressImage(file)

			expect(result).not.toBe(file)
			expect(result.name).toBe('pngimage.webp')
			expect(result.type).toBe('image/webp')
			expect(result.size).toBe(512)
			expect(encodeCalls).toEqual([{ type: 'image/webp', quality: 0.8 }])
		})

		it('preserves the last modification time of the original file', async () => {
			const file = makeFile('pngimage.png', { lastModified: 1234567890000 })

			const result = await compressImage(file)

			expect(result.lastModified).toBe(1234567890000)
		})

		it('scales oversized images down, preserving the aspect ratio', async () => {
			bitmap = { width: 2560, height: 1440, close: vi.fn() }

			await compressImage(makeFile('pngimage.png'))

			expect(canvases.at(-1)).toMatchObject({ width: 1280, height: 720 })
			expect(context.drawImage).toHaveBeenCalledWith(bitmap, 0, 0, 1280, 720)
		})

		it('scales by the longer side for portrait images', async () => {
			bitmap = { width: 1000, height: 4000, close: vi.fn() }

			await compressImage(makeFile('pngimage.png'))

			expect(canvases.at(-1)).toMatchObject({ width: 320, height: 1280 })
		})

		it('does not upscale images smaller than the limit', async () => {
			bitmap = { width: 320, height: 240, close: vi.fn() }

			await compressImage(makeFile('pngimage.png'))

			expect(canvases.at(-1)).toMatchObject({ width: 320, height: 240 })
			expect(context.drawImage).toHaveBeenCalledWith(bitmap, 0, 0, 320, 240)
		})

		it('honours the given quality and maximum side', async () => {
			bitmap = { width: 1000, height: 500, close: vi.fn() }

			await compressImage(makeFile('pngimage.png'), 0.5, 100)

			expect(canvases.at(-1)).toMatchObject({ width: 100, height: 50 })
			expect(encodeCalls).toEqual([{ type: 'image/webp', quality: 0.5 }])
		})

		it('returns null when re-encoding does not reduce the size', async () => {
			const file = makeFile('pngimage.png', { size: 512 })
			encoded = { type: 'image/webp', size: 512 }

			const result = await compressImage(file)

			expect(result).toBeNull()
		})

		it('trusts the encoder type over the requested one', async () => {
			// Some engines silently fall back to a lossless PNG encode
			encoded = { type: 'image/png', size: 512 }

			const result = await compressImage(makeFile('pngimage.png'))

			expect(result.name).toBe('pngimage.png')
			expect(result.type).toBe('image/png')
		})

		it.each([
			['pngimage.png', 'pngimage.webp'],
			['my.photo.from.2026.jpeg', 'my.photo.from.2026.webp'],
			['no-extension', 'no-extension.webp'],
			// A leading dot is treated as an extension separator
			['.hidden', '.webp'],
		])('renames %s to %s', async (name, expectedName) => {
			const result = await compressImage(makeFile(name))

			expect(result.name).toBe(expectedName)
		})
	})
})
