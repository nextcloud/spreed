import { join, relative } from 'node:path'

import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	// Talk pages
	main: join(import.meta.dirname, 'src', 'main.js'),
	'admin-settings': join(import.meta.dirname, 'src', 'mainAdminSettings.js'),
	recording: join(import.meta.dirname, 'src', 'mainRecording.js'),

	// Files integrations
	// 'files-sidebar': join(import.meta.dirname, 'src', 'mainFilesSidebar.js'),
	'public-share-auth-sidebar': join(import.meta.dirname, 'src', 'mainPublicShareAuthSidebar.js'),
	'public-share-sidebar': join(import.meta.dirname, 'src', 'mainPublicShareSidebar.js'),

	// Other integrations
	collections: join(import.meta.dirname, 'src', 'collections.js'),
	flow: join(import.meta.dirname, 'src', 'flow.js'),
	deck: join(import.meta.dirname, 'src', 'deck.js'),
	maps: join(import.meta.dirname, 'src', 'maps.js'),
	search: join(import.meta.dirname, 'src', 'search.js'),
}, {
	// Move CSS assets to js/css to other built files
	// Rename from default "spreed-*" to "talk-*"
	assetFileNames: (assetInfo) => {
		const extType = assetInfo.name?.split('.').at(-1)
		if (!extType) {
			return undefined
		}

		if (/css/i.test(extType)) {
			return 'js/css/[name].css'
		}

		// Use @nextcloud/vite-config default behavior
		return undefined
	},

	config: {
		assetsInclude: ['**/*.tflite', '**/*.wasm'],

		build: {
			cssCodeSplit: true,

			rollupOptions: {
				output: {
					entryFileNames: 'js/talk-[name].mjs',
					chunkFileNames: 'js/chunks/[name].mjs',
				},
			},

			// Support vendors mediapipe modules.
			// Usually we need to transform Commonjs only from CJS dependencies in node_modules
			// But Talk also has CJS dependencies in src/utils/media/effects/virtual-background/vendor/ which are not compatible with ESM
			commonjsOptions: {
				include: [/node_modules/, /src[/\\]utils[/\\]media[/\\]effects[/\\]virtual-background[/\\]vendor/],
				transformMixedEsModules: true,
			},
		},

		worker: {
			rollupOptions: {
				output: {
					// Move worker and its assets and chunks to assets and chunks of main entrypoints
					// Note: JitsiStreamBackgroundEffect worker's assets (wasm/tflite) must keep original names
					entryFileNames: 'js/assets/[name].mjs',
					assetFileNames: 'js/assets/[name][extname]',
					chunkFileNames: 'js/chunks/[name].mjs',
				},
			},
		},

		experimental: {
			// TODO: find a way to reuse @nextcloud/vite-config or update upstream to support Workers
			renderBuiltUrl(filename, { hostId, hostType }) {
				// Workers doesn't have window object and cannot use window.OC.filePath
				// Use relative import instead
				if (hostId.endsWith('.worker.mjs')) {
					return {
						relative: true,
					}
				}

				// Copy from @nextcloud/vite-config...
				if (hostType === 'css') {
					return relative('../css', `../${filename}`)
				}

				return {
					runtime: `window.OC.filePath('spreed', '', '${filename}')`,
				}
			},
		},

		define: {
			IS_DESKTOP: false
		},
	}
})
