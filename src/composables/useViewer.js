/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { File } from '@nextcloud/files'
import { encodePath } from '@nextcloud/paths'
import { generateRemoteUrl } from '@nextcloud/router'
import { getViewer, registerDefaultHandlers } from '@nextcloud/viewer'
import { nextTick, ref } from 'vue'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'
import { useSidebarStore } from '../stores/sidebar.ts'
import { useIsInCall } from './useIsInCall.js'

// The viewer only ships handlers for images/video/audio if the page itself
// registers them (importing the package deliberately does not). Talk's own
// pages don't load the Files app's init script, so register here instead.
// Safe to call from every copy of this module: a call after the first does nothing.
registerDefaultHandlers()

/**
 * @callback OpenViewer
 *
 * @description Open files in the Viewer taking into account Talk's fullscreen mode and call view
 * @see https://github.com/nextcloud-libraries/nextcloud-viewer
 * @param {Array<object>} list - The list of the files to be opened
 * @param {object} [fileInfo] - The known file info, opened by default if not given the first of the list
 * @param {Function} [loadMore] - The callback to load additional content
 */

/**
 *
 * @param {string} [path] path to file
 * @return {string}
 */
function generateAbsolutePath(path) {
	if (!path) {
		return '/'
	}
	return path.startsWith('/') ? path : '/' + path
}

/**
 * Is Viewer currently opened
 *
 * @type {import('vue').Ref<boolean>}
 */
const isViewerOpen = ref(false)

/**
 * Composable with @nextcloud/viewer helpers
 *
 * @param {'files'|'talk'} fileAPI whether to treat file object as it comes from Files or Talk
 * @return {{ openViewer: OpenViewer, isViewerOpen: import('vue').Ref<boolean>, toViewerFile: Function }}
 */
export function useViewer(fileAPI) {
	const isInCall = useIsInCall()
	const callViewStore = useCallViewStore()
	const sidebarStore = useSidebarStore()
	const actorStore = useActorStore()

	/**
	 * Build an IFile node to be used by the Viewer, from a Files or Talk file object
	 *
	 * @param {object} file file object (from Files API or Talk API)
	 */
	function toViewerFile(file) {
		const userId = actorStore.userId
		const path = generateAbsolutePath(fileAPI === 'files' ? file.filename : file.path)
		const name = fileAPI === 'files' ? file.basename : file.name

		// Guests (public shares) have no WebDAV access, so fall back to the
		// file's public download link like FilePreview's directImageUrl does.
		const source = userId
			? generateRemoteUrl(`dav/files/${userId}`) + encodePath(path)
			: `${file.link}/download/${encodePath(name)}`
		const root = userId ? `/files/${userId}` : '/'

		return new File({
			id: fileAPI === 'files' ? file.fileid : parseInt(file.id, 10),
			source,
			root,
			owner: userId ?? null,
			mime: fileAPI === 'files' ? file.mime : file.mimetype,
			size: file.size === undefined ? undefined : Number(file.size),
			displayname: fileAPI === 'files' ? file.basename : file.name,
			permissions: file.permissions === undefined ? undefined : Number(file.permissions),
			attributes: {
				etag: file.etag,
				hasPreview: fileAPI === 'files'
					? file.hasPreview
					: (file.previewAvailable === 'yes' || file['preview-available'] === 'yes'),
			},
		})
	}

	/**
	 * @type {OpenViewer}
	 */
	const openViewer = async (list, fileInfo, loadMore) => {
		if (isInCall.value) {
			callViewStore.setIsViewerOverlay(true)
		}

		const files = list.map(toViewerFile)
		const file = fileInfo ? toViewerFile(fileInfo) : files[0]

		await getViewer().open(files, file, {
			onClose: () => {
				isViewerOpen.value = false
				callViewStore.setIsViewerOverlay(false)
			},
			loadMore: loadMore && (async (...args) => (await loadMore(...args)).map(toViewerFile)),
			canLoop: false,
		})

		// Wait Viewer to be mounted
		await nextTick()

		isViewerOpen.value = true
	}

	return {
		isViewerOpen,
		openViewer,
		toViewerFile,
	}
}
