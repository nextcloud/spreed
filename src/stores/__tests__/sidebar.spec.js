/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { setActivePinia, createPinia } from 'pinia'

import { emit } from '@nextcloud/event-bus'

import BrowserStorage from '../../services/BrowserStorage.js'
import { useSidebarStore } from '../sidebar.js'

jest.mock('@nextcloud/event-bus', () => ({
	emit: jest.fn(),
}))

jest.mock('../../services/BrowserStorage.js', () => ({
	getItem: jest.fn(),
	setItem: jest.fn(),
}))

describe('sidebarStore', () => {
	let sidebarStore

	beforeEach(() => {
		setActivePinia(createPinia())
		sidebarStore = useSidebarStore()
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	it('shows the sidebar on selected tab with caching the value', () => {
		sidebarStore.showSidebar({ activeTab: 'test-tab', cache: true })

		expect(sidebarStore.show).toBe(true)
		expect(emit).toHaveBeenCalledWith('spreed:select-active-sidebar-tab', 'test-tab')
		expect(BrowserStorage.setItem).toHaveBeenCalledWith('sidebarOpen', 'true')
	})

	it('shows the sidebar with caching the value', () => {
		sidebarStore.showSidebar()

		expect(sidebarStore.show).toBe(true)
		expect(emit).not.toHaveBeenCalled()
		expect(BrowserStorage.setItem).toHaveBeenCalledWith('sidebarOpen', 'true')
	})

	it('shows the sidebar without caching the value', () => {
		sidebarStore.showSidebar({ cache: false })

		expect(sidebarStore.show).toBe(true)
		expect(emit).not.toHaveBeenCalled()
		expect(BrowserStorage.setItem).not.toHaveBeenCalled()
	})

	it('hides the sidebar with caching the value', () => {
		sidebarStore.hideSidebar()

		expect(sidebarStore.show).toBe(false)
		expect(BrowserStorage.setItem).toHaveBeenCalledWith('sidebarOpen', 'false')
	})

	it('hides the sidebar without caching the value', () => {
		sidebarStore.hideSidebar({ cache: false })

		expect(sidebarStore.show).toBe(false)
		expect(BrowserStorage.setItem).not.toHaveBeenCalled()
	})
})
