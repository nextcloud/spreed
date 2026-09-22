/*!
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { File } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateRemoteUrl } from '@nextcloud/router'
import { canView, getViewer } from '@nextcloud/viewer'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'
import { generatePublicSharePreviewUrl } from '../utils/davUtils.ts'
import { useIsInCall } from './useIsInCall.js'

/**
 * Make path absolute
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
 * Read the fields the Viewer needs from a Files or Talk file object
 *
 * @param {'files'|'talk'} fileAPI whether the file object comes from Files or Talk
 * @param {object} file file object
 * @return {object}
 */
function getFileData(fileAPI, file) {
	if (fileAPI === 'files') {
		return {
			id: file.fileid,
			path: file.filename,
			name: file.basename,
			mime: file.mime,
			size: file.size,
			permissions: file.permissions,
			etag: file.etag,
			hasPreview: file.hasPreview,
			link: undefined,
		}
	}

	return {
		id: parseInt(file.id, 10),
		path: file.path,
		name: file.name,
		mime: file.mimetype,
		size: file.size === undefined ? undefined : Number(file.size),
		permissions: file.permissions === undefined ? undefined : Number(file.permissions),
		etag: file.etag,
		hasPreview: file['preview-available'] === 'yes',
		link: file.link,
	}
}

/**
 * Composable with `@nextcloud/viewer` helpers
 *
 * @param {'files'|'talk'} fileAPI whether to treat file object as it comes from Files or Talk
 * @return {{ openViewer: Function, canOpenInViewer: Function }}
 */
export function useViewer(fileAPI) {
	const isInCall = useIsInCall()
	const callViewStore = useCallViewStore()
	const actorStore = useActorStore()

	/**
	 * Build a Viewer node from a Files or Talk file object
	 *
	 * @param {object} file file object from Files or Talk
	 * @return {File}
	 */
	function generateViewerObject(file) {
		const userId = actorStore.userId
		const { id, path, name, mime, size, permissions, etag, hasPreview, link } = getFileData(fileAPI, file)
		const absolutePath = generateAbsolutePath(path)

		// Guests have no WebDAV access, use the share download link
		// Keep source unencoded: File.encodedSource encodes it later
		const source = userId
			? generateRemoteUrl(`dav/files/${userId}`) + absolutePath
			: `${link}/download/${name}`
		const root = userId ? `/files/${userId}` : '/'

		return new File({
			id,
			source,
			root,
			owner: userId ?? null,
			mime,
			size,
			displayname: name,
			permissions,
			attributes: {
				etag,
				hasPreview,
				// TODO: drop once @nextcloud/viewer stops falling back to /core/preview for guests
				previewUrl: !userId && hasPreview && link ? generatePublicSharePreviewUrl(link, name, { x: -1, y: -1 }) : undefined,
			},
		})
	}

	/**
	 * Check if the Viewer can open the file
	 *
	 * @param {object} file file object from Files or Talk
	 * @return {boolean}
	 */
	function canOpenInViewer(file) {
		try {
			return canView(generateViewerObject(file))
		} catch (error) {
			console.error('Failed to build a Viewer object', error)
			return false
		}
	}

	/**
	 * Build Viewer objects, skipping the files that fail validation
	 *
	 * @param {Array<object>} list file objects from Files or Talk
	 * @return {Array<File>}
	 */
	function generateViewerObjects(list) {
		return list.flatMap((file) => {
			try {
				return [generateViewerObject(file)]
			} catch (error) {
				console.error('Failed to build a Viewer object', error)
				return []
			}
		})
	}

	/**
	 * Open files in the Viewer taking into account Talk's call view
	 *
	 * @param {Array<object>} list the list of the files to be opened
	 * @param {object} [fileInfo] the file to open first, defaults to the first of the list
	 * @param {Function} [loadMore] the callback to load additional content
	 * @return {Promise<void>}
	 */
	async function openViewer(list, fileInfo, loadMore) {
		let session
		try {
			const files = generateViewerObjects(list)
			const file = fileInfo ? generateViewerObject(fileInfo) : files[0]

			if (isInCall.value) {
				callViewStore.setIsViewerOverlay(true)
			}

			session = await getViewer().open(files, file, {
				loadMore: loadMore && (async () => generateViewerObjects(await loadMore())),
				canLoop: false,
			})
		} catch (error) {
			console.error('Failed to open the Viewer', error)
			callViewStore.setIsViewerOverlay(false)
			showError(t('spreed', 'Error while opening file'))
			return
		}

		session.addEventListener('close', () => {
			callViewStore.setIsViewerOverlay(false)
		})
	}

	return {
		openViewer,
		canOpenInViewer,
	}
}
