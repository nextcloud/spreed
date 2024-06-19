import { join } from 'node:path'

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
	inlineCSS: true,

	config: {
		assetsInclude: ['**/*.tflite', '**/*.wasm'],

		build: {
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

		define: {
			IS_DESKTOP: false
		},
	}
})
