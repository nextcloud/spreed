/*!
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { defaultRemoteURL } from '@nextcloud/files/dav'
import { encodePath } from '@nextcloud/paths'
import { generateUrl, getBaseUrl } from '@nextcloud/router'

/**
 * Generate a WebDAV url to a user files
 *
 * @param filepath - The path to the user's file, e.g., Talk/20240101-000102.png
 * @param userid - The user id, e.g., 'admin'. Defaults to the current user id
 * @return The WebDAV URL to the file, e.g., https://nextcloud.ltd/remote.php/dav/files/admin/Talk/20240101-000102.png
 */
export function generateUserFileUrl(filepath: string, userid: string | undefined = getCurrentUser()?.uid) {
	if (!userid) {
		throw new TypeError('Cannot generate /files/<user>/ URL without a user')
	}
	return defaultRemoteURL + '/files/' + encodeURI(userid) + '/' + encodeURI(filepath)
}

/**
 * Generate a WebDAV url to a user folder (to download as a ZIP archive).
 * Mirrors Files app behaviour (appends trailing slash and `accept=zip` to the DAV URL).
 *
 * @param filepath - The path to the user's folder, e.g., Talk/Media
 * @param userid - The user id, e.g., 'admin'. Defaults to the current user id
 * @return The WebDAV URL to download the folder as ZIP, e.g., https://nextcloud.ltd/remote.php/dav/files/admin/Talk/Media/?accept=zip
 */
export function generateUserFolderUrl(filepath: string, userid: string | undefined = getCurrentUser()?.uid) {
	const url = new URL(generateUserFileUrl(filepath, userid))
	url.searchParams.append('accept', 'zip')
	if (!url.pathname.endsWith('/')) {
		url.pathname = `${url.pathname}/`
	}
	return url.href
}

/**
 * Generate a public WebDAV download link for a public share
 *
 * @param shareLink - The public share link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH?path=/Media
 * @param path - The file path, only used when starting with a slash (relative to a folder share), e.g., /Media/photo.jpg
 * @return The download link, e.g., https://nextcloud.ltd/public.php/dav/files/CBeNTiJz5JeT2CH/Media/photo.jpg
 */
export function generatePublicShareDownloadUrl(shareLink: string, path: string = '') {
	const token = new URL(shareLink).pathname.split('/').filter(Boolean).at(-1)
	return getBaseUrl() + '/public.php/dav/files/' + token + (path.startsWith('/') ? encodePath(path) : '')
}

/**
 * Generate a preview link for a file of the current user
 *
 * @param fileId - The file id, e.g., 123
 * @param size - The preview size, both -1 for the largest preview
 * @param size.x - The preview width, -1 to keep the aspect ratio
 * @param size.y - The preview height, -1 to keep the aspect ratio
 * @return The preview link, e.g., https://nextcloud.ltd/index.php/core/preview?fileId=123&x=-1&y=384&a=1
 */
export function generatePreviewUrl(fileId: string | number, { x, y }: { x: number, y: number }) {
	return generateUrl('/core/preview?fileId={fileId}&x={x}&y={y}&a=1', {
		fileId,
		x,
		y,
	})
}

/**
 * Generate a preview link for a file in a public share
 *
 * @param shareLink - The public share link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH?path=/Media
 * @param path - The file path, only used when starting with a slash (relative to a folder share), e.g., /Media/photo.jpg
 * @param size - The preview size, both -1 for the largest preview
 * @param size.x - The preview width, -1 to keep the aspect ratio
 * @param size.y - The preview height, -1 to keep the aspect ratio
 * @return The preview link, e.g., https://nextcloud.ltd/index.php/apps/files_sharing/publicpreview/CBeNTiJz5JeT2CH?file=%2FMedia%2Fphoto.jpg&x=-1&y=384&a=1
 */
export function generatePublicSharePreviewUrl(shareLink: string, path: string, { x, y }: { x: number, y: number }) {
	const token = new URL(shareLink).pathname.split('/').filter(Boolean).at(-1)
	return generateUrl('/apps/files_sharing/publicpreview/{token}?file={file}&x={x}&y={y}&a=1', {
		token,
		file: path.startsWith('/') ? path : '',
		x,
		y,
	})
}
