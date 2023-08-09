/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'
import Router from 'vue-router'

import { getRootUrl, generateUrl } from '@nextcloud/router'

import CallView from '../components/CallView/CallView.vue'
import MainView from '../views/MainView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import SessionConflictView from '../views/SessionConflictView.vue'
import UpgradeRequiredView from '../views/UpgradeRequiredView.vue'
import WelcomeView from '../views/WelcomeView.vue'

Vue.use(Router)

/**
 * Generate base url for Talk Web app based on server's root
 *
 * @return {string} Vue Router base url
 */
function generateTalkWebBasePath() {
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	const webRootWithIndexPHP = getRootUrl() + '/index.php'
	const doesURLContainIndexPHP = window.location.pathname.startsWith(webRootWithIndexPHP)
	return generateUrl('/', {}, {
		noRewrite: doesURLContainIndexPHP,
	})
}

export default new Router({
	// On desktop (Electron) app is open via file:// protocol - History API is not available and no base path
	mode: !IS_DESKTOP ? 'history' : 'hash',
	base: !IS_DESKTOP ? generateTalkWebBasePath() : '',

	linkActiveClass: 'active',

	routes: [
		{
			path: '/apps/spreed',
			// On desktop add index path as root page
			alias: IS_DESKTOP ? '/' : undefined,
			name: 'root',
			component: WelcomeView,
			props: true,
		},
		{
			path: '/apps/spreed/not-found',
			name: 'notfound',
			component: NotFoundView,
			props: true,
		},
		{
			path: '/apps/spreed/upgrade-required',
			name: 'upgraderequired',
			component: UpgradeRequiredView,
			props: true,
		},
		{
			path: '/apps/spreed/duplicate-session',
			name: 'duplicatesession',
			component: SessionConflictView,
			props: true,
		},
		{
			path: '/call/:token',
			name: 'conversation',
			component: MainView,
			props: true,
		},
		{
			path: '/call/:token/recording',
			name: 'recording',
			component: CallView,
			props: true,
		},
	],
})
