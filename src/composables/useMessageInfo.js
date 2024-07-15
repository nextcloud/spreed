/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, ref } from 'vue'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import { useConversationInfo } from './useConversationInfo.js'
import { useStore } from './useStore.js'
import { ATTENDEE } from '../constants.js'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

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
		message.value.actorId === currentActorId
		&& message.value.actorType === currentActorType
	)

	const isEditable = computed(() => {
		if (!hasTalkFeature(message.value.token, 'edit-messages') || !isConversationModifiable.value || isObjectShare.value || message.value.systemMessage
			|| ((!store.getters.isModerator || isOneToOneConversation.value) && !isCurrentUserOwnMessage.value)) {
			return false
		}

		return (moment(message.value.timestamp * 1000).add(1, 'd')) > moment()
	})

	const isFileShare = computed(() => Object.keys(Object(message.value.messageParameters)).some(key => key.startsWith('file')))

	const isFileShareWithoutCaption = computed(() => message.value.message === '{file}' && isFileShare.value)

	const isDeleteable = computed(() =>
		(hasTalkFeature(message.value.token, 'delete-messages-unlimited') || (moment(message.value.timestamp * 1000).add(6, 'h')) > moment())
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
		const displayName = message.value.actorDisplayName.trim()

		if (displayName === '' && message.value.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
			return t('spreed', 'Guest')
		}

		if (displayName === '') {
			return t('spreed', 'Deleted user')
		}

		return displayName
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

	}

}
