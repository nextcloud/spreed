/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { resolve } from 'node:path'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	plugins: [vue()],
	assetsInclude: ['**/*.tflite', '**/*.wasm'],
	test: {
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		exclude: [
			// TODO: migrate to Vue 3
			'src/components/**',
			// FIXME: broken after Vue 3 migration
			'src/store/fileUploadStore.spec.js',
			// FIXME: broken after Vitest migration
			'src/utils/SignalingTypingHandler.spec.js',
			'src/utils/media/pipeline/MediaDevicesSource.spec.js',
			'src/store/messagesStore.spec.js',
		],
		server: {
			deps: {
				// Allow importing CSS from dependencies
				inline: ['@nextcloud/vue'],
			},
		},
		alias: [
			{ find: './vendor/tflite/tflite.wasm', replacement: resolve(import.meta.dirname, 'src/utils/media/effects/virtual-background/vendor/tflite/tflite.js') },
			{ find: './vendor/tflite/tflite-simd.wasm', replacement: resolve(import.meta.dirname, 'src/utils/media/effects/virtual-background/vendor/tflite/tflite-simd.js') },
			{ find: '@matrix-org/olm/olm.wasm', replacement: '@matrix-org/olm/olm.js' },
		],
		environment: 'jsdom',
		environmentOptions: {
			jsdom: {
				url: 'http://localhost',
			},
		},
		setupFiles: ['src/test-setup.js'],
		globalSetup: 'src/test-global-setup.js',
	},
})
