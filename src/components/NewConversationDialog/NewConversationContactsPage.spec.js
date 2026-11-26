/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { CanceledError } from 'axios'
import { cloneDeep } from 'es-toolkit'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NewConversationContactsPage from './NewConversationContactsPage.vue'
import router from '../../__mocks__/router.js'
import { ATTENDEE } from '../../constants.ts'
import { autocompleteQuery } from '../../services/coreService.ts'
import storeConfig from '../../store/storeConfig.js'
import { generateOCSResponse } from '../../test-helpers.js'

vi.mock('../../services/coreService', () => ({
	autocompleteQuery: vi.fn(),
}))

describe('NewConversationContactsPage.vue', () => {
	const ALICE = { id: 'alice', label: 'Alice', source: ATTENDEE.ACTOR_TYPE.USERS }
	const BOB = { id: 'bob', label: 'Bob', source: ATTENDEE.ACTOR_TYPE.USERS }
	// The hint uses a no-break space before the ellipsis
	const SEARCHING = 'Searching\u00A0…'

	let store
	let requests

	/**
	 * Shared function to mount component
	 */
	function mountComponent() {
		return mount(NewConversationContactsPage, {
			props: {
				selectedParticipants: [],
				onlyUsers: true,
			},
			global: {
				plugins: [router, store],
				stubs: {
					NcAvatar: true,
				},
			},
		})
	}

	/**
	 * Answer a search request that is still in flight
	 *
	 * @param {number} index position of the request
	 * @param {object[]} payload users found
	 */
	async function respond(index, payload) {
		requests[index].resolve(generateOCSResponse({ payload }))
		await flushPromises()
	}

	/**
	 * Type into the search field and let the debounced search start
	 *
	 * @param {object} wrapper mounted component
	 * @param {string} text search text
	 */
	async function search(wrapper, text) {
		await wrapper.find('input[type="text"]').setValue(text)
		vi.advanceTimersByTime(300)
		await flushPromises()
	}

	beforeEach(() => {
		vi.useFakeTimers()
		setActivePinia(createPinia())

		requests = []
		autocompleteQuery.mockImplementation((payload, { signal }) => new Promise((resolve, reject) => {
			requests.push({ searchText: payload.searchText, resolve })
			// Emulate axios rejecting a request once it is aborted
			signal.addEventListener('abort', () => reject(new CanceledError()))
		}))

		store = createStore(cloneDeep(storeConfig))
	})

	afterEach(() => {
		vi.useRealTimers()
		vi.clearAllMocks()
	})

	test('shows that it is searching until the first results arrive', async () => {
		const wrapper = mountComponent()
		await flushPromises()
		expect(wrapper.text()).toContain(SEARCHING)

		await respond(0, [ALICE, BOB])

		expect(wrapper.text()).not.toContain(SEARCHING)
		expect(wrapper.text()).toContain('Alice')
	})

	test('keeps the previous results until the new search completes', async () => {
		const wrapper = mountComponent()
		await flushPromises()
		await respond(0, [ALICE, BOB])

		await search(wrapper, 'b')
		expect(requests.map((request) => request.searchText)).toEqual(['', 'b'])
		expect(wrapper.text()).toContain('Alice')
		expect(wrapper.text()).not.toContain(SEARCHING)

		await respond(1, [BOB])
		expect(wrapper.text()).not.toContain('Alice')
		expect(wrapper.text()).toContain('Bob')
	})

	test('keeps showing that nothing was found until the new search completes', async () => {
		const wrapper = mountComponent()
		await flushPromises()
		await respond(0, [ALICE, BOB])
		await search(wrapper, 'x')
		await respond(1, [])

		await search(wrapper, 'xy')

		expect(wrapper.text()).toContain('No search results')
		expect(wrapper.text()).not.toContain(SEARCHING)
	})

	test('restores the full list when the search is cleared, dropping pending searches', async () => {
		const wrapper = mountComponent()
		await flushPromises()
		await respond(0, [ALICE, BOB])
		await search(wrapper, 'b')
		await wrapper.find('input[type="text"]').setValue('bo')

		await wrapper.find('button[aria-label="Cancel search"]').trigger('click')
		vi.advanceTimersByTime(300)
		await respond(1, [BOB])

		expect(requests).toHaveLength(2)
		expect(wrapper.text()).toContain('Alice')
		expect(wrapper.text()).toContain('Bob')
	})
})
