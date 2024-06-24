/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import VueRouter from 'vue-router'

const Stub = {
	name: 'Stub',
	template: '<div></div>',
}

export default new VueRouter({
	linkActiveClass: 'active',
	routes: [
		{
			path: '/apps/spreed',
			name: 'root',
			component: Stub,
			props: true,
		},
		{
			path: '/apps/spreed/not-found',
			name: 'notfound',
			component: Stub,
			props: true,
		},
		{
			path: '/apps/spreed/forbidden',
			name: 'forbidden',
			component: Stub,
			props: true,
		},
		{
			path: '/apps/spreed/duplicate-session',
			name: 'duplicatesession',
			component: Stub,
			props: true,
		},
		{
			path: '/call/:token',
			name: 'conversation',
			component: Stub,
			props: true,
		},
		{
			path: '/call/:token/recording',
			name: 'recording',
			component: Stub,
			props: true,
		},
	],
})
