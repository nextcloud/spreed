<!--
  - SPDX-FileCopyrightText: 2016 Iftekhar Rifat
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: MIT
-->

<!-- Based on vue-cropperjs@5.0.0 (https://github.com/Agontuk/vue-cropperjs) -->
<template>
	<div>
		<img
			ref="img"
			alt="image"
			style="max-width: 100%">
	</div>
</template>

<script>
import Cropper from 'cropperjs'

import 'cropperjs/dist/cropper.css'

const MAX_OUTPUT_SIZE = 512

export default {
	name: 'ConversationAvatarCropper',

	props: {
		viewMode: {
			type: Number,
			default: 0,
		},

		aspectRatio: {
			type: Number,
			default: NaN,
		},

		guides: {
			type: Boolean,
			default: true,
		},

		center: {
			type: Boolean,
			default: true,
		},

		highlight: {
			type: Boolean,
			default: true,
		},

		autoCropArea: {
			type: Number,
			default: 0.8,
		},

		minContainerWidth: {
			type: Number,
			default: 200,
		},

		minContainerHeight: {
			type: Number,
			default: 100,
		},
	},

	expose: ['replace', 'getCroppedCanvas'],

	mounted() {
		this.cropper = new Cropper(this.$refs.img, { ...this.$props })
	},

	beforeUnmount() {
		this.cropper.destroy()
	},

	methods: {
		/**
		 * Replace the image's src and rebuild the cropper
		 *
		 * @param {string} url - The new URL.
		 */
		replace(url) {
			this.cropper.replace(url)
		},

		/**
		 * Get a canvas with the cropped image, max 512×512
		 *
		 * @return {HTMLCanvasElement} - The result canvas.
		 */
		getCroppedCanvas() {
			return this.cropper.getCroppedCanvas({
				maxWidth: MAX_OUTPUT_SIZE,
				maxHeight: MAX_OUTPUT_SIZE,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.cropper-view-box) {
	border-radius: 50%;
}
</style>
