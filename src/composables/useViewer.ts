/*!
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { NodeData } from '@nextcloud/files'
import type { ViewerSession } from '@nextcloud/viewer'
import type { File as TalkFile, TemplateFile } from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { subscribe } from '@nextcloud/event-bus'
import { File } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { generateRemoteUrl } from '@nextcloud/router'
import { canView, getViewer } from '@nextcloud/viewer'
import { reactive } from 'vue'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'
import { generatePublicShareDownloadUrl } from '../utils/davUtils.ts'
import { useIsInCall } from './useIsInCall.js'

type FileAPIMap = {
	files: TemplateFile
	talk: TalkFile
}

type ViewerFileData = Omit<NodeData, 'source' | 'root' | 'owner'> & {
	path: string | null | undefined
	link: string | undefined
}

// Etags of files saved in the Viewer, newer than the ones in loaded messages
// Rotation emits the node with its old etag (kept by Viewer on purpose), so rotated previews refresh only on reload
const savedEtags = reactive(new Map<string, string>())

subscribe('files:node:updated', (node) => {
	if (node.fileid !== undefined && node.attributes.etag) {
		savedEtags.set(String(node.fileid), node.attributes.etag)
	}
})

/**
 * Get the latest known etag of a file
 *
 * @param fileId file id
 * @param etag etag from the message
 */
export function getFileEtag(fileId: string | number, etag?: string): string | undefined {
	return savedEtags.get(String(fileId)) ?? etag
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
			displayname: file.basename,
			mime: file.mime,
			size: file.size,
			permissions: file.permissions,
			attributes: {
				etag: file.etag,
				hasPreview: file.hasPreview,
			},
			path: file.filename,
			link: undefined,
		}
	}

	return {
		id: parseInt(file.id, 10),
		displayname: file.name,
		mime: file.mimetype,
		size: file.size === undefined ? undefined : Number(file.size),
		permissions: file.permissions === undefined ? undefined : Number(file.permissions),
		attributes: {
			etag: getFileEtag(file.id, file.etag),
			hasPreview: file['preview-available'] === 'yes',
		},
		path: file.path,
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
		const { path, link, ...nodeData } = getFileData(fileAPI, file)
		const absolutePath = generateAbsolutePath(path)

		// Guests have no user WebDAV access, use the public share WebDAV
		// Keep source unencoded: File.encodedSource encodes it later
		const source = userId
			? generateRemoteUrl(`dav/files/${userId}`) + absolutePath
			: generatePublicShareDownloadUrl(link!) + (path?.startsWith('/') ? path : '')
		const root = userId ? `/files/${userId}` : '/'

		return new File({
			...nodeData,
			source,
			root,
			owner: userId ?? null,
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
