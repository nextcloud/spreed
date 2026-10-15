/*!
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { defaultRemoteURL } from '@nextcloud/files/dav'
import { generateUrl } from '@nextcloud/router'

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
 * Generate a download link for a public share
 *
 * @param shareLink - The public share link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH/
 * @return The download link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH/download
 */
export function generatePublicShareDownloadUrl(shareLink: string) {
	return shareLink + '/download'
}

/**
 * Generate a preview link for a file in a public share
 *
 * @param shareLink - The public share link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH?path=/Media
 * @param name - The file name, e.g., 20240101-000102.png
 * @param size - The preview size, both -1 for the largest preview
 * @param size.x - The preview width, -1 to keep the aspect ratio
 * @param size.y - The preview height, -1 to keep the aspect ratio
 * @return The preview link, e.g., https://nextcloud.ltd/index.php/apps/files_sharing/publicpreview/CBeNTiJz5JeT2CH?file=%2FMedia%2F20240101-000102.png&x=-1&y=384&a=1
 */
export function generatePublicSharePreviewUrl(shareLink: string, name: string, { x, y }: { x: number, y: number }) {
	const url = new URL(shareLink)
	const token = url.pathname.split('/').filter(Boolean).pop()
	const folder = url.searchParams.get('path') ?? ''
	return generateUrl('/apps/files_sharing/publicpreview/{token}?file={file}&x={x}&y={y}&a=1', {
		token,
		file: `${folder.replace(/\/$/, '')}/${name}`,
		x,
		y,
	})
}
