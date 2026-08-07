/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Types to re-encode on upload.
 *
 * Excluded types:
 * - GIF - to keep the animation;
 * - SVG - not worth to resterize the vector;
 * - HEIC/HEIF, TIFF, RAW - need a heavy library.
 */
const COMPRESSIBLE_TYPES = [
	'image/avif',
	'image/bmp',
	'image/jpeg',
	'image/png',
	'image/webp',
	'image/x-icon',
]

/** Unified output format (lossy WebP is ~25-35% smaller than JPEG and supports an alpha channel) */
const OUTPUT_TYPE = 'image/webp'
/** Fallback used where WebP encoding is unavailable */
const FALLBACK_TYPE = 'image/jpeg'
/** Default image compression quality for formats with lossy compression support (80%) */
const COMPRESS_QUALITY = 0.8
/** Default max resolution of compressed image in pixels (matches HD resolution) */
const COMPRESS_MAX_RESOLUTION = 1280

const EXTENSIONS = {
	'image/webp': '.webp',
	'image/jpeg': '.jpg',
	'image/png': '.png',
}

type EncodableCanvas = OffscreenCanvas | HTMLCanvasElement

let isOffscreenCanvasSupported: boolean

/**
 * Detect whether OffscreenCanvas can be used (missing on Safari < 16.4 and some engines without a 2D encoder)
 */
function supportOffscreenCanvas(): boolean {
	isOffscreenCanvasSupported ??= typeof OffscreenCanvas !== 'undefined'
		&& typeof OffscreenCanvas.prototype.convertToBlob === 'function'

	return isOffscreenCanvasSupported
}

let isWebpEncodingSupported: boolean

/**
 * Detect whether the canvas encoder can produce WebP (missing on Safari < 16.4).
 * Browser fallback is lossless PNG encode.
 */
function supportWebpEncoding(): boolean {
	if (typeof isWebpEncodingSupported !== 'undefined') {
		return isWebpEncodingSupported
	}
	if (typeof document === 'undefined') {
		return false
	}
	try {
		const canvas = document.createElement('canvas')
		canvas.width = 1
		canvas.height = 1
		isWebpEncodingSupported ??= canvas.toDataURL('image/webp')?.startsWith('data:image/webp') ?? false
	} catch (error) {
		isWebpEncodingSupported ??= false
	}
	return isWebpEncodingSupported
}

/**
 * Creates the canvas for off-screen encoding.
 *
 * @param width The canvas width in pixels
 * @param height The canvas height in pixels
 */
function createCanvas(width: number, height: number): EncodableCanvas {
	if (supportOffscreenCanvas()) {
		return new OffscreenCanvas(width, height)
	}
	const canvas = document.createElement('canvas')
	canvas.width = width
	canvas.height = height
	return canvas
}

/**
 * Encodes the canvas contents
 *
 * @param canvas The canvas to encode
 * @param type The requested output MIME type
 * @param quality The encoder quality (between 0 and 1)
 */
async function encodeCanvas(canvas: EncodableCanvas, type: string, quality: number): Promise<Blob> {
	if ('convertToBlob' in canvas) {
		return await canvas.convertToBlob({ type, quality })
	}

	return await new Promise((resolve, reject) => {
		canvas.toBlob(
			(blob) => blob ? resolve(blob) : reject(new Error('[imageCompression] Failed to encode canvas')),
			type,
			quality,
		)
	})
}

/**
 * Whether a file of the given MIME type can be re-encoded by compressImage.
 *
 * @param mimeType The MIME type of the file
 */
export function supportImageCompression(mimeType: string): boolean {
	return COMPRESSIBLE_TYPES.includes(mimeType)
}

/**
 * Scale dimensions down so neither side exceeds maxResolution, preserving aspect ratio.
 *
 * @param width The original width in pixels
 * @param height The original height in pixels
 * @param maxResolution The maximum allowed resolution in pixels
 */
function scaledDimensions(width: number, height: number, maxResolution: number): { width: number, height: number } {
	if (width <= maxResolution && height <= maxResolution) {
		return { width, height }
	}
	const ratio = Math.min(maxResolution / width, maxResolution / height)
	return { width: Math.round(width * ratio), height: Math.round(height * ratio) }
}

/**
 * Compresses an image file via the Canvas API.
 * - preserve the EXIF orientation before drawing ('from-image'), while stripping EXIF tag;
 * - outputs WebP (JPEG as fallback) file with adjusted extension;
 * - scales down images larger than maxResolution on either axis;
 * - returns null when the encode fails, or when re-encoding would not make the file smaller.
 *
 * @param file The image file to compress
 * @param quality The encoder quality (between 0 and 1)
 * @param maxResolution The maximum allowed resolution in pixels
 */
export async function compressImage(file: File, quality = COMPRESS_QUALITY, maxResolution = COMPRESS_MAX_RESOLUTION): Promise<File | null> {
	const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
	const dimensionsOriginal = `${bitmap.width}×${bitmap.height}`
	const { width, height } = scaledDimensions(bitmap.width, bitmap.height, maxResolution)

	const outputType = supportWebpEncoding() ? OUTPUT_TYPE : FALLBACK_TYPE
	const canvas = createCanvas(width, height)
	const ctx = canvas.getContext('2d') as OffscreenCanvasRenderingContext2D | CanvasRenderingContext2D | null
	if (!ctx) {
		bitmap.close()
		console.warn('[imageCompression] Failed to get 2D canvas context')
		return null
	}

	try {
		ctx.imageSmoothingQuality = 'high'
		if (outputType === FALLBACK_TYPE) {
			// JPEG has no alpha channel, so composite onto white instead of black
			ctx.fillStyle = '#ffffff'
			ctx.fillRect(0, 0, width, height)
		}
		ctx.drawImage(bitmap, 0, 0, width, height)
	} catch (e) {
		console.warn('[imageCompression] Failed to draw image on canvas')
		return null
	} finally {
		// Bitmap is no longer required, close both in case of success or exception
		bitmap.close()
	}

	const blob = await encodeCanvas(canvas, outputType, quality)
	// An unsupported type silently yields a PNG - trust the encoder to assign type
	const type = blob.type as keyof typeof EXTENSIONS
	const name = file.name.replace(/\.[^.]+$/, '') + (EXTENSIONS[type] ?? '')
	const compressed = new File([blob], name, { type, lastModified: file.lastModified })

	if (compressed.size >= file.size) {
		console.debug('[DEBUG] spreed: imageCompression skipped - re-encoding did not reduce the size')
		return null
	}

	console.debug(
		'[DEBUG] spreed: image compressed with %s loss:\n<<< %s (%s, %s)\n>>> %s (%s, %s)',
		// Loss
		((1 - compressed.size / file.size) * 100).toFixed(1) + '%',
		// Original
		file.name,
		dimensionsOriginal,
		(file.size / 1024).toFixed(1) + ' KiB',
		// Reduced
		compressed.name,
		`${width}×${height}`,
		(compressed.size / 1024).toFixed(1) + ' KiB',
	)

	return compressed
}
