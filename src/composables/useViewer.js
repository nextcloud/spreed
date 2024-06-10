/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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
 * @param {'files'|'talk'} fileAPI whether to treat file object as it comes from Files or Talk
 * @return {{ openViewer: OpenViewer, isViewerOpen: import('vue').Ref<boolean> }}
 */
export function useViewer(fileAPI) {
	const store = useStore()
	const isInCall = useIsInCall()
	const isFullscreen = computed(() => store.getters.isFullscreen())

	watch(isFullscreen, () => {
		if (isViewerOpen.value) {
			reparentViewer(isFullscreen.value)
		}
	})

	/**
	 * Map object to be used by Viewer
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
