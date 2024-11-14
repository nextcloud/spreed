/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createSharedComposable } from '@vueuse/core'
import type { ComputedRef, Ref } from 'vue'
import Vue, { computed, ref } from 'vue'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import { useIsDarkTheme } from './useIsDarkTheme.ts'
import { ATTENDEE } from '../constants.js'
import { getConversationAvatarOcsUrl, getUserProxyAvatarOcsUrl } from '../services/avatarService.ts'
import { searchPossibleMentions } from '../services/mentionsService.ts'
import type { ChatMention } from '../types/index.ts'

type AutocompleteChatMention = Omit<ChatMention, 'status'> & {
	icon?: string,
	iconUrl?: string,
	subline?: string | null,
	status?: {
		status: string,
		icon?: string | null,
	},
}
type AutoCompleteCallback = (args: AutocompleteChatMention[]) => void
type UserData = Record<string, AutocompleteChatMention>
type UserDataTokenMap = Record<string, UserData>
type ReturnType = {
	autoComplete: (search: string, callback: AutoCompleteCallback) => void,
	userData: ComputedRef<UserData>,
}

/**
 * Provides autoComplete fallback and cached mention object for NcRichContenteditable
 * @param token conversation token
 */
function useChatMentionsComposable(token: Ref<string>): ReturnType {
	const isDarkTheme = useIsDarkTheme()
	const userDataTokenMap = ref<UserDataTokenMap>({})
	const userData = computed(() => {
		return userDataTokenMap.value[token.value] ?? {}
	})

	/**
	 * @param search search string
	 * @param callback callback for autocomplete feature
	 */
	async function autoComplete(search: string, callback: AutoCompleteCallback) {
		try {
			const response = await searchPossibleMentions(token.value, search)
			if (!response) {
				// It was not possible to get the candidate mentions, so just keep the previous ones.
				return
			}

			const possibleMentions = response.data.ocs.data

			possibleMentions.forEach(possibleMention => {
				// Set icon for candidate mentions that are not for users.
				if (possibleMention.source === 'calls') {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.iconUrl = getConversationAvatarOcsUrl(token.value, isDarkTheme.value)
					possibleMention.subline = possibleMention?.details ? possibleMention.details : t('spreed', 'Everyone')
				} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.GROUPS) {
					possibleMention.icon = 'icon-group-forced-white'
					possibleMention.subline = t('spreed', 'Group')
				} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.GUESTS) {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.subline = t('spreed', 'Guest')
				} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.iconUrl = getUserProxyAvatarOcsUrl(token.value, possibleMention.id, isDarkTheme.value, 64)
				} else {
					// The avatar is automatically shown for users, but an icon
					// is nevertheless required as fallback.
					possibleMention.icon = 'icon-user-forced-white'
					if (possibleMention.source === ATTENDEE.ACTOR_TYPE.USERS && possibleMention.id !== possibleMention.mentionId) {
						// Prevent local users avatars in federated room to be overwritten
						possibleMention.iconUrl = generateUrl('avatar/{userId}/64' + (isDarkTheme.value ? '/dark' : '') + '?v=0', { userId: possibleMention.id })
					}
					// Convert status properties to an object.
					if (possibleMention.status) {
						possibleMention.status = {
							status: possibleMention.status,
							icon: possibleMention.statusIcon,
						}
						possibleMention.subline = possibleMention.statusMessage
					}
				}

				// mentionId should be the default match since 'federation-v1'
				possibleMention.id = possibleMention.mentionId ?? possibleMention.id
				// caching the user id data for each possible mention
				if (!userDataTokenMap.value[token.value]) {
					Vue.set(userDataTokenMap.value, token.value, {})
				}
				Vue.set(userDataTokenMap.value[token.value], possibleMention.id, possibleMention)
			})

			callback(possibleMentions)
		} catch (error) {
			console.debug('Error while searching possible mentions: ', error)
		}
	}

	return {
		autoComplete,
		userData,
	}
}

/**
 * Shared composable to provide autoComplete fallback and cached mention object for NcRichContenteditable
 * @param token conversation token
 */
export const useChatMentions = createSharedComposable(useChatMentionsComposable)
