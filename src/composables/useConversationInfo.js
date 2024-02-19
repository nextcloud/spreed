/*
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { computed, ref } from 'vue'

import { ATTENDEE, CONVERSATION } from '../constants.js'

/**
 * Reusable properties for Conversation... items
 * @param {object} payload function payload
 * @param {import('vue').Ref} payload.item conversation item
 * @param {import('vue').Ref} [payload.isSearchResult] whether conversation item appears as search result
 * @param {import('vue').Ref} [payload.exposeMessages] whether to show messages in conversation item
 */
export function useConversationInfo({
	item,
	isSearchResult = ref(null),
	exposeMessages = ref(null),
}) {
	const counterType = computed(() => {
		if (exposeMessages.value === false) {
			return ''
		} else if (item.value.unreadMentionDirect || (item.value.unreadMessages !== 0
			&& [CONVERSATION.TYPE.ONE_TO_ONE, CONVERSATION.TYPE.ONE_TO_ONE_FORMER].includes(item.value.type)
		)) {
			return 'highlighted'
		} else if (item.value.unreadMention) {
			return 'outlined'
		} else {
			return ''
		}
	})

	const hasLastMessage = computed(() => {
		return !!Object.keys(Object(item.value?.lastMessage)).length
	})

	/**
	 * Simplified version of the last chat message.
	 * Parameters are parsed without markup (just replaced with the name),
	 * e.g. no avatars on mentions.
	 */
	const simpleLastChatMessage = computed(() => {
		if (exposeMessages.value === false || !hasLastMessage.value) {
			return ''
		}

		const params = item.value?.lastMessage.messageParameters
		let subtitle = item.value?.lastMessage.message.trim()

		// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
		Object.keys(params).forEach((parameterKey) => {
			subtitle = subtitle.replace('{' + parameterKey + '}', params[parameterKey].name)
		})

		return subtitle
	})

	/**
	 * @return {string} Part of the name until the first space
	 */
	const shortLastChatMessageAuthor = computed(() => {
		if (exposeMessages.value === false || !hasLastMessage.value || item.value.lastMessage.systemMessage.length) {
			return ''
		}

		const author = item.value.lastMessage.actorDisplayName.trim().split(' ').shift()

		if (author.length === 0 && item.value.lastMessage.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
			return t('spreed', 'Guest')
		}

		return author
	})

	const conversationInformation = computed(() => {
		// temporary item while joining, only for Conversation component
		if (isSearchResult.value === false && !item.value.actorId) {
			return t('spreed', 'Joining conversation …')
		}

		if (exposeMessages.value === false || !hasLastMessage.value) {
			return ''
		}

		if (shortLastChatMessageAuthor.value === '') {
			return simpleLastChatMessage.value
		}

		if (item.value?.lastMessage.actorId === item.value.actorId
			&& item.value?.lastMessage.actorType === item.value.actorType) {
			return t('spreed', 'You: {lastMessage}', {
				lastMessage: simpleLastChatMessage.value,
			}, undefined, {
				escape: false,
				sanitize: false,
			})
		}

		if ([CONVERSATION.TYPE.ONE_TO_ONE,
			CONVERSATION.TYPE.ONE_TO_ONE_FORMER,
			CONVERSATION.TYPE.CHANGELOG].includes(item.value.type)) {
			return simpleLastChatMessage.value
		}

		return t('spreed', '{actor}: {lastMessage}', {
			actor: shortLastChatMessageAuthor.value,
			lastMessage: simpleLastChatMessage.value,
		}, undefined, {
			escape: false,
			sanitize: false,
		})
	})

	return {
		counterType,
		conversationInformation,
	}
}
