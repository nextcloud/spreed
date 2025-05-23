/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createClient } from 'webdav'

import { getRequestToken } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'

// init webdav client on default dav endpoint
export const getDavClient = () => {
	return createClient(
		generateRemoteUrl('dav'),
		{ headers: { requesttoken: getRequestToken() || '' } },
	)
}
