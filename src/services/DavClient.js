/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getRequestToken } from '@nextcloud/auth'
import { generateRemoteUrl } from '@nextcloud/router'
import { createClient } from 'webdav'

// init webdav client on default dav endpoint
export const getDavClient = () => {
	return createClient(
		generateRemoteUrl('dav'),
		{ headers: { requesttoken: getRequestToken() || '' } },
	)
}
