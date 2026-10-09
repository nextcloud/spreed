<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- Based on vue-cropperjs@5.0.0 (https://github.com/Agontuk/vue-cropperjs) -->
<script setup lang="ts">
import type { CropperCanvas, CropperImage, CropperSelection } from 'cropperjs'

import Cropper from 'cropperjs'
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue'

type Rect = { x: number, y: number, width: number, height: number }

const MAX_OUTPUT_SIZE = 512

// Rounding of the selection position can put it up to 1px outside the image
const EDGE_TOLERANCE = 1

const RESIZE_HANDLES = ['n', 'e', 's', 'w', 'ne', 'nw', 'se', 'sw']
	.map((direction) => `<cropper-handle action="${direction}-resize"></cropper-handle>`)
	.join('')

// Options from v1: aspectRatio: 1, viewMode: 1, autoCropArea: 1, no guides, no center, no highlight
const TEMPLATE = '<cropper-canvas background>'
	+ '<cropper-image translatable scalable></cropper-image>'
	+ '<cropper-shade hidden></cropper-shade>'
	+ '<cropper-handle action="select" plain></cropper-handle>'
	+ '<cropper-selection aspect-ratio="1" movable resizable outlined>'
	+ '<cropper-handle action="move" plain></cropper-handle>'
	+ RESIZE_HANDLES
	+ '</cropper-selection>'
	+ '</cropper-canvas>'

const imageRef = useTemplateRef<HTMLImageElement>('image')

let cropper: Cropper | null = null
let cropperCanvas: CropperCanvas | null = null
let cropperImage: CropperImage | null = null
let cropperSelection: CropperSelection | null = null
let isInitializing = false

defineExpose({ replace, getCroppedCanvas })

onMounted(() => {
	cropper = new Cropper(imageRef.value!, { template: TEMPLATE })
	cropperCanvas = cropper.getCropperCanvas()
	cropperImage = cropper.getCropperImage()
	cropperSelection = cropper.getCropperSelection()

	cropperImage?.addEventListener('change', onImageChange)
	cropperSelection?.addEventListener('change', onSelectionChange)
})

onBeforeUnmount(() => {
	cropperImage?.removeEventListener('change', onImageChange)
	cropperSelection?.removeEventListener('change', onSelectionChange)
	cropper?.destroy()
})

/**
 * Get the image position and size, relative to the cropper canvas
 */
function getImageRect(): Rect {
	const canvasRect = cropperCanvas!.getBoundingClientRect()
	const imageRect = cropperImage!.$image.getBoundingClientRect()
	return {
		x: imageRect.x - canvasRect.x,
		y: imageRect.y - canvasRect.y,
		width: imageRect.width,
		height: imageRect.height,
	}
}

/**
 * Check if the inner rect is inside the outer rect
 *
 * @param inner - The rect to check
 * @param outer - The bounding rect
 */
function isInside(inner: Rect, outer: Rect): boolean {
	return inner.x >= outer.x - EDGE_TOLERANCE
		&& inner.y >= outer.y - EDGE_TOLERANCE
		&& inner.x + inner.width <= outer.x + outer.width + EDGE_TOLERANCE
		&& inner.y + inner.height <= outer.y + outer.height + EDGE_TOLERANCE
}

/**
 * Keep the selection inside the image (viewMode: 1 in v1)
 *
 * @param event - The selection change event
 */
function onSelectionChange(event: Event) {
	if (isInitializing) {
		return
	}
	if (!isInside((event as CustomEvent<Rect>).detail, getImageRect())) {
		event.preventDefault()
	}
}

/**
 * Keep the image covering the selection when zooming out (viewMode: 1 in v1)
 *
 * @param event - The image change event
 */
function onImageChange(event: Event) {
	if (isInitializing) {
		return
	}
	if (!isInside(cropperSelection!, (event as CustomEvent<Rect>).detail)) {
		event.preventDefault()
	}
}

/**
 * Replace the image's src and reset the cropper
 *
 * @param url - The new URL
 */
async function replace(url: string) {
	isInitializing = true
	try {
		cropperImage!.src = url
		await cropperImage!.$ready()

		cropperImage!.$resetTransform()
		cropperImage!.$center('contain')

		// Largest square inside the image (autoCropArea: 1 in v1)
		const imageRect = getImageRect()
		const size = Math.floor(Math.min(imageRect.width, imageRect.height))
		cropperSelection!.$change(
			Math.ceil(imageRect.x + (imageRect.width - size) / 2),
			Math.ceil(imageRect.y + (imageRect.height - size) / 2),
			size,
			size,
		)
	} finally {
		isInitializing = false
	}
}

/**
 * Get a canvas with the cropped image, max 512×512
 */
async function getCroppedCanvas(): Promise<HTMLCanvasElement> {
	const [scale] = cropperImage!.$getTransform()
	const naturalWidth = Math.round(cropperSelection!.width / scale)
	return cropperSelection!.$toCanvas({ width: Math.min(naturalWidth, MAX_OUTPUT_SIZE) })
}
</script>

<template>
	<div class="cropper">
		<img ref="image" alt="">
	</div>
</template>

<style lang="scss" scoped>
.cropper {
	:deep(cropper-canvas) {
		width: 100%;
		height: 100%;
	}

	:deep(cropper-selection),
	:deep(cropper-shade) {
		border-radius: 50%;
	}
}
</style>
