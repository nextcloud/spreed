/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref } from 'vue'

import { t } from '@nextcloud/l10n'

import { useConversationInfo } from './useConversationInfo.js'
import { useStore } from './useStore.js'
import { ATTENDEE, CONVERSATION } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { useGuestNameStore } from '../stores/guestName.js'
import { ONE_DAY_IN_MS, ONE_HOUR_IN_MS } from '../utils/formattedTime.ts'
import { getDisplayNameWithFallback } from '../utils/getDisplayName.ts'

/**
 * Check whether the user can edit the message or not
 *
 * @param {import('vue').Ref} message message object
 * @return {Record<string, import('vue').ComputedRef<boolean>>}
 */
export function useMessageInfo(message = ref({})) {
	// Get the conversation
	const store = useStore()
	const conversation = computed(() => store.getters.conversation(message.value.token))
	const currentActorId = store.getters.getActorId()
	const currentActorType = store.getters.getActorType()
	// If the conversation or message is not available, return false
	if (!conversation.value || !message.value.id) {
		return {
			isEditable: computed(() => false),
			isDeleteable: computed(() => false),
			isCurrentUserOwnMessage: computed(() => false),
			isBotInOneToOne: computed(() => false),
			isObjectShare: computed(() => false),
			isConversationModifiable: computed(() => false),
			isConversationReadOnly: computed(() => false),
			isFileShareWithoutCaption: computed(() => false),
			isFileShare: computed(() => false),
			remoteServer: computed(() => ''),
			lastEditor: computed(() => ''),
			actorDisplayName: computed(() => ''),
			actorDisplayNameWithFallback: computed(() => ''),
		}
	}

	const {
		isOneToOneConversation,
		isConversationReadOnly,
		isConversationModifiable,
	} = useConversationInfo({ item: conversation })

	const isObjectShare = computed(() => Object.keys(Object(message.value.messageParameters)).some(key => key.startsWith('object')))

	const isCurrentUserOwnMessage = computed(() =>
		message.value.actorId === currentActorId
		&& message.value.actorType === currentActorType
	)
	const isBotInOneToOne = computed(() =>
		message.value.actorId.startsWith(ATTENDEE.BOT_PREFIX)
		&& message.value.actorType === ATTENDEE.ACTOR_TYPE.BOTS
		&& (conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE
			|| conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER)
	)

	const isEditable = computed(() => {
		if (!hasTalkFeature(message.value.token, 'edit-messages') || !isConversationModifiable.value || isObjectShare.value || message.value.systemMessage
			|| ((!store.getters.isModerator || isOneToOneConversation.value) && !(isCurrentUserOwnMessage.value || isBotInOneToOne.value))) {
			return false
		}

		if (hasTalkFeature(message.value.token, 'edit-messages-note-to-self') && conversation.value.type === CONVERSATION.TYPE.NOTE_TO_SELF) {
			return true
		}

		return (Date.now() - message.value.timestamp * 1000 < ONE_DAY_IN_MS)
	})

	const isFileShare = computed(() => Object.keys(Object(message.value.messageParameters)).some(key => key.startsWith('file')))

	const isFileShareWithoutCaption = computed(() => message.value.message === '{file}' && isFileShare.value)

	const isDeleteable = computed(() =>
		(hasTalkFeature(message.value.token, 'delete-messages-unlimited') || (Date.now() - message.value.timestamp * 1000 < 6 * ONE_HOUR_IN_MS))
		&& (message.value.messageType === 'comment' || message.value.messageType === 'voice-message')
		&& (isCurrentUserOwnMessage.value || (!isOneToOneConversation.value && store.getters.isModerator))
		&& isConversationModifiable.value)

	const remoteServer = computed(() => {
		return message.value.actorType === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
			? '(' + message.value.actorId.split('@').pop() + ')'
			: ''
	})

	const lastEditor = computed(() => {
		if (!message.value.lastEditTimestamp) {
			return ''
		} else if (message.value.lastEditActorId === message.value.actorId
			&& message.value.lastEditActorType === message.value.actorType) {
			// TRANSLATORS Edited by the author of the message themselves
			return t('spreed', '(edited)')
		} else if (message.value.lastEditActorId === currentActorId
			&& message.value.lastEditActorType === currentActorType) {
			return t('spreed', '(edited by you)')
		} else if (message.value.lastEditActorId === 'deleted_users'
			&& message.value.lastEditActorType === 'deleted_users') {
			return t('spreed', '(edited by a deleted user)')
		} else {
			return t('spreed', '(edited by {moderator})', { moderator: message.value.lastEditActorDisplayName })
		}
	})

	const actorDisplayName = computed(() => {
		if ([ATTENDEE.ACTOR_TYPE.GUESTS, ATTENDEE.ACTOR_TYPE.EMAILS].includes(message.value.actorType)) {
			const guestNameStore = useGuestNameStore()
			return guestNameStore.getGuestName(message.value.token, message.value.actorId)
		} else {
			return message.value.actorDisplayName.trim()
		}
	})

	const actorDisplayNameWithFallback = computed(() => {
		return getDisplayNameWithFallback(actorDisplayName.value, message.value.actorType)
	})

	return {
		isEditable,
		isDeleteable,
		isCurrentUserOwnMessage,
		isObjectShare,
		isConversationModifiable,
		isConversationReadOnly,
		isFileShareWithoutCaption,
		isFileShare,
		remoteServer,
		lastEditor,
		actorDisplayName,
		actorDisplayNameWithFallback,
	}
}
