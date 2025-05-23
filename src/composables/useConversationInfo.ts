/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, Ref } from 'vue'
import type { ChatMessage, Conversation } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { toRef } from '@vueuse/core'
import { computed, ref } from 'vue'
import { ATTENDEE, CONVERSATION, PARTICIPANT } from '../constants.ts'
import { getEventTimeRange } from '../utils/conversation.ts'
import { futureRelativeTime, ONE_DAY_IN_MS } from '../utils/formattedTime.ts'
import { getMessageIcon } from '../utils/getMessageIcon.ts'
import { useStore } from './useStore.js'

type Payload = {
	item: Ref<Conversation> | ComputedRef<Conversation>
	isSearchResult: Ref<boolean | null>
	exposeMessagesRef: Ref<boolean | null>
	exposeDescriptionRef: Ref<boolean | null>
}

const TITLE_MAX_LENGTH = 1000

/**
 * Reusable properties for Conversation... items
 * @param payload - function payload
 * @param payload.item - conversation item
 * @param payload.isSearchResult - whether conversation item appears as search result
 * @param payload.exposeMessagesRef - whether to show messages in conversation item
 * @param payload.exposeDescriptionRef - whether to show description in conversation item
 */
export function useConversationInfo({
	item,
	isSearchResult = ref(null),
	exposeMessagesRef = ref(null),
	exposeDescriptionRef = ref(null),
}: Payload) {
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

	/**
	 * Check whether last message of the conversation exists.
	 * If not an object, can be either '[]' (before 21.0.0) or 'undefined' (21.0.0+)
	 */
	const hasLastMessage = computed(() => {
		return !!item.value?.lastMessage && !!Object.keys(Object(item.value?.lastMessage)).length
	})

	/**
	 * The last message of the conversation.
	 * To be used only if 'hasLastMessage' is true.
	 */
	const lastMessage = toRef(() => item.value.lastMessage!)

	/**
	 * Simplified version of the last chat message.
	 * Parameters are parsed without markup (just replaced with the name),
	 * e.g. no avatars on mentions.
	 */
	const simpleLastChatMessage = computed(() => {
		if (!exposeMessages || !hasLastMessage.value) {
			return ''
		}

		const params = lastMessage.value.messageParameters
		let subtitle = lastMessage.value.message.trim()

		// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
		Object.keys(params).forEach((parameterKey) => {
			subtitle = subtitle.replaceAll('{' + parameterKey + '}', params[parameterKey].name)
		})

		return subtitle
	})

	/**
	 * @return Part of the name until the first space
	 */
	const shortLastChatMessageAuthor = computed(() => {
		if (!exposeMessages || !hasLastMessage.value || lastMessage.value.systemMessage.length) {
			return ''
		}

		const author = lastMessage.value.actorDisplayName.trim().split(' ')[0]

		if (!author && lastMessage.value.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
			return t('spreed', 'Guest')
		}

		return author
	})

	const conversationInformation = computed(() => {
		// temporary item while joining, only for Conversation component
		if (isSearchResult.value === false && !item.value.actorId) {
			return {
				actor: null,
				icon: null,
				message: t('spreed', 'Joining conversation …'),
				title: t('spreed', 'Joining conversation …'),
			}
		}

		// This is for event conversations where no messages from participants are shown
		const startTime = getEventTimeRange(item.value).start
		if (item.value.objectType === CONVERSATION.OBJECT_TYPE.EVENT
			&& startTime && startTime > Date.now()) {
			// Check if there is a message to display
			const store = useStore()
			const hasHumanMessage = item.value.unreadMessages !== 0 || store.getters.messagesList(item.value.token).some((message: ChatMessage) => {
				return message.systemMessage === '' && message.messageType !== 'comment_deleted'
			})

			if (!hasHumanMessage && startTime - Date.now() < ONE_DAY_IN_MS) {
				return {
					actor: null,
					icon: null,
					message: futureRelativeTime(startTime),
					title: futureRelativeTime(startTime),
				}
			} else if (!hasHumanMessage) {
				return {
					actor: null,
					icon: null,
					message: moment(startTime).calendar(),
					title: moment(startTime).calendar(),
				}
			}
		}

		if (!exposeMessages) {
			return {
				actor: null,
				icon: null,
				message: exposeDescription ? item.value?.description : '',
				title: exposeDescription ? item.value?.description : null,
			}
		} else if (!hasLastMessage.value) {
			return {
				actor: null,
				icon: null,
				message: t('spreed', 'No messages'),
				title: t('spreed', 'No messages'),
			}
		}

		if (shortLastChatMessageAuthor.value === '') {
			return {
				actor: null,
				icon: getMessageIcon(lastMessage.value),
				message: simpleLastChatMessage.value,
				title: simpleLastChatMessage.value.slice(0, TITLE_MAX_LENGTH),
			}
		}

		if (lastMessage.value.actorId === item.value.actorId
			&& lastMessage.value.actorType === item.value.actorType) {
			return {
				// TRANSLATORS Prefix for messages shown in navigation list
				actor: t('spreed', 'You:'),
				icon: getMessageIcon(lastMessage.value),
				message: simpleLastChatMessage.value,
				title: t('spreed', 'You: {lastMessage}', {
					lastMessage: simpleLastChatMessage.value,
				}, { escape: false, sanitize: false }).slice(0, TITLE_MAX_LENGTH),
			}
		}

		if ([CONVERSATION.TYPE.ONE_TO_ONE,
			CONVERSATION.TYPE.ONE_TO_ONE_FORMER,
			CONVERSATION.TYPE.CHANGELOG].includes(item.value.type)) {
			return {
				actor: null,
				icon: getMessageIcon(lastMessage.value),
				message: simpleLastChatMessage.value,
				title: simpleLastChatMessage.value.slice(0, TITLE_MAX_LENGTH),
			}
		}

		return {
			// TRANSLATORS Actor name prefixing for messages shown in navigation list
			actor: t('spreed', '{actor}:', { actor: shortLastChatMessageAuthor.value }, { escape: false, sanitize: false }),
			icon: getMessageIcon(lastMessage.value),
			message: simpleLastChatMessage.value,
			title: t('spreed', '{actor}: {lastMessage}', {
				actor: shortLastChatMessageAuthor.value,
				lastMessage: simpleLastChatMessage.value,
			}, { escape: false, sanitize: false }).slice(0, TITLE_MAX_LENGTH),
		}
	})

	const isOneToOneConversation = computed(() => {
		return [CONVERSATION.TYPE.ONE_TO_ONE, CONVERSATION.TYPE.ONE_TO_ONE_FORMER].includes(item.value.type)
	})

	const isConversationReadOnly = computed(() => {
		return item.value.readOnly === CONVERSATION.STATE.READ_ONLY
	})

	const isConversationModifiable = computed(() => !isConversationReadOnly.value
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
