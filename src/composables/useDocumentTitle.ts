/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref, watch } from 'vue'
import type { ComputedRef } from 'vue'

import { t } from '@nextcloud/l10n'

import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useStore } from './useStore.js'
import Router from '../router/router.js'
import { EventBus } from '../services/EventBus.ts'
import type { Conversation } from '../types/index.ts'

/**
 * Composable to check whether the page is visible.
 */
export function useDocumentTitle() {
	const store = useStore()
	const isDocumentVisible = useDocumentVisibility()

	const defaultPageTitle = ref<string>('')
	const savedLastMessageMap = ref<Record<string, number>>({})

	const token = computed(() => store.getters.getToken())
	/**
	 * Keeps a list for all last message ids
	 *
	 * @return {object} Map with token => lastMessageId
	 */
	const lastMessageMap: ComputedRef<Record<string, number>> = computed(() => {
		const conversationList = store.getters.conversationsList
		if (conversationList.length === 0) {
			return {}
		}

		const lastMessage: Record<string, number> = {}
		conversationList.forEach((conversation: Conversation) => {
			lastMessage[conversation.token] = 0
			if (!Array.isArray(conversation.lastMessage)) {
				const currentActorIsAuthor = conversation.lastMessage.actorType === store.getters.getActorType()
					&& conversation.lastMessage.actorId === store.getters.getActorId()
				if (currentActorIsAuthor) {
					// Set a special value when the actor is the author so we can skip it.
					// Can't use 0 though because hidden commands result in 0
					// and they would hide other previously posted new messages
					lastMessage[conversation.token] = -1
				} else {
					lastMessage[conversation.token] = Math.max(
						// @ts-expect-error: id is missing for federated conversations
						conversation.lastMessage?.id ? conversation.lastMessage.id : 0,
						store.getters.getLastKnownMessageId(conversation.token) ? store.getters.getLastKnownMessageId(conversation.token) : 0,
					)
				}
			}
		})
		return lastMessage
	})

	/**
	 * @return {boolean} Returns true, if
	 * - a conversation is newly added to lastMessageMap
	 * - a conversation has a different last message id then previously
	 */
	const atLeastOneLastMessageIdChanged = computed(() => {
		let modified = false
		Object.keys(lastMessageMap.value).forEach(token => {
			if (!savedLastMessageMap.value[token] // Conversation is new
				|| (savedLastMessageMap.value[token] !== lastMessageMap.value[token] // Last message changed
					&& lastMessageMap.value[token] !== -1)) { // But is not from the current user
				modified = true
			}
		})

		return modified
	})

	watch(atLeastOneLastMessageIdChanged, () => {
		if (isDocumentVisible.value) {
			return
		}
		setPageTitle(getConversationName(token.value), atLeastOneLastMessageIdChanged.value)
	})

	watch(isDocumentVisible, () => {
		if (isDocumentVisible.value) {
			// Remove the potential "*" marker for unread chat messages
			let title = getConversationName(token.value)
			if (window.document.title.indexOf(t('spreed', 'Duplicate session')) === 0) {
				title = t('spreed', 'Duplicate session')
			}
			setPageTitle(title, false)
		} else {
			// Copy the last message map to the saved version,
			// this will be our reference to check if any chat got a new
			// message since the last visit
			savedLastMessageMap.value = lastMessageMap.value
		}
	})

	/**
	 * Adjust the page title to the conversation name once conversationsList is loaded
	 */
	EventBus.once('conversations-received', () => {
		if (Router.currentRoute.name === 'conversation') {
			setPageTitle(getConversationName(token.value), false)
		}
	})

	/**
	 * Change the page title after the route was changed
	 */
	Router.afterEach((to) => {
		if (to.name === 'conversation') {
			setPageTitle(getConversationName(to.params.token), false)
		} else if (to.name === 'notfound') {
			setPageTitle('', false)
		}
	})

	/**
	 * Set the page title to the conversation name
	 *
	 * @param title Prefix for the page title e.g. conversation name
	 * @param showAsterix Prefix for the page title e.g. conversation name
	 */
	function setPageTitle(title: string, showAsterix: boolean) {
		if (defaultPageTitle.value === '') {
			// On the first load we store the current page title "Talk - Nextcloud",
			// so we can append it every time again
			defaultPageTitle.value = window.document.title
			// Coming from a "Duplicate session - Talk - …" page?
			if (defaultPageTitle.value.indexOf(' - ' + t('spreed', 'Talk') + ' - ') !== -1) {
				defaultPageTitle.value = defaultPageTitle.value.substring(defaultPageTitle.value.indexOf(' - ' + t('spreed', 'Talk') + ' - ') + 3)
			}
			// When a conversation is opened directly, the "Talk - " part is
			// missing from the title
			if (!IS_DESKTOP && defaultPageTitle.value.indexOf(t('spreed', 'Talk') + ' - ') !== 0) {
				defaultPageTitle.value = t('spreed', 'Talk') + ' - ' + defaultPageTitle.value
			}
		}

		let newTitle = defaultPageTitle.value
		if (title !== '') {
			newTitle = `${title} - ${newTitle}`
		}
		if (showAsterix && !newTitle.startsWith('* ')) {
			newTitle = '* ' + newTitle
		}
		window.document.title = newTitle
	}

	/**
	 * Get a conversation's name.
	 *
	 * @param {string} token The conversation's token
	 * @return {string} The conversation's name
	 */
	function getConversationName(token: string) {
		if (!store.getters.conversation(token)) {
			return ''
		}

		return store.getters.conversation(token).displayName
	}
}
