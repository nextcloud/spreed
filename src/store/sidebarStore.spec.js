/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import sidebarStore from './sidebarStore.js'

describe('sidebarStore', () => {
	let localVue = null
	let store = null

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(cloneDeep(sidebarStore))
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	test('defaults are off', () => {
		expect(store.getters.getSidebarStatus).toBe(true)
	})

	test('toggle sidebar', () => {
		store.dispatch('hideSidebar')

		expect(store.getters.getSidebarStatus).toBe(false)

		store.dispatch('showSidebar')

		expect(store.getters.getSidebarStatus).toBe(true)
	})
})
