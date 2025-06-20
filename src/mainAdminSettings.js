/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import AdminSettings from './views/AdminSettings.vue'

import '@nextcloud/dialogs/style.css'
import './assets/admin-settings.css'

Vue.prototype.OC = OC
Vue.prototype.OCA = OCA
Vue.prototype.OCP = OCP

export default new Vue({
	el: '#admin_settings',
	render: (h) => h(AdminSettings),
})
