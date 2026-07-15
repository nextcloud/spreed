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

let cancelSearchListedConversations: ReturnType<typeof CancelableRequest>['cancel'] | null = null
let cancelSearchPossibleConversations: ReturnType<typeof CancelableRequest>['cancel'] | null = null

/**
 * Composable to control logic for fetching search results:
 * - listed conversations
 * - possible conversations (users, groups, teams)
 *
 * Has only single consumer (LeftSidebar.vue)
 */
export function useSearchConversationsResults() {
	const actorStore = useActorStore()
	const store = useStore()

	const searchResultsListedConversations = ref<Conversation[]>([])
	const searchResultsPossibleConversations = ref<AutocompleteResult[]>([])
	const searchResultsLoading = ref<boolean>(true)

	onBeforeUnmount(() => {
		abortSearchRequests()
	})

	/**
	 * Get list of possible conversations (users, groups, teams) and write to ref
	 *
	 * @param query search text
	 */
	async function fetchPossibleConversations(query: string) {
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

			// Get all known user ids of 1:1 conversations
			const oneToOneMap = new Set(store.getters.conversationsList.reduce(
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
	 */
	async function fetchListedConversations(query: string) {
		const { request, cancel } = CancelableRequest(searchListedConversations)
		try {
			// Cancel previous search request if pending, and store reference to new one
			cancelSearchListedConversations?.()
			cancelSearchListedConversations = cancel

			const response = await request(query)
			searchResultsListedConversations.value = response.data.ocs.data
		} catch (exception) {
			if (isCancel(exception)) {
				return
			}
			console.error('Error searching for open conversations', exception)
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
	 */
	async function search(query: string) {
		searchResultsLoading.value = true

		const promiseResults = await Promise.allSettled([
			fetchListedConversations(query),
			fetchPossibleConversations(query),
		])

		if (
			cancelSearchListedConversations !== null
			|| cancelSearchPossibleConversations !== null
		) {
			// New search request was triggered, do not proceed
			return
		}

		if (promiseResults.some((result) => result.status === 'rejected')) {
			showError(t('spreed', 'An error occurred while performing the search'))
		}

		searchResultsLoading.value = false
	}

	/**
	 * Abort running requests and cleanup cancel functions
	 */
	function abortSearchRequests() {
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
