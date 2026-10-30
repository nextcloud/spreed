/*!
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import AdminSettingsApp from './AdminSettingsApp.vue'

import '@nextcloud/dialogs/style.css'
import './assets/admin-settings.css'

export default createApp(AdminSettingsApp)
	.mount('#admin_settings')
