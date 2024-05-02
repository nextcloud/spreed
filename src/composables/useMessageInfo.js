/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed } from 'vue'

import { getCapabilities } from '@nextcloud/capabilities'
import moment from '@nextcloud/moment'

import { useConversationInfo } from './useConversationInfo.js'
import { useStore } from './useStore.js'

const canDeleteMessageUnlimited = getCapabilities()?.spreed?.features?.includes('delete-messages-unlimited')
const canEditMessage = getCapabilities()?.spreed?.features?.includes('edit-messages')

/**
 * Check whether the user can edit the message or not
 *
 * @param {string} token conversation token
 * @param {string} messageId message id to edit
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useMessageInfo(token = null, messageId = null) {
	const store = useStore()

	// Get the conversation and message
	const conversation = computed(() => store.getters.conversation(token))
	const message = computed(() => store.getters.message(token, messageId))

	// If the conversation or message is not available, return false
	if (!conversation.value || !message.value) {
		return {
			isEditable: computed(() => false),
			isDeleteable: computed(() => false),
			isCurrentUserOwnMessage: computed(() => false),
			isObjectShare: computed(() => false),
			isConversationModifiable: computed(() => false),
			isConversationReadOnly: computed(() => false),
			isFileShareWithoutCaption: computed(() => false),
			isFileShare: computed(() => false),
		}
	}

	const {
		isOneToOneConversation,
		isConversationReadOnly,
		isConversationModifiable,
	} = useConversationInfo({ item: conversation })

	const isObjectShare = computed(() => Object.keys(Object(message.value.messageParameters)).some(key => key.startsWith('object')))

	const isCurrentUserOwnMessage = computed(() =>
		message.value.actorId === store.getters.getActorId()
		&& message.value.actorType === store.getters.getActorType()
	)

	const isEditable = computed(() => {
		if (!canEditMessage || !isConversationModifiable.value || isObjectShare.value
			|| ((!store.getters.isModerator || isOneToOneConversation.value) && !isCurrentUserOwnMessage.value)) {
			return false
		}

		return (moment(message.value.timestamp * 1000).add(1, 'd')) > moment()
	})

	const isFileShare = computed(() => Object.keys(Object(message.value.messageParameters)).some(key => key.startsWith('file')))

	const isFileShareWithoutCaption = computed(() => message.value.message === '{file}' && isFileShare.value)

	const isDeleteable = computed(() => {
		if (!isConversationModifiable.value) {
			return false
		}

		return (canDeleteMessageUnlimited || (moment(message.value.timestamp * 1000).add(6, 'h')) > moment())
			&& (message.value.messageType === 'comment' || message.value.messageType === 'voice-message')
			&& (isCurrentUserOwnMessage.value || (!isOneToOneConversation.value && store.getters.isModerator))
			&& !isConversationReadOnly.value
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
	}

}
