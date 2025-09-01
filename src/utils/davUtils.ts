/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { defaultRemoteURL } from '@nextcloud/files/dav'

/**
 * Generate a WebDAV url to a user files
 * @param filepath - The path to the user's file, e.g., Talk/20240101-000102.png
 * @param userid - The user id, e.g., 'admin'. Defaults to the current user id
 * @return {string} The WebDAV URL to the file, e.g., https://nextcloud.ltd/remote.php/dav/files/admin/Talk/20240101-000102.png
 */
export function generateUserFileUrl(filepath: string, userid: string | undefined = getCurrentUser()?.uid) {
	if (!userid) {
		throw new TypeError('Cannot generate /files/<user>/ URL without a user')
	}
	return defaultRemoteURL + '/files/' + encodeURI(userid) + '/' + encodeURI(filepath)
}

/**
 * Generate a download link for a public share
 * @param shareLink - The public share link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH/
 * @return {string} The download link, e.g., https://nextcloud.ltd/s/CBeNTiJz5JeT2CH/download
 */
export function generatePublicShareDownloadUrl(shareLink: string) {
	return shareLink + '/download'
}
