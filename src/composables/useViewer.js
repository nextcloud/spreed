/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, nextTick, ref, watch } from 'vue'

import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'

/**
 * @callback OpenViewer
 *
 * @description Open files in the OCA.Viewer taking into account Talk's fullscreen mode and call view
 * @see https://github.com/nextcloud/viewer
 * @param {string} path - The path to the file to be open
 * @param {Array<object>} list - The list of the files to be opened
 * @param {object} [fileInfo] - The known file info
 * @param {Function} [loadMore] - The callback to load additional content
 */

/**
 *
 * @param {string} path path to file
 * @return {string}
 */
function generateAbsolutePath(path) {
	return path.startsWith('/') ? path : '/' + path
}

/**
 *
 * @param {number} filePermissions file permissions in a bit notation
 * @return {string}
 */
function generatePermissions(filePermissions) {
	let permissions = ''

	if (filePermissions & OC.PERMISSION_CREATE) {
		permissions += 'CK'
	}
	if (filePermissions & OC.PERMISSION_READ) {
		permissions += 'G'
	}
	if (filePermissions & OC.PERMISSION_UPDATE) {
		permissions += 'W'
	}
	if (filePermissions & OC.PERMISSION_DELETE) {
		permissions += 'D'
	}
	if (filePermissions & OC.PERMISSION_SHARE) {
		permissions += 'R'
	}

	return permissions
}

/**
 * FIXME Remove this hack once it is possible to set the parent
 * element of the viewer.
 * By default the viewer is a sibling of the fullscreen element, so
 * it is not visible when in fullscreen mode. It is not possible to
 * specify the parent nor to know when the viewer was actually
 * opened, so for the time being it is reparented if needed shortly
 * after calling it.
 *
 * @see https://github.com/nextcloud/viewer/issues/995
 *
 * @param {boolean} isFullscreen - is currently in fullscreen mode
 */
function reparentViewer(isFullscreen) {
	const viewerElement = document.getElementById('viewer')

	if (isFullscreen) {
		// When changed to the fullscreen mode, Viewer should be moved to the talk app
		document.getElementById('content-vue').appendChild(viewerElement)
	} else {
		// In normal mode if it was in fullscreen before, move back to body
		// Otherwise it will be overlapped by web-page's header
		document.body.appendChild(viewerElement)
	}
}

/**
 * Is Viewer currently opened
 *
 * @type {import('vue').Ref<boolean>}
 */
const isViewerOpen = ref(false)

/**
 * Composable with OCA.Viewer helpers
 *
 * @return {{ openViewer: OpenViewer, isViewerOpen: import('vue').Ref<boolean> }}
 */
export function useViewer() {
	const store = useStore()
	const isInCall = useIsInCall()
	const isFullscreen = computed(() => store.getters.isFullscreen())

	watch(isFullscreen, () => {
		if (isViewerOpen.value) {
			reparentViewer(isFullscreen.value)
		}
	})

	const generateViewerObject = (file) => ({
		fileid: file.fileid ?? parseInt(file.id, 10),
		filename: file.filename ?? generateAbsolutePath(file.path),
		basename: file.basename ?? file.name,
		mime: file.mime ?? file.mimetype,
		hasPreview: file.hasPreview ?? (file.previewAvailable === 'yes' || file['preview-available'] === 'yes'),
		etag: file.etag,
		permissions: generatePermissions(file.permissions), // Viewer expects a String instead of Bitmask
	})

	/**
	 * @type {OpenViewer}
	 */
	const openViewer = async (path, list, fileInfo, loadMore) => {
		if (!OCA.Viewer) {
			return false
		}

		// The Viewer expects a file to be set in the sidebar if the sidebar is open
		if (store.getters.getSidebarStatus) {
			OCA.Files.Sidebar.state.file = path
		}

		if (isInCall.value) {
			store.dispatch('setCallViewMode', { isViewerOverlay: true })
		}

		OCA.Viewer.open({
			path,
			list: list.map(generateViewerObject),
			fileInfo: generateViewerObject(fileInfo),
			onClose: () => {
				isViewerOpen.value = false
				store.dispatch('setCallViewMode', { isViewerOverlay: false })
			},
			loadMore,
			canLoop: false,
		})

		// Wait Viewer to be mounted
		await nextTick()

		isViewerOpen.value = true

		if (isFullscreen.value) {
			reparentViewer(true)
		}
	}

	return {
		isViewerOpen,
		openViewer,
	}
}
