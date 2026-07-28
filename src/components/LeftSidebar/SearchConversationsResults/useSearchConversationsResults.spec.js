/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { mount } from '@vue/test-utils'
import { CanceledError } from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, test, vi } from 'vitest'
import { h } from 'vue'
import { createStore } from 'vuex'
import { ATTENDEE, CONVERSATION } from '../../../constants.ts'
import { searchListedConversations } from '../../../services/conversationsService.ts'
import { autocompleteQuery } from '../../../services/coreService.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { generateOCSResponse } from '../../../test-helpers.js'
import { useSearchConversationsResults } from './useSearchConversationsResults.ts'

vi.mock('../../../services/conversationsService', () => ({
	searchListedConversations: vi.fn(),
}))
vi.mock('../../../services/coreService', () => ({
	autocompleteQuery: vi.fn(),
}))

describe('useSearchConversationsResults', () => {
	let vuexStore
	let conversationsList
	// Pending requests of each service, in the order they were created
	let listedRequests
	let possibleRequests
	// Whether aborting a request rejects it. Set to false to emulate requests
	// that already left the pending state, so that aborting them has no effect
	let abortRejectsRequests

	const CURRENT_USER = 'current-user'

	/**
	 * Create a promise that the test resolves or rejects on demand,
	 * emulating a request that is still in flight
	 *
	 * @param {AbortSignal} [signal] abort signal of the cancelable request
	 * @return {object} the deferred request
	 */
	function createDeferred(signal) {
		let resolve
		let reject
		const promise = new Promise((res, rej) => {
			resolve = res
			reject = rej
		})
		// Emulate axios rejecting a request once it is aborted
		if (abortRejectsRequests) {
			signal?.addEventListener('abort', () => reject(new CanceledError()))
		}

		return { promise, resolve, reject }
	}

	/**
	 * Build an autocomplete result with the given id and source
	 *
	 * @param {string} id id of the result
	 * @param {string} source actor type of the result
	 * @return {object} the autocomplete result
	 */
	function autocompleteResult(id, source) {
		return {
			id,
			source,
			label: id,
			icon: '',
			status: '',
			subline: '',
			shareWithDisplayNameUnique: '',
		}
	}

	/**
	 * Mount a host component to provide a lifecycle scope for the composable
	 *
	 * @return {object} the composable and the host component wrapper
	 */
	function mountComposable() {
		let composable
		const wrapper = mount({
			setup() {
				composable = useSearchConversationsResults()
				return () => h('div')
			},
		}, {
			global: {
				plugins: [vuexStore],
			},
		})

		return { composable, wrapper }
	}

	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
		useActorStore().setCurrentUser({ uid: CURRENT_USER, displayName: CURRENT_USER })

		conversationsList = []
		vuexStore = createStore({
			getters: {
				conversationsList: () => conversationsList,
			},
		})

		// By default requests stay pending, so that tests settle them in the required order.
		// Tests that do not depend on the order arrange their responses with 'mock*ValueOnce'
		listedRequests = []
		possibleRequests = []
		abortRejectsRequests = true
		searchListedConversations.mockImplementation((query, options) => {
			const request = createDeferred(options?.signal)
			listedRequests.push(request)
			return request.promise
		})
		autocompleteQuery.mockImplementation((payload, options) => {
			const request = createDeferred(options?.signal)
			possibleRequests.push(request)
			return request.promise
		})
	})

	describe('successful search', () => {
		test('applies the results and stops loading', async () => {
			// Arrange
			const listedResponse = generateOCSResponse({ payload: [{ id: 1, token: 'listed' }] })
			const possibleResponse = generateOCSResponse({ payload: [autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)] })
			searchListedConversations.mockResolvedValueOnce(listedResponse)
			autocompleteQuery.mockResolvedValueOnce(possibleResponse)
			const { composable } = mountComposable()

			// Act: search for conversations
			const search = composable.search('query')

			// Assert
			expect(composable.searchResultsLoading.value).toBeTruthy()
			await expect(search).resolves.toBeTruthy()
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 1, token: 'listed' }])
			expect(composable.searchResultsPossibleConversations.value).toEqual([autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)])
			expect(composable.searchResultsLoading.value).toBeFalsy()
			expect(showError).not.toHaveBeenCalled()
		})

		test('requests groups and teams if the user can create conversations', async () => {
			// Arrange
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			const { composable } = mountComposable()

			// Act: search for conversations
			await composable.search('query')

			// Assert
			expect(autocompleteQuery).toHaveBeenCalledWith(
				{ searchText: 'query', token: 'new', onlyUsers: false },
				expect.objectContaining({ signal: expect.any(AbortSignal) }),
			)
			expect(searchListedConversations).toHaveBeenCalledWith('query', expect.anything())
		})

		test('filters out the current user and known one-to-one conversations', async () => {
			// Arrange
			conversationsList = [
				{ id: 1, name: 'one', type: CONVERSATION.TYPE.ONE_TO_ONE },
				{ id: 2, name: 'two', type: CONVERSATION.TYPE.GROUP },
			]
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({
				payload: [
					// Filtered out: the current user
					autocompleteResult(CURRENT_USER, ATTENDEE.ACTOR_TYPE.USERS),
					// Filtered out: a one-to-one conversation exists already
					autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS),
					// Kept: only a group conversation exists with this user
					autocompleteResult('two', ATTENDEE.ACTOR_TYPE.USERS),
					// Kept: not a user, so the one-to-one check does not apply
					autocompleteResult('one', ATTENDEE.ACTOR_TYPE.GROUPS),
				],
			}))
			const { composable } = mountComposable()

			// Act: search for conversations
			await composable.search('query')

			// Assert
			expect(composable.searchResultsPossibleConversations.value).toEqual([
				autocompleteResult('two', ATTENDEE.ACTOR_TYPE.USERS),
				autocompleteResult('one', ATTENDEE.ACTOR_TYPE.GROUPS),
			])
		})
	})

	describe('outdated search', () => {
		test('does not apply results of a superseded search', async () => {
			// Arrange
			const { composable } = mountComposable()

			// Act: let the results of a search arrive, but supersede it before they are processed
			const outdatedSearch = composable.search('outdated')
			listedRequests[0].resolve(generateOCSResponse({ payload: [{ id: 1, token: 'outdated' }] }))
			possibleRequests[0].resolve(generateOCSResponse({ payload: [autocompleteResult('outdated', ATTENDEE.ACTOR_TYPE.USERS)] }))
			const currentSearch = composable.search('current')

			// Assert: nothing is applied while the newest search is still pending
			await expect(outdatedSearch).resolves.toBeFalsy()
			expect(composable.searchResultsListedConversations.value).toEqual([])
			expect(composable.searchResultsPossibleConversations.value).toEqual([])
			expect(composable.searchResultsLoading.value).toBeTruthy()

			// Act: the newest search receives its results
			listedRequests[1].resolve(generateOCSResponse({ payload: [{ id: 2, token: 'current' }] }))
			possibleRequests[1].resolve(generateOCSResponse({ payload: [autocompleteResult('current', ATTENDEE.ACTOR_TYPE.USERS)] }))

			// Assert
			await expect(currentSearch).resolves.toBeTruthy()
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 2, token: 'current' }])
			expect(composable.searchResultsPossibleConversations.value).toEqual([autocompleteResult('current', ATTENDEE.ACTOR_TYPE.USERS)])
			expect(composable.searchResultsLoading.value).toBeFalsy()
		})

		test('cancels pending requests of a superseded search', async () => {
			// Arrange
			const { composable } = mountComposable()

			// Act: supersede a search while its requests are still pending
			const outdatedSearch = composable.search('outdated')
			composable.search('current')

			// Assert
			await expect(outdatedSearch).resolves.toBeFalsy()
			expect(showError).not.toHaveBeenCalled()
		})

		test('reports an aborted search as not applied', async () => {
			// Arrange
			const { composable } = mountComposable()

			// Act: abort a search while its requests are still pending
			const search = composable.search('query')
			composable.abortSearchRequests()

			// Assert
			await expect(search).resolves.toBeFalsy()
			expect(composable.searchResultsListedConversations.value).toEqual([])
			expect(composable.searchResultsPossibleConversations.value).toEqual([])
			expect(showError).not.toHaveBeenCalled()
		})

		test('aborts a pending search on unmount', async () => {
			// Arrange
			const { composable, wrapper } = mountComposable()

			// Act: unmount the consumer while the requests are still pending
			const search = composable.search('query')
			wrapper.unmount()

			// Assert
			await expect(search).resolves.toBeFalsy()
			expect(showError).not.toHaveBeenCalled()
		})
	})

	describe('failing search', () => {
		let consoleError

		beforeEach(() => {
			// Test setup makes console.error throw, but failures are expected here
			consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
		})

		test('shows a single error and clears the results if both requests fail', async () => {
			// Arrange: populate the results with an earlier successful search
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [{ id: 1, token: 'listed' }] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)] }))
			const { composable } = mountComposable()
			await composable.search('query')

			searchListedConversations.mockRejectedValueOnce(new Error('Listed conversations are not available'))
			autocompleteQuery.mockRejectedValueOnce(new Error('Autocomplete is not available'))

			// Act: search again, with both requests failing
			const search = composable.search('failing')

			// Assert
			await expect(search).resolves.toBeTruthy()
			expect(consoleError).toHaveBeenCalledTimes(2)
			expect(showError).toHaveBeenCalledTimes(1)
			expect(composable.searchResultsListedConversations.value).toEqual([])
			expect(composable.searchResultsPossibleConversations.value).toEqual([])
			expect(composable.searchResultsLoading.value).toBeFalsy()
		})

		test('keeps the successful results if only one request fails', async () => {
			// Arrange
			searchListedConversations.mockRejectedValueOnce(new Error('Listed conversations are not available'))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)] }))
			const { composable } = mountComposable()

			// Act: search with only one of the requests failing
			const search = composable.search('query')

			// Assert
			await expect(search).resolves.toBeTruthy()
			expect(showError).toHaveBeenCalledTimes(1)
			expect(composable.searchResultsListedConversations.value).toEqual([])
			expect(composable.searchResultsPossibleConversations.value).toEqual([autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)])
			expect(composable.searchResultsLoading.value).toBeFalsy()
		})

		test('does not report a failure of a superseded search', async () => {
			// Arrange
			const { composable } = mountComposable()

			// Act: let the requests of a search fail, but supersede it before they are handled
			const outdatedSearch = composable.search('outdated')
			listedRequests[0].reject(new Error('Listed conversations are not available'))
			possibleRequests[0].reject(new Error('Autocomplete is not available'))
			const currentSearch = composable.search('current')

			// Assert: the failure of the outdated search is not reported to the user
			await expect(outdatedSearch).resolves.toBeFalsy()
			expect(showError).not.toHaveBeenCalled()

			// Act: the newest search receives its results
			listedRequests[1].resolve(generateOCSResponse({ payload: [{ id: 2, token: 'current' }] }))
			possibleRequests[1].resolve(generateOCSResponse({ payload: [autocompleteResult('current', ATTENDEE.ACTOR_TYPE.USERS)] }))

			// Assert
			await expect(currentSearch).resolves.toBeTruthy()
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 2, token: 'current' }])
			expect(composable.searchResultsPossibleConversations.value).toEqual([autocompleteResult('current', ATTENDEE.ACTOR_TYPE.USERS)])
		})

		test('does not clear newer results when a superseded search fails late', async () => {
			// Arrange: the outdated requests already left the pending state,
			// so cancelling them has no effect
			abortRejectsRequests = false
			const { composable } = mountComposable()

			// Act: supersede a search and let the newest one succeed first
			const outdatedSearch = composable.search('outdated')
			const currentSearch = composable.search('current')
			listedRequests[1].resolve(generateOCSResponse({ payload: [{ id: 2, token: 'current' }] }))
			possibleRequests[1].resolve(generateOCSResponse({ payload: [autocompleteResult('current', ATTENDEE.ACTOR_TYPE.USERS)] }))

			// Assert
			await expect(currentSearch).resolves.toBeTruthy()

			// Act: only then the outdated search fails
			listedRequests[0].reject(new Error('Listed conversations are not available'))
			possibleRequests[0].reject(new Error('Autocomplete is not available'))

			// Assert: the results of the newest search are kept
			await expect(outdatedSearch).resolves.toBeFalsy()
			expect(showError).not.toHaveBeenCalled()
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 2, token: 'current' }])
			expect(composable.searchResultsPossibleConversations.value).toEqual([autocompleteResult('current', ATTENDEE.ACTOR_TYPE.USERS)])
		})
	})
})
