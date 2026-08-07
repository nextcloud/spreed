/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	AutocompleteResult,
	Conversation,
} from '../../../types/index.ts'

import { isCancel } from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { onBeforeUnmount, ref } from 'vue'
import { useStore } from 'vuex'
import { ATTENDEE, CONVERSATION } from '../../../constants.ts'
import BrowserStorage from '../../../services/BrowserStorage.js'
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'
import { searchListedConversations } from '../../../services/conversationsService.ts'
import { autocompleteQuery, searchMessages } from '../../../services/coreService.ts'
import { useActorStore } from '../../../stores/actor.ts'
import CancelableRequest from '../../../utils/CancelableRequest.ts'
import { mapMessageResultEntry } from '../../../utils/searchEntries.ts'

const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')

// Scope filters to search in
export const SEARCH_FILTERS = ['conversations', 'messages'] as const
export type SearchFilter = typeof SEARCH_FILTERS[number]

/**
 * Restore user picked search filters from Browser storage (default - 'conversations')
 */
function restoreSearchFilters(): SearchFilter[] {
	const storedFilters = BrowserStorage.getItem('globalSearchFilters')
	if (!storedFilters) {
		return [SEARCH_FILTERS[0]]
	}

	const filters = storedFilters.split(',')
	return SEARCH_FILTERS.filter((filter) => filters.includes(filter))
}

/**
 * Composable to control logic for fetching search results:
 * - listed conversations
 * - possible conversations (users, groups, teams)
 * - messages from all conversations
 *
 * Has only single consumer (LeftSidebar.vue)
 */
export function useSearchConversationsResults() {
	const actorStore = useActorStore()
	const vuexStore = useStore()

	const searchFilters = ref<SearchFilter[]>(restoreSearchFilters())
	const searchResultsListedConversations = ref<Conversation[]>([])
	const searchResultsPossibleConversations = ref<AutocompleteResult[]>([])
	const searchResultsMessages = ref<ReturnType<typeof mapMessageResultEntry>[]>([])
	const searchResultsLoading = ref(true)

	let cancelSearchListedConversations: ReturnType<typeof CancelableRequest>['cancel'] | null = null
	let cancelSearchPossibleConversations: ReturnType<typeof CancelableRequest>['cancel'] | null = null
	let cancelSearchMessages: ReturnType<typeof CancelableRequest>['cancel'] | null = null

	// Generation counter - increases with every started, aborted or disabled search.
	// Individual per filter.
	// Results of a search are only applied with matching generation
	const searchGenerations: Record<SearchFilter, number> = {
		conversations: 0,
		messages: 0,
	}

	// Query the stored results of each filter belong to, null if there are none.
	// Results of a disabled filter are kept, so that re-enabling it with an
	// unchanged query shows them again instead of requesting them anew
	const fetchedQueries: Record<SearchFilter, string | null> = {
		conversations: null,
		messages: null,
	}

	onBeforeUnmount(() => {
		abortSearchRequests()
	})

	/**
	 * Get list of possible conversations (users, groups, teams) and write to ref
	 *
	 * @param query search text
	 * @param generation search generation
	 */
	async function fetchPossibleConversations(query: string, generation: number) {
		const { request, cancel } = CancelableRequest(autocompleteQuery)
		try {
			// Cancel previous search request if pending, and store reference to new one
			cancelSearchPossibleConversations?.()
			cancelSearchPossibleConversations = cancel

			const response = await request({
				searchText: query,
				token: 'new',
				onlyUsers: !canStartConversations,
			})

			if (generation !== searchGenerations.conversations) {
				// Results are outdated
				return
			}

			// Get all known user ids of 1:1 conversations
			const oneToOneMap = new Set(vuexStore.getters.conversationsList.reduce(
				(acc: string[], conversation: Conversation) => {
					if (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE) {
						acc.push(conversation.name)
					}
					return acc
				},
				// Include self
				[actorStore.userId],
			))

			// and filter them out
			searchResultsPossibleConversations.value = response.data.ocs.data.filter((match) => {
				return !(match.source === ATTENDEE.ACTOR_TYPE.USERS && oneToOneMap.has(match.id))
			})
		} catch (exception) {
			if (isCancel(exception)) {
				return
			}
			console.error('Error searching for possible conversations', exception)
			if (generation === searchGenerations.conversations) {
				// Drop results of the failed search
				searchResultsPossibleConversations.value = []
			}
			throw exception
		} finally {
			if (cancelSearchPossibleConversations === cancel) {
				cancelSearchPossibleConversations = null
			}
		}
	}

	/**
	 * Get list of listable conversations (open to registered users) and write to ref
	 *
	 * @param query search text
	 * @param generation search generation
	 */
	async function fetchListedConversations(query: string, generation: number) {
		const { request, cancel } = CancelableRequest(searchListedConversations)
		try {
			// Cancel previous search request if pending, and store reference to new one
			cancelSearchListedConversations?.()
			cancelSearchListedConversations = cancel

			const response = await request(query)

			if (generation !== searchGenerations.conversations) {
				// Results are outdated
				return
			}

			searchResultsListedConversations.value = response.data.ocs.data
		} catch (exception) {
			if (isCancel(exception)) {
				return
			}
			console.error('Error searching for open conversations', exception)
			if (generation === searchGenerations.conversations) {
				// Drop results of the failed search
				searchResultsListedConversations.value = []
			}
			throw exception
		} finally {
			if (cancelSearchListedConversations === cancel) {
				cancelSearchListedConversations = null
			}
		}
	}

	/**
	 * Get list of messages matching the query and write to ref
	 *
	 * @param query search text
	 * @param generation search generation
	 */
	async function fetchMessages(query: string, generation: number) {
		const { request, cancel } = CancelableRequest(searchMessages)
		try {
			// Cancel previous search request if pending, and store reference to new one
			cancelSearchMessages?.()
			cancelSearchMessages = cancel

			// TODO: implement offset with 'Show more' feature
			const response = await request({ term: query, limit: 10 })

			if (generation !== searchGenerations.messages) {
				// Results are outdated
				return
			}

			searchResultsMessages.value = response.data.ocs.data.entries.map(mapMessageResultEntry)
		} catch (exception) {
			if (isCancel(exception)) {
				return
			}
			console.error('Error searching for messages', exception)
			if (generation === searchGenerations.messages) {
				// Drop results of the failed search
				searchResultsMessages.value = []
			}
			throw exception
		} finally {
			if (cancelSearchMessages === cancel) {
				cancelSearchMessages = null
			}
		}
	}

	/**
	 * Cancel the pending requests of a filter
	 *
	 * @param filter filter to cancel the requests of
	 * @return whether any request was pending
	 */
	function cancelFilterRequests(filter: SearchFilter): boolean {
		if (filter === 'conversations') {
			const hasPendingRequests = cancelSearchListedConversations !== null
				|| cancelSearchPossibleConversations !== null

			cancelSearchListedConversations?.()
			cancelSearchListedConversations = null

			cancelSearchPossibleConversations?.()
			cancelSearchPossibleConversations = null

			return hasPendingRequests
		}

		const hasPendingRequests = cancelSearchMessages !== null

		cancelSearchMessages?.()
		cancelSearchMessages = null

		return hasPendingRequests
	}

	/**
	 * Stop searching in a filter, keeping its results to show them again
	 * if it is re-enabled with an unchanged query
	 *
	 * @param filter filter to disable
	 */
	function disableFilter(filter: SearchFilter) {
		searchGenerations[filter]++

		if (cancelFilterRequests(filter)) {
			// Results of the aborted requests are incomplete and cannot be reused
			fetchedQueries[filter] = null
		}
	}

	/**
	 * Drop the results of a filter and invalidate its pending requests
	 *
	 * @param filter filter to clear
	 */
	function clearFilter(filter: SearchFilter) {
		disableFilter(filter)
		fetchedQueries[filter] = null

		if (filter === 'conversations') {
			searchResultsListedConversations.value = []
			searchResultsPossibleConversations.value = []
		} else {
			searchResultsMessages.value = []
		}
	}

	/**
	 * Keep loading as long as any request is still waiting for its results
	 */
	function updateLoadingState() {
		searchResultsLoading.value = cancelSearchListedConversations !== null
			|| cancelSearchPossibleConversations !== null
			|| cancelSearchMessages !== null
	}

	/**
	 * Fetch and prepare results (in parallel)
	 * Run for enabled filters, drop results for disabled ones
	 *
	 * @param query search text
	 * @return whether the results of this search were applied
	 */
	async function search(query: string): Promise<boolean> {
		for (const filter of SEARCH_FILTERS) {
			if (!searchFilters.value.includes(filter)) {
				clearFilter(filter)
			}
		}

		return await searchInFilters(query, [...searchFilters.value])
	}

	/**
	 * Fetch and prepare results of the given filters
	 *
	 * @param query search text
	 * @param filters filters to search in
	 * @return whether the results of this search were applied
	 */
	async function searchInFilters(query: string, filters: SearchFilter[]): Promise<boolean> {
		searchResultsLoading.value = true

		const generations = filters.map((filter) => [filter, ++searchGenerations[filter]] as const)
		const promiseResults = await Promise.all(generations.map(async ([filter, generation]) => {
			const results = await Promise.allSettled(filter === 'conversations'
				? [fetchListedConversations(query, generation), fetchPossibleConversations(query, generation)]
				: [fetchMessages(query, generation)])

			if (generation === searchGenerations[filter]) {
				// Only complete results of the current query can be shown again later
				fetchedQueries[filter] = results.every((result) => result.status === 'fulfilled')
					? query
					: null
			}

			return results
		}))

		// A superseding search keeps loading with its own pending requests
		updateLoadingState()

		if (generations.some(([filter, generation]) => generation !== searchGenerations[filter])) {
			// Search was superseded by a newer one or aborted, do not proceed
			return false
		}

		if (promiseResults.flat().some((result) => result.status === 'rejected')) {
			showError(t('spreed', 'An error occurred while performing the search'))
		}

		return true
	}

	/**
	 * Enable or disable a filter, fetching or hiding its results.
	 * The results of the other filters are kept, even if their search is still pending
	 *
	 * @param query search text
	 * @param filter filter to toggle
	 * @return whether the results were updated
	 */
	async function toggleFilter(query: string, filter: SearchFilter): Promise<boolean> {
		const shouldRemoveFilter = searchFilters.value.includes(filter)
		searchFilters.value = shouldRemoveFilter
			? searchFilters.value.filter((item) => item !== filter)
			: [...searchFilters.value, filter]
		BrowserStorage.setItem('globalSearchFilters', searchFilters.value.join(','))

		if (shouldRemoveFilter) {
			disableFilter(filter)
			updateLoadingState()
			return true
		} else if (query === '') {
			// Nothing to fetch until a search is started
			return false
		} else if (fetchedQueries[filter] === query) {
			// Results of this query are still there and shown again as is
			return true
		} else {
			return await searchInFilters(query, [filter])
		}
	}

	/**
	 * Abort running requests and cleanup cancel functions
	 */
	function abortSearchRequests() {
		for (const filter of SEARCH_FILTERS) {
			// Invalidate a pending search, so that its results are not applied
			searchGenerations[filter]++
			cancelFilterRequests(filter)
			// The search is over, its results are not shown again
			fetchedQueries[filter] = null
		}
	}

	return {
		searchFilters,
		searchResultsPossibleConversations,
		searchResultsListedConversations,
		searchResultsMessages,
		searchResultsLoading,
		search,
		toggleFilter,
		abortSearchRequests,
	}
}
