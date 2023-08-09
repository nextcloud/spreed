import path from 'node:path'
import { createAppConfig } from '@nextcloud/vite-config'
import { defineConfig } from 'vite'

export default createAppConfig({
	'admin-settings': path.join(__dirname, 'src', 'mainAdminSettings.js'),
	collections: path.join(__dirname, 'src', 'collections.js'),
	main: path.join(__dirname, 'src', 'main.js'),
	recording: path.join(__dirname, 'src', 'mainRecording.js'),
	// TODO: fixme
	//	'files-sidebar': [
	//		path.join(__dirname, 'src', 'mainFilesSidebar.js'),
	//		path.join(__dirname, 'src', 'mainFilesSidebarLoader.js'),
	//	],
	'public-share-auth-sidebar': path.join(__dirname, 'src', 'mainPublicShareAuthSidebar.js'),
	'public-share-sidebar': path.join(__dirname, 'src', 'mainPublicShareSidebar.js'),
	flow: path.join(__dirname, 'src', 'flow.js'),
	dashboard: path.join(__dirname, 'src', 'dashboard.js'),
	deck: path.join(__dirname, 'src', 'deck.js'),
	maps: path.join(__dirname, 'src', 'maps.js'),
}, {
	config: defineConfig({
		assetsInclude:  ['**/*.tflite'],

		build: {
			commonjsOptions: {
				include: [/node_modules/, /vendor/],
				transformMixedEsModules: true,
				// FOR virtual-background/vendor/tflite
				requireReturnsDefault: 'debug',
			},
		},

		define: {
			IS_DESKTOP: false
		},
	})
})