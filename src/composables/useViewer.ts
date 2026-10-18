/*!
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ViewerSession } from '@nextcloud/viewer'
import type { File as TalkFile, TemplateFile } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { File } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateRemoteUrl } from '@nextcloud/router'
import { canView, getViewer } from '@nextcloud/viewer'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'
import { generatePublicSharePreviewUrl } from '../utils/davUtils.ts'
import { useIsInCall } from './useIsInCall.js'

type FileAPIMap = {
	files: TemplateFile
	talk: TalkFile
}

type ViewerFileData = {
	id: number
	path: string | null | undefined
	name: string
	mime: string | undefined
	size: number | undefined
	permissions: number | undefined
	etag: string
	hasPreview: boolean
	link: string | undefined
}

/**
 * Make path absolute
 *
 * @param path path to file
 */
function generateAbsolutePath(path: string | null | undefined): string {
	if (!path) {
		return '/'
	}
	return path.startsWith('/') ? path : '/' + path
}

/**
 * Whether the file object comes from Files API
 *
 * @param fileAPI whether the file object comes from Files or Talk
 * @param file file object
 */
function isFilesAPI(fileAPI: keyof FileAPIMap, file: TemplateFile | TalkFile): file is TemplateFile {
	return fileAPI === 'files'
}

/**
 * Read the fields the Viewer needs from a Files or Talk file object
 *
 * @param fileAPI whether the file object comes from Files or Talk
 * @param file file object
 */
function getFileData(fileAPI: keyof FileAPIMap, file: TemplateFile | TalkFile): ViewerFileData {
	if (isFilesAPI(fileAPI, file)) {
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
 * @param fileAPI whether to treat file object as it comes from Files or Talk
 */
export function useViewer<T extends keyof FileAPIMap>(fileAPI: T) {
	const isInCall = useIsInCall()
	const callViewStore = useCallViewStore()
	const actorStore = useActorStore()

	/**
	 * Build a Viewer node from a Files or Talk file object
	 *
	 * @param file file object from Files or Talk
	 */
	function generateViewerObject(file: FileAPIMap[T]): File {
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
	 * @param file file object from Files or Talk
	 */
	function canOpenInViewer(file: FileAPIMap[T]): boolean {
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
	 * @param list file objects from Files or Talk
	 */
	function generateViewerObjects(list: FileAPIMap[T][]): File[] {
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
	 * @param list the list of the files to be opened
	 * @param fileInfo the file to open first, defaults to the first of the list
	 * @param loadMore the callback to load additional content
	 */
	async function openViewer(list: FileAPIMap[T][], fileInfo?: FileAPIMap[T], loadMore?: () => Promise<FileAPIMap[T][]>): Promise<void> {
		let session: ViewerSession
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
