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
		expect(store.getters.isRenamingConversation).toBe(false)
	})

	test('toggle sidebar', () => {
		store.dispatch('hideSidebar')

		expect(store.getters.getSidebarStatus).toBe(false)

		store.dispatch('showSidebar')

		expect(store.getters.getSidebarStatus).toBe(true)
	})

	test('toggling renaming mode and remembers sidebar hidden state', () => {
		store.dispatch('hideSidebar')
		store.dispatch('isRenamingConversation', true)

		expect(store.getters.getSidebarStatus).toBe(false)
		expect(store.getters.isRenamingConversation).toBe(true)

		store.dispatch('showSidebar')

		expect(store.getters.getSidebarStatus).toBe(true)

		store.dispatch('isRenamingConversation', false)

		expect(store.getters.getSidebarStatus).toBe(false)
		expect(store.getters.isRenamingConversation).toBe(false)
	})

	test('toggling renaming mode and remembers sidebar shown state', () => {
		store.dispatch('showSidebar')
		store.dispatch('isRenamingConversation', true)

		expect(store.getters.getSidebarStatus).toBe(true)
		expect(store.getters.isRenamingConversation).toBe(true)

		store.dispatch('hideSidebar')

		expect(store.getters.getSidebarStatus).toBe(false)

		store.dispatch('isRenamingConversation', false)

		expect(store.getters.getSidebarStatus).toBe(true)
		expect(store.getters.isRenamingConversation).toBe(false)
	})
})
