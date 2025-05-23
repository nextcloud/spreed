/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Route } from 'vue-router'
import type { Conversation } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router/composables'
import { EventBus } from '../services/EventBus.ts'
import { hasCall, hasUnreadMentions } from '../utils/conversation.ts'
import { useDocumentVisibility } from './useDocumentVisibility.ts'
import { useStore } from './useStore.js'

type LastMessageMap = {
	[token: string]: {
		lastMessageId: number
		unreadMessages: number
	}
}

/**
 * Composable to check whether the page is visible.
 */
export function useDocumentTitle() {
	const store = useStore()
	const router = useRouter()
	const route = useRoute()
	const isDocumentVisible = useDocumentVisibility()

	const defaultPageTitle = ref<string>(getDefaultPageTitle())
	const showAsterisk = ref(false)
	const savedLastMessageMap = ref<LastMessageMap>({})

	const conversationList = computed(() => store.getters.conversationsList)
	const actorId = computed(() => store.getters.getActorId())
	const actorType = computed(() => store.getters.getActorType())

	watch(conversationList, (newValue) => {
		if (isDocumentVisible.value || document.title.startsWith('* ')
			|| !Object.keys(savedLastMessageMap.value).length) {
			return
		}

		const newLastMessageMap = getLastMessageMap(newValue)
		/**
		 * @return {boolean} Returns true, if
		 * - a conversation is newly added to lastMessageMap
		 * - a conversation has a different last message id then previously
		 */
		const shouldShowAsterisk = Object.keys(newLastMessageMap).some((token) => {
			return savedLastMessageMap.value[token] === undefined // Conversation is new
				|| (savedLastMessageMap.value[token].lastMessageId !== newLastMessageMap[token].lastMessageId // Last message changed
					&& savedLastMessageMap.value[token].unreadMessages !== newLastMessageMap[token].unreadMessages // Unread messages changed
					&& newLastMessageMap[token].lastMessageId !== -1) // And it is not under one of the exceptions (see getLastMessageMap())
		})
		if (shouldShowAsterisk) {
			showAsterisk.value = true
			setPageTitleFromRoute(route)
		}
	})

	watch(isDocumentVisible, () => {
		if (isDocumentVisible.value) {
			// Remove asterisk for unread chat messages
			showAsterisk.value = false
			setPageTitleFromRoute(route)
		} else {
			// Copy the last message map to the saved version,
			// this will be our reference to check if any chat got a new
			// message since the last visit
			savedLastMessageMap.value = getLastMessageMap(conversationList.value)
		}
	})

	/**
	 * Adjust the page title to the conversation name once conversationsList is loaded
	 */
	EventBus.once('conversations-received', () => {
		setPageTitleFromRoute(route)
	})

	/**
	 * Change the page title after the route was changed
	 */
	router.afterEach((to) => setPageTitleFromRoute(to))

	/**
	 * Get a list for all last message ids
	 *
	 * @param conversationList array of conversations
	 */
	function getLastMessageMap(conversationList: Conversation[]): LastMessageMap {
		if (conversationList.length === 0) {
			return {}
		}

		return conversationList.reduce((acc: LastMessageMap, conversation: Conversation) => {
			const { token, lastMessage, isArchived, unreadMessages } = conversation
			// Default to 0 for messages without valid lastMessage
			acc[token] = {
				lastMessageId: 0,
				unreadMessages: 0,
			}
			if (!lastMessage || Array.isArray(lastMessage)) {
				return acc
			}

			if ((lastMessage.actorId === actorId.value && lastMessage.actorType === actorType.value)
				|| (isArchived && !hasUnreadMentions(conversation) && !hasCall(conversation))) {
				// Set a special value when (one of the following is true):
				// - The actor is the author
				// - The conversation is archived and no relevant notification (mention or call).
				// so to skip the asterisk for these messages
				// Can't use 0 though because hidden commands result in 0,
				// and they would hide other previously posted new messages
				acc[token].lastMessageId = -1
			} else {
				// @ts-expect-error: Property 'id' does not exist on type ChatProxyMessage
				const lastMessageId = lastMessage.id ?? 0
				const lastKnownMessageId = store.getters.getLastKnownMessageId(token) ?? 0
				acc[token].lastMessageId = Math.max(lastMessageId, lastKnownMessageId)
			}
			acc[token].unreadMessages = unreadMessages
			return acc
		}, {})
	}

	/**
	 *
	 * @param route current web route
	 */
	function setPageTitleFromRoute(route: Route) {
		switch (route.name) {
			case 'conversation':
				setPageTitle(store.getters.conversation(route.params.token)?.displayName ?? '')
				break
			case 'duplicatesession':
				setPageTitle(t('spreed', 'Duplicate session'))
				break
			default:
				setPageTitle('')
		}
	}

	/**
	 * Set the page title to the conversation name
	 *
	 * @param title Prefix for the page title e.g. conversation name
	 */
	function setPageTitle(title: string) {
		const newTitle = title ? `${title} - ${defaultPageTitle.value}` : defaultPageTitle.value
		document.title = (showAsterisk.value && !newTitle.startsWith('* '))
			? '* ' + newTitle
			: newTitle
	}

	/**
	 * Get the default page title of Talk page like "Talk - Nextcloud", to append it every time again
	 */
	function getDefaultPageTitle() {
		// Do nothing on Desktop
		if (IS_DESKTOP) {
			return document.title
		}
		const appNamePrefix = t('spreed', 'Talk') + ' - '
		// If coming from a "… - Talk - …" page, keep only "Talk - …" part
		if (document.title.includes(' - ' + appNamePrefix)) {
			return document.title.substring(document.title.indexOf(' - ' + appNamePrefix) + 3)
		}
		// When a conversation is opened directly, "Talk - " might be missing from the title
		if (!document.title.startsWith(appNamePrefix)) {
			return appNamePrefix + document.title
		}
		return document.title
	}
}
