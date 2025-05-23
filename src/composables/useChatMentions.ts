/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, Ref } from 'vue'
import type { ChatMention } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import Vue, { computed, ref } from 'vue'
import { ATTENDEE } from '../constants.ts'
import { getConversationAvatarOcsUrl, getUserProxyAvatarOcsUrl } from '../services/avatarService.ts'
import { searchPossibleMentions } from '../services/mentionsService.ts'

type AutocompleteChatMention = Omit<ChatMention, 'status'> & {
	icon?: string
	iconUrl?: string
	subline?: string | null
	status?: {
		status: string
		icon?: string | null
	}
}
type AutoCompleteCallback = (args: AutocompleteChatMention[]) => void
type UserData = Record<string, AutocompleteChatMention>
type UserDataTokenMap = Record<string, UserData>
type ReturnType = {
	autoComplete: (search: string, callback: AutoCompleteCallback) => void
	userData: ComputedRef<UserData>
}

const userDataTokenMap = ref<UserDataTokenMap>({})

/**
 * Provides autoComplete fallback and cached mention object for NcRichContenteditable
 * @param token conversation token
 */
export function useChatMentions(token: Ref<string>): ReturnType {
	const isDarkTheme = useIsDarkTheme()
	const userData = computed(() => {
		return userDataTokenMap.value[token.value] ?? {}
	})

	/**
	 * Prepare and cache search results
	 * @param possibleMention mention object from API response
	 * @param token conversation token
	 * @param isDarkTheme whether current theme is dark
	 */
	function parseMention(possibleMention: ChatMention, token: string, isDarkTheme: boolean): AutocompleteChatMention {
		const chatMention: AutocompleteChatMention = {
			...possibleMention,
			id: possibleMention.mentionId ?? possibleMention.id, // mentionId should be the default match since 'federation-v1'
			status: undefined,
		}

		// Set icon for candidate mentions that are not for users.
		if (possibleMention.source === 'calls') {
			chatMention.icon = 'icon-user-forced-white'
			chatMention.iconUrl = getConversationAvatarOcsUrl(token, isDarkTheme)
			chatMention.subline = possibleMention?.details || t('spreed', 'Everyone')
		} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.GROUPS) {
			chatMention.icon = 'icon-group-forced-white'
			chatMention.subline = t('spreed', 'Group')
		} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.CIRCLES
			|| possibleMention.source === ATTENDEE.ACTOR_TYPE.TEAMS) {
			chatMention.icon = 'icon-team-forced-white'
			chatMention.subline = t('spreed', 'Team')
		} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.GUESTS) {
			chatMention.icon = 'icon-user-forced-white'
			chatMention.subline = t('spreed', 'Guest')
		} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.EMAILS) {
			chatMention.icon = 'icon-user-forced-white'
			chatMention.subline = possibleMention?.details ?? t('spreed', 'Guest')
		} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
			chatMention.icon = 'icon-user-forced-white'
			chatMention.iconUrl = getUserProxyAvatarOcsUrl(token, possibleMention.id, isDarkTheme, 64)
		} else {
			// The avatar is automatically shown for users, but an icon is nevertheless required as fallback.
			chatMention.icon = 'icon-user-forced-white'
			if (possibleMention.source === ATTENDEE.ACTOR_TYPE.USERS && possibleMention.id !== possibleMention.mentionId) {
				// Prevent local users avatars in federated room to be overwritten
				chatMention.iconUrl = generateUrl('avatar/{userId}/64' + (isDarkTheme ? '/dark' : '') + '?v=0', { userId: possibleMention.id })
			}
			// Convert status properties to an object.
			if (possibleMention.status) {
				chatMention.status = {
					status: possibleMention.status,
					icon: possibleMention.statusIcon,
				}
				chatMention.subline = possibleMention.statusMessage
			}
		}

		// caching the user id data for each possible mention
		if (!userDataTokenMap.value[token]) {
			Vue.set(userDataTokenMap.value, token, {})
		}
		Vue.set(userDataTokenMap.value[token], chatMention.id, chatMention)

		return chatMention
	}

	/**
	 * Prepare and cache search results
	 * @param token conversation token
	 * @param search search string
	 * @param isDarkTheme whether current theme is dark
	 */
	async function getMentions(token: string, search: string, isDarkTheme: boolean): Promise<AutocompleteChatMention[]> {
		try {
			const response = await searchPossibleMentions(token, search)
			return response.data.ocs.data.map((possibleMention) => parseMention(possibleMention, token, isDarkTheme))
		} catch (error) {
			console.error('Error while searching possible mentions: ', error)
			return []
		}
	}

	/**
	 * @param search search string
	 * @param callback callback for autocomplete feature
	 */
	async function autoComplete(search: string, callback: AutoCompleteCallback) {
		const autocompleteResults = await getMentions(token.value, search, isDarkTheme.value)
		if (!autocompleteResults.length) {
			// It was not possible to get the candidate mentions, so just keep the previous ones.
			return
		}

		callback(autocompleteResults)
	}

	return {
		autoComplete,
		userData,
	}
}
