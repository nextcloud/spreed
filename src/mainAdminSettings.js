/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettings from './views/AdminSettings.vue'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

import '@nextcloud/dialogs/style.css'
import './assets/admin-settings.css'

export default createApp(AdminSettings)
	.use(NextcloudGlobalsVuePlugin)
	.mount('#admin_settings')
