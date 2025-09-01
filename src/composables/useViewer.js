/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { nextTick, ref } from 'vue'
import { useCallViewStore } from '../stores/callView.ts'
import { useSidebarStore } from '../stores/sidebar.ts'
import { useIsInCall } from './useIsInCall.js'

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
 * Is Viewer currently opened
 *
 * @type {import('vue').Ref<boolean>}
 */
const isViewerOpen = ref(false)

/**
 * Composable with OCA.Viewer helpers
 *
 * @param {'files'|'talk'} fileAPI whether to treat file object as it comes from Files or Talk
 * @return {{ openViewer: OpenViewer, isViewerOpen: import('vue').Ref<boolean> }}
 */
export function useViewer(fileAPI) {
	const isInCall = useIsInCall()
	const callViewStore = useCallViewStore()
	const sidebarStore = useSidebarStore()

	/**
	 * Map object to be used by Viewer
	 *
	 * @param {object} file file object (from Files API or Talk API)
	 */
	function generateViewerObject(file) {
		switch (fileAPI) {
			case 'files': return {
				...file,
				permissions: generatePermissions(file.permissions), // Viewer expects a String instead of Bitmask
			}
			case 'talk':
			default: return {
				fileid: parseInt(file.id, 10),
				filename: generateAbsolutePath(file.path),
				basename: file.name,
				mime: file.mimetype,
				hasPreview: (file.previewAvailable === 'yes' || file['preview-available'] === 'yes'),
				etag: file.etag,
				permissions: generatePermissions(file.permissions), // Viewer expects a String instead of Bitmask
			}
		}
	}

	/**
	 * @type {OpenViewer}
	 */
	const openViewer = async (path, list, fileInfo, loadMore) => {
		if (!OCA.Viewer) {
			return false
		}

		// The Viewer expects a file to be set in the sidebar if the sidebar is open
		if (sidebarStore.show) {
			OCA.Files.Sidebar.state.file = path
		}

		if (isInCall.value) {
			callViewStore.setIsViewerOverlay(true)
		}

		OCA.Viewer.open({
			path,
			list: list.map(generateViewerObject),
			fileInfo: generateViewerObject(fileInfo),
			onClose: () => {
				isViewerOpen.value = false
				callViewStore.setIsViewerOverlay(false)
			},
			loadMore,
			canLoop: false,
		})

		// Wait Viewer to be mounted
		await nextTick()

		isViewerOpen.value = true
	}

	return {
		isViewerOpen,
		openViewer,
	}
}
