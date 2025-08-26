/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { emit } from '@nextcloud/event-bus'
import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import BrowserStorage from '../../services/BrowserStorage.js'
import { useSidebarStore } from '../sidebar.ts'

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
}))

vi.mock('../../services/BrowserStorage.js', () => ({
	default: {
		getItem: vi.fn(),
		setItem: vi.fn(),
	},
}))

describe('sidebarStore', () => {
	let sidebarStore

	beforeEach(() => {
		setActivePinia(createPinia())
		sidebarStore = useSidebarStore()
	})

	afterEach(() => {
		vi.clearAllMocks()
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
