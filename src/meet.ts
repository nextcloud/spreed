/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { createApp } from 'vue'
import MeetView from './views/MeetView.vue'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

__webpack_nonce__ = getCSPNonce()
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

createApp(MeetView)
	.use(NextcloudGlobalsVuePlugin)
	.mount('#talk-meet')
