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
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'
import { searchListedConversations } from '../../../services/conversationsService.ts'
import { autocompleteQuery } from '../../../services/coreService.ts'
import { useActorStore } from '../../../stores/actor.ts'
import CancelableRequest from '../../../utils/CancelableRequest.ts'

const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')

/**
 * Composable to control logic for fetching search results:
 * - listed conversations
 * - possible conversations (users, groups, teams)
 *
 * Has only single consumer (LeftSidebar.vue)
 */
export function useSearchConversationsResults() {
	const actorStore = useActorStore()
	const vuexStore = useStore()

	const searchResultsListedConversations = ref<Conversation[]>([])
	const searchResultsPossibleConversations = ref<AutocompleteResult[]>([])
	const searchResultsLoading = ref(true)

	let cancelSearchListedConversations: ReturnType<typeof CancelableRequest>['cancel'] | null = null
	let cancelSearchPossibleConversations: ReturnType<typeof CancelableRequest>['cancel'] | null = null
	// Generation counter - increases with every started or aborted search.
	// Results of a search are only applied with matching generation
	let searchGeneration = 0

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

			if (generation !== searchGeneration) {
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
			if (generation === searchGeneration) {
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

			if (generation !== searchGeneration) {
				// Results are outdated
				return
			}

			searchResultsListedConversations.value = response.data.ocs.data
		} catch (exception) {
			if (isCancel(exception)) {
				return
			}
			console.error('Error searching for open conversations', exception)
			if (generation === searchGeneration) {
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
	 * Fetch and prepare results (in parallel)
	 *
	 * @param query search text
	 * @return whether the results of this search were applied
	 */
	async function search(query: string): Promise<boolean> {
		const generation = ++searchGeneration
		searchResultsLoading.value = true

		const promiseResults = await Promise.allSettled([
			fetchListedConversations(query, generation),
			fetchPossibleConversations(query, generation),
		])

		if (generation !== searchGeneration) {
			// Search was superseded by a newer one or aborted, do not proceed
			return false
		}

		if (promiseResults.some((result) => result.status === 'rejected')) {
			showError(t('spreed', 'An error occurred while performing the search'))
		}

		searchResultsLoading.value = false
		return true
	}

	/**
	 * Abort running requests and cleanup cancel functions
	 */
	function abortSearchRequests() {
		// Invalidate a pending search, so that its results are not applied
		searchGeneration++

		cancelSearchListedConversations?.()
		cancelSearchListedConversations = null

		cancelSearchPossibleConversations?.()
		cancelSearchPossibleConversations = null
	}

	return {
		searchResultsPossibleConversations,
		searchResultsListedConversations,
		searchResultsLoading,
		search,
		abortSearchRequests,
	}
}
