/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { RouteRecordRaw } from 'vue-router'

import { generateUrl, getRootUrl } from '@nextcloud/router'
import {
	createRouter,
	createWebHashHistory,
	createWebHistory,
} from 'vue-router'
import CallView from '../components/CallView/CallView.vue'
import ForbiddenView from '../views/ForbiddenView.vue'
import MainView from '../views/MainView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import SessionConflictView from '../views/SessionConflictView.vue'
import WelcomeView from '../views/WelcomeView.vue'

/**
 * Generate base url for Talk Web app based on server's root
 *
 * @return {string} Vue Router base url
 */
function generateTalkWebBasePath(): string {
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	const webRootWithIndexPHP = getRootUrl() + '/index.php'
	const doesURLContainIndexPHP = window.location.pathname.startsWith(webRootWithIndexPHP)
	return generateUrl('/', {}, {
		noRewrite: doesURLContainIndexPHP,
	})
}

/**
 * Returns a router object for the main app (Talk, Talk Recording, Talk Desktop)
 */
export function createTalkRouter() {
	const routes: RouteRecordRaw[] = [
		{
			path: '/apps/spreed',
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
			path: '/apps/spreed/forbidden',
			name: 'forbidden',
			component: ForbiddenView,
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
	]

	return createRouter({
		// On desktop (Electron) app is open via file:// protocol - History API is not available and no base path
		history: !IS_DESKTOP ? createWebHistory(generateTalkWebBasePath()) : createWebHashHistory(''),
		linkActiveClass: 'active',
		routes,
	})
}
