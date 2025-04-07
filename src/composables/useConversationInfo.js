/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import escapeHtml from 'escape-html'
import { computed, ref } from 'vue'

import { t } from '@nextcloud/l10n'

import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../constants.ts'
import { getMessageIcon } from '../utils/getMessageIcon.ts'

/**
 * Reusable properties for Conversation... items
 * @param {object} payload function payload
 * @param {import('vue').Ref} payload.item conversation item
 * @param {import('vue').Ref} [payload.isSearchResult] whether conversation item appears as search result
 * @param {import('vue').Ref} [payload.exposeMessagesRef] whether to show messages in conversation item
 * @param {import('vue').Ref} [payload.exposeDescriptionRef] whether to show description in conversation item
 */
export function useConversationInfo({
	item,
	isSearchResult = ref(null),
	exposeMessagesRef = ref(null),
	exposeDescriptionRef = ref(null),
}) {
	const exposeMessages = exposeMessagesRef.value !== null ? exposeMessagesRef.value : !isSearchResult.value
	const exposeDescription = exposeDescriptionRef.value !== null ? exposeDescriptionRef.value : isSearchResult.value

	const counterType = computed(() => {
		if (!exposeMessages) {
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
		return !!item.value?.lastMessage && !!Object.keys(Object(item.value?.lastMessage)).length
	})

	/**
	 * Simplified version of the last chat message.
	 * Parameters are parsed without markup (just replaced with the name),
	 * e.g. no avatars on mentions.
	 */
	const simpleLastChatMessage = computed(() => {
		if (!exposeMessages || !hasLastMessage.value) {
			return ''
		}

		const params = item.value?.lastMessage.messageParameters
		let subtitle = (getMessageIcon(item.value?.lastMessage) + ' ' + escapeHtml(item.value?.lastMessage.message)).trim()

		// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
		Object.keys(params).forEach((parameterKey) => {
			subtitle = subtitle.replaceAll('{' + parameterKey + '}', escapeHtml(params[parameterKey].name))
		})

		return subtitle
	})

	/**
	 * @return {string} Part of the name until the first space
	 */
	const shortLastChatMessageAuthor = computed(() => {
		if (!exposeMessages || !hasLastMessage.value || item.value.lastMessage.systemMessage.length) {
			return ''
		}

		const author = item.value.lastMessage.actorDisplayName.trim().split(' ').shift()

		if (author.length === 0 && item.value.lastMessage.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
			return t('spreed', 'Guest')
		}

		return escapeHtml(author)
	})

	const conversationInformation = computed(() => {
		// temporary item while joining, only for Conversation component
		if (isSearchResult.value === false && !item.value.actorId) {
			return t('spreed', 'Joining conversation …')
		}

		if (!exposeMessages) {
			return exposeDescription ? item.value?.description : ''
		} else if (!hasLastMessage.value) {
			return t('spreed', 'No messages')
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

	const isOneToOneConversation = computed(() => {
		return [CONVERSATION.TYPE.ONE_TO_ONE, CONVERSATION.TYPE.ONE_TO_ONE_FORMER].includes(item.value.type)
	})

	const isConversationReadOnly = computed(() => {
		return item.value.readOnly === CONVERSATION.STATE.READ_ONLY
	})

	const isConversationModifiable = computed(() =>
		!isConversationReadOnly.value
		&& item.value.participantType !== PARTICIPANT.TYPE.GUEST
		&& item.value.participantType !== PARTICIPANT.TYPE.GUEST_MODERATOR)

	return {
		counterType,
		conversationInformation,
		isOneToOneConversation,
		isConversationReadOnly,
		isConversationModifiable,
	}
}
