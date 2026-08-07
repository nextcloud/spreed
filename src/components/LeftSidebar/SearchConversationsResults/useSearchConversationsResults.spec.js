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
import BrowserStorage from '../../../services/BrowserStorage.js'
import { searchListedConversations } from '../../../services/conversationsService.ts'
import { autocompleteQuery, searchMessages } from '../../../services/coreService.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { generateOCSResponse } from '../../../test-helpers.js'
import { useSearchConversationsResults } from './useSearchConversationsResults.ts'

vi.mock('../../../services/conversationsService', () => ({
	searchListedConversations: vi.fn(),
}))
vi.mock('../../../services/coreService', () => ({
	autocompleteQuery: vi.fn(),
	searchMessages: vi.fn(),
}))
vi.mock('../../../services/BrowserStorage.js', () => ({
	default: {
		getItem: vi.fn(),
		setItem: vi.fn(),
	},
}))

describe('useSearchConversationsResults', () => {
	let vuexStore
	let conversationsList
	// Pending requests of each service, in the order they were created
	let listedRequests
	let possibleRequests
	let messageRequests
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
	 * Build a unified search entry for a message
	 *
	 * @param {string} messageId id of the message
	 * @param {string} threadId id of the thread the message belongs to
	 * @return {object} the unified search result entry
	 */
	function messageResult(messageId, threadId = messageId) {
		return {
			title: `message ${messageId}`,
			subline: `message ${messageId}`,
			attributes: {
				conversation: 'token-1',
				messageId,
				threadId,
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorId: 'one',
				timestamp: '1000',
			},
		}
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
		BrowserStorage.getItem.mockReturnValue(null)
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
		messageRequests = []
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
		searchMessages.mockImplementation((payload, options) => {
			const request = createDeferred(options?.signal)
			messageRequests.push(request)
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

	describe('search filters', () => {
		test('restores valid saved filters', () => {
			BrowserStorage.getItem.mockReturnValueOnce('messages,unknown')

			const { composable } = mountComposable()

			expect(BrowserStorage.getItem).toHaveBeenCalledWith('globalSearchFilters')
			expect(composable.searchFilters.value).toEqual(['messages'])
		})

		test('persists changed filters', async () => {
			const { composable } = mountComposable()

			await composable.toggleFilter('', 'messages')

			expect(BrowserStorage.setItem).toHaveBeenCalledWith('globalSearchFilters', 'conversations,messages')
		})

		test('requests only the enabled filters', async () => {
			// Arrange
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			const { composable } = mountComposable()

			// Act: search in the conversations filter only
			await composable.search('query')

			// Assert: conversations and people belong to the same filter
			expect(searchListedConversations).toHaveBeenCalledTimes(1)
			expect(autocompleteQuery).toHaveBeenCalledTimes(1)
			expect(searchMessages).not.toHaveBeenCalled()
		})

		test('applies message results with a router link to the message', async () => {
			// Arrange
			searchMessages.mockResolvedValueOnce(generateOCSResponse({
				payload: { entries: [messageResult('101'), messageResult('102', '100')] },
			}))
			const { composable } = mountComposable()
			composable.searchFilters.value = ['messages']

			// Act: search in the messages filter only
			await composable.search('query')

			// Assert
			expect(searchMessages).toHaveBeenCalledWith(
				{ term: 'query', limit: expect.any(Number) },
				expect.objectContaining({ signal: expect.any(AbortSignal) }),
			)
			expect(composable.searchResultsMessages.value).toEqual([
				{
					...messageResult('101'),
					// A message of the main thread has no thread to route to
					to: { name: 'conversation', hash: '#message_101', params: { token: 'token-1' }, query: { threadId: undefined } },
				},
				{
					...messageResult('102', '100'),
					to: { name: 'conversation', hash: '#message_102', params: { token: 'token-1' }, query: { threadId: '100' } },
				},
			])
		})

		test('drops the results of a filter that is no longer searched', async () => {
			// Arrange: search in all filters
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [{ id: 1, token: 'listed' }] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			searchMessages.mockResolvedValueOnce(generateOCSResponse({ payload: { entries: [messageResult('101')] } }))
			const { composable } = mountComposable()
			composable.searchFilters.value = ['conversations', 'messages']
			await composable.search('query')
			expect(composable.searchResultsMessages.value).toHaveLength(1)

			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [{ id: 1, token: 'listed' }] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))

			// Act: search again without the messages filter
			composable.searchFilters.value = ['conversations']
			await composable.search('query')

			// Assert
			expect(searchMessages).toHaveBeenCalledTimes(1)
			expect(composable.searchResultsMessages.value).toEqual([])
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 1, token: 'listed' }])
		})

		test('clears a single filter without invalidating the others', async () => {
			// Arrange
			const { composable } = mountComposable()

			// Act: drop the messages filter while the other requests are still pending
			composable.searchFilters.value = ['conversations', 'messages']
			const search = composable.search('query')
			composable.toggleFilter('query', 'messages')
			listedRequests[0].resolve(generateOCSResponse({ payload: [{ id: 1, token: 'listed' }] }))
			possibleRequests[0].resolve(generateOCSResponse({ payload: [autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)] }))

			// Assert: the remaining filters are unaffected, only the dropped one reports as outdated
			await expect(search).resolves.toBeFalsy()
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 1, token: 'listed' }])
			expect(composable.searchResultsPossibleConversations.value).toEqual([autocompleteResult('one', ATTENDEE.ACTOR_TYPE.USERS)])
			expect(composable.searchResultsMessages.value).toEqual([])
			// The outdated search does not stop loading, dropping the filter has to
			expect(composable.searchResultsLoading.value).toBeFalsy()
		})

		test('searches a single filter without invalidating the others', async () => {
			// Arrange
			const { composable } = mountComposable()

			// Act: enable the messages filter while the other requests are still pending
			const search = composable.search('query')
			const messagesSearch = composable.toggleFilter('query', 'messages')
			messageRequests[0].resolve(generateOCSResponse({ payload: { entries: [messageResult('101')] } }))

			// Assert
			await expect(messagesSearch).resolves.toBeTruthy()
			expect(composable.searchResultsMessages.value).toHaveLength(1)
			// The other filters are still pending, so loading is kept
			expect(composable.searchResultsLoading.value).toBeTruthy()

			// Act: the other filters receive their results
			listedRequests[0].resolve(generateOCSResponse({ payload: [{ id: 1, token: 'listed' }] }))
			possibleRequests[0].resolve(generateOCSResponse({ payload: [] }))

			// Assert
			await expect(search).resolves.toBeTruthy()
			expect(composable.searchResultsMessages.value).toHaveLength(1)
			expect(composable.searchResultsListedConversations.value).toEqual([{ id: 1, token: 'listed' }])
			expect(composable.searchResultsLoading.value).toBeFalsy()
		})
	})

	describe('re-enabled filters', () => {
		/**
		 * Search in both filters and disable the messages one afterwards
		 *
		 * @param {string} query search text to look for
		 * @return {object} the composable
		 */
		async function searchAndDisableMessages(query) {
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			searchMessages.mockResolvedValueOnce(generateOCSResponse({ payload: { entries: [messageResult('101')] } }))
			const { composable } = mountComposable()
			composable.searchFilters.value = ['conversations', 'messages']
			await composable.search(query)
			await composable.toggleFilter(query, 'messages')

			return composable
		}

		test('keeps the results of a disabled filter', async () => {
			// Act: disable the messages filter after a successful search
			const composable = await searchAndDisableMessages('query')

			// Assert: the results are kept to be shown again, the consumer hides them meanwhile
			expect(composable.searchFilters.value).toEqual(['conversations'])
			expect(composable.searchResultsMessages.value).toHaveLength(1)
		})

		test('does not request again if the query did not change', async () => {
			// Arrange
			const composable = await searchAndDisableMessages('query')

			// Act: enable the messages filter again, without searching in between
			await expect(composable.toggleFilter('query', 'messages')).resolves.toBeTruthy()

			// Assert: the results of the previous search are shown again
			expect(searchMessages).toHaveBeenCalledTimes(1)
			expect(composable.searchResultsMessages.value).toHaveLength(1)
			expect(composable.searchResultsLoading.value).toBeFalsy()
		})

		test('requests again if the query changed meanwhile', async () => {
			// Arrange
			const composable = await searchAndDisableMessages('query')
			searchListedConversations.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			autocompleteQuery.mockResolvedValueOnce(generateOCSResponse({ payload: [] }))
			searchMessages.mockResolvedValueOnce(generateOCSResponse({ payload: { entries: [messageResult('102')] } }))

			// Act: search for another query, then enable the messages filter again
			await composable.search('another query')
			await expect(composable.toggleFilter('another query', 'messages')).resolves.toBeTruthy()

			// Assert
			expect(searchMessages).toHaveBeenCalledTimes(2)
			expect(searchMessages).toHaveBeenLastCalledWith({ term: 'another query', limit: expect.any(Number) }, expect.anything())
			expect(composable.searchResultsMessages.value).toEqual([expect.objectContaining(messageResult('102'))])
		})

		test('requests again if the disabled filter had a pending request', async () => {
			// Arrange
			const { composable } = mountComposable()
			composable.searchFilters.value = ['conversations', 'messages']

			// Act: disable the messages filter while its request is still pending
			composable.search('query')
			await composable.toggleFilter('query', 'messages')
			// and enable it again with an unchanged query
			const messagesSearch = composable.toggleFilter('query', 'messages')
			messageRequests[1].resolve(generateOCSResponse({ payload: { entries: [messageResult('101')] } }))

			// Assert: results of the aborted request are incomplete and not reused
			await expect(messagesSearch).resolves.toBeTruthy()
			expect(searchMessages).toHaveBeenCalledTimes(2)
			expect(composable.searchResultsMessages.value).toHaveLength(1)
		})

		test('requests again after the search was aborted', async () => {
			// Arrange
			const composable = await searchAndDisableMessages('query')
			searchMessages.mockResolvedValueOnce(generateOCSResponse({ payload: { entries: [messageResult('102')] } }))

			// Act: abort the search (the search box was cleared), then start over with the same query
			composable.abortSearchRequests()
			await expect(composable.toggleFilter('query', 'messages')).resolves.toBeTruthy()

			// Assert
			expect(searchMessages).toHaveBeenCalledTimes(2)
			expect(composable.searchResultsMessages.value).toEqual([expect.objectContaining(messageResult('102'))])
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

		test('requests again when a re-enabled filter failed before', async () => {
			// Arrange: the messages filter fails, then it is disabled
			searchMessages.mockRejectedValueOnce(new Error('Unified search is not available'))
			const { composable } = mountComposable()
			composable.searchFilters.value = ['messages']
			await composable.search('query')
			await composable.toggleFilter('query', 'messages')

			searchMessages.mockResolvedValueOnce(generateOCSResponse({ payload: { entries: [messageResult('101')] } }))

			// Act: enable the filter again with an unchanged query
			await expect(composable.toggleFilter('query', 'messages')).resolves.toBeTruthy()

			// Assert: a failed search leaves nothing to show again
			expect(searchMessages).toHaveBeenCalledTimes(2)
			expect(composable.searchResultsMessages.value).toHaveLength(1)
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
