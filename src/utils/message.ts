/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage, Conversation } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { ATTENDEE, CONVERSATION, MENTION, MESSAGE } from '../constants.ts'

/**
 * Module augmentation to declare the t function from `@nextcloud/l10n`
 * (no placeholders should be replaced until it reaches NcRichText)
 *
 * @param app - app name ('spreed')
 * @param text - translated string
 */
declare module '@nextcloud/l10n' {
	export function t(app: string, text: string): string
}

/** Specific type for chat-relayed system message 'MESSAGE.SYSTEM_TYPE.CALL_STARTED' */
type ChatMessageCallStarted = ChatMessage & {
	call?: {
		silent?: boolean
	}
}

/**
 * Returns correct mention type for self actor
 */
const SELF_MENTION_TYPE = {
	[ATTENDEE.ACTOR_TYPE.USERS]: MENTION.TYPE.USER,
	[ATTENDEE.ACTOR_TYPE.FEDERATED_USERS]: MENTION.TYPE.FEDERATED_USER,
	[ATTENDEE.ACTOR_TYPE.EMAILS]: MENTION.TYPE.EMAIL,
	[ATTENDEE.ACTOR_TYPE.GUESTS]: MENTION.TYPE.GUEST,
} as const

type SelfMentionTypeKey = keyof typeof SELF_MENTION_TYPE

/**
 * Sync with server-side constant SYSTEM_MESSAGE_TYPE_RELAY in lib/Signaling/Listener.php
 */
const SYSTEM_MESSAGE_TYPE_RELAY = [
	MESSAGE.SYSTEM_TYPE.CALL_STARTED, // 'call_started',
	MESSAGE.SYSTEM_TYPE.CALL_JOINED, // 'call_joined',
	MESSAGE.SYSTEM_TYPE.CALL_LEFT, // 'call_left',
	MESSAGE.SYSTEM_TYPE.CALL_ENDED, // 'call_ended',
	MESSAGE.SYSTEM_TYPE.CALL_ENDED_EVERYONE, // 'call_ended_everyone',
	MESSAGE.SYSTEM_TYPE.THREAD_CREATED, // 'thread_created',
	MESSAGE.SYSTEM_TYPE.THREAD_RENAMED, // 'thread_renamed',
	MESSAGE.SYSTEM_TYPE.MESSAGE_DELETED, // 'message_deleted',
	MESSAGE.SYSTEM_TYPE.MESSAGE_EDITED, // 'message_edited',
	MESSAGE.SYSTEM_TYPE.MODERATOR_PROMOTED, // 'moderator_promoted',
	MESSAGE.SYSTEM_TYPE.MODERATOR_DEMOTED, // 'moderator_demoted',
	MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_PROMOTED, // 'guest_moderator_promoted',
	MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_DEMOTED, // 'guest_moderator_demoted',
	MESSAGE.SYSTEM_TYPE.FILE_SHARED, // 'file_shared',
	MESSAGE.SYSTEM_TYPE.OBJECT_SHARED, // 'object_shared',
	MESSAGE.SYSTEM_TYPE.HISTORY_CLEARED, // 'history_cleared',
	MESSAGE.SYSTEM_TYPE.POLL_VOTED, // 'poll_voted',
	MESSAGE.SYSTEM_TYPE.POLL_CLOSED, // 'poll_closed',
	MESSAGE.SYSTEM_TYPE.RECORDING_STARTED, // 'recording_started',
	MESSAGE.SYSTEM_TYPE.RECORDING_STOPPED, // 'recording_stopped',
] as const

/**
 * System messages that aren't necessary to translate
 */
const SYSTEM_MESSAGE_TYPE_UNTRANSLATED = [
	MESSAGE.SYSTEM_TYPE.REACTION,
	MESSAGE.SYSTEM_TYPE.REACTION_DELETED,
	MESSAGE.SYSTEM_TYPE.REACTION_REVOKED,
	MESSAGE.SYSTEM_TYPE.MESSAGE_DELETED,
	MESSAGE.SYSTEM_TYPE.MESSAGE_EDITED,
	MESSAGE.SYSTEM_TYPE.THREAD_CREATED,
	MESSAGE.SYSTEM_TYPE.THREAD_RENAMED,
] as const

/**
 * System messages that aren't shown separately in the chat
 */
const SYSTEM_MESSAGE_TYPE_HIDDEN = [
	MESSAGE.SYSTEM_TYPE.REACTION,
	MESSAGE.SYSTEM_TYPE.REACTION_DELETED,
	MESSAGE.SYSTEM_TYPE.REACTION_REVOKED,
	MESSAGE.SYSTEM_TYPE.POLL_VOTED,
	MESSAGE.SYSTEM_TYPE.MESSAGE_DELETED,
	MESSAGE.SYSTEM_TYPE.MESSAGE_EDITED,
	MESSAGE.SYSTEM_TYPE.THREAD_CREATED,
	MESSAGE.SYSTEM_TYPE.THREAD_RENAMED,
] as const

/**
 * Returns whether the actor is CLI (Administrator)
 *
 * @param message Chat message
 */
function cliIsActor(message: ChatMessage) {
	return message.messageParameters.actor.id === ATTENDEE.ACTOR_CLI_ID
		&& message.messageParameters.actor.type === MENTION.TYPE.GUEST
}

/**
 * Returns whether the conversation is one-to-one
 *
 * @param type conversation type
 */
function conversationIsOneToOne(type: number) {
	return type === CONVERSATION.TYPE.ONE_TO_ONE
		|| type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
}

/**
 * Returns whether the call is started silently
 *
 * @param message conversation type
 */
function callIsSilent(message: ChatMessageCallStarted) {
	return message.call?.silent === true
}

/**
 * Returns correct mention type for self (when guest, id contains a prefix)
 *
 * @param actorId user id
 * @param actorType user type
 */
function selfMentionId(actorId: string, actorType: string) {
	if (actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
		return `${MENTION.TYPE.GUEST}/${actorId}`
	}
	return actorId
}

/**
 * Returns correct mention type for self ('users' -> 'user', etc.)
 *
 * @param actorType user type
 */
function selfMentionType(actorType: string) {
	return SELF_MENTION_TYPE[actorType as SelfMentionTypeKey] ?? MENTION.TYPE.GUEST
}

/**
 * Returns whether the actor is current user (You)
 *
 * @param message Chat message
 * @param selfActorId Current user id
 * @param selfActorType Current user type
 */
function selfIsActor(message: ChatMessage, selfActorId: string, selfActorType: string) {
	return message.messageParameters.actor.id === selfActorId
		&& message.messageParameters.actor.type === selfMentionType(selfActorType)
}

/**
 * Returns whether the affected user is current user (you)
 *
 * @param message Chat message
 * @param selfActorId Current user id
 * @param selfActorType Current user type
 */
function selfIsUser(message: ChatMessage, selfActorId: string, selfActorType: string) {
	return message.messageParameters.user.id === selfMentionId(selfActorId, selfActorType)
		&& message.messageParameters.user.type === selfMentionType(selfActorType)
}

/**
 * Returns whether the given system message should be hidden in the UI
 *
 * @param message Chat message
 * @return whether the message is hidden in the UI
 */
export function isHiddenSystemMessage(message: ChatMessage): boolean {
	// System message for auto unpin
	if (message.systemMessage === MESSAGE.SYSTEM_TYPE.MESSAGE_UNPINNED
		&& message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
		&& message.actorId === ATTENDEE.ACTOR_SYSTEM_ID) {
		return true
	}

	return SYSTEM_MESSAGE_TYPE_HIDDEN.includes(message.systemMessage)
}

/**
 * Returns whether the given system message should be hidden in the UI
 *
 * @param message Chat message
 * @param conversation Current conversation
 * @return whether the message is hidden in the UI
 */
export function tryLocalizeSystemMessage(message: ChatMessage, conversation: Conversation): string {
	if (SYSTEM_MESSAGE_TYPE_UNTRANSLATED.includes(message.systemMessage)) {
		// Don't localize hidden system messages, keep original
		return message.message
	}

	if (!SYSTEM_MESSAGE_TYPE_RELAY.includes(message.systemMessage)) {
		// Don't localize non-supported relayed system messages, do polling
		throw new Error()
	}

	switch (message.systemMessage) {
		case MESSAGE.SYSTEM_TYPE.CALL_STARTED: {
			if (callIsSilent(message)) {
				if (selfIsActor(message, conversation.actorId, conversation.actorType)) {
					return conversationIsOneToOne(conversation.type)
						? t('spreed', 'Outgoing silent call')
						: t('spreed', 'You started a silent call')
				} else {
					return conversationIsOneToOne(conversation.type)
						? t('spreed', 'Incoming silent call')
						: t('spreed', '{actor} started a silent call')
				}
			} else {
				if (selfIsActor(message, conversation.actorId, conversation.actorType)) {
					return conversationIsOneToOne(conversation.type)
						? t('spreed', 'Outgoing call')
						: t('spreed', 'You started a call')
				} else {
					return conversationIsOneToOne(conversation.type)
						? t('spreed', 'Incoming call')
						: t('spreed', '{actor} started a call')
				}
			}
		}
		case MESSAGE.SYSTEM_TYPE.CALL_JOINED: {
			return selfIsActor(message, conversation.actorId, conversation.actorType)
				? t('spreed', 'You joined the call')
				: t('spreed', '{actor} joined the call')
		}
		case MESSAGE.SYSTEM_TYPE.CALL_LEFT: {
			return selfIsActor(message, conversation.actorId, conversation.actorType)
				? t('spreed', 'You left the call')
				: t('spreed', '{actor} left the call')
		}
		case MESSAGE.SYSTEM_TYPE.CALL_ENDED:
		case MESSAGE.SYSTEM_TYPE.CALL_ENDED_EVERYONE: {
			// Original method #parseCall ~ 150 lines of PHP code
			// requires to know amount of guests, duration, $maxDurationWasReached
			// doesn't worth localizing on client side
			throw new Error()
		}
		case MESSAGE.SYSTEM_TYPE.MODERATOR_PROMOTED:
		case MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_PROMOTED: {
			if (selfIsActor(message, conversation.actorId, conversation.actorType)) {
				return t('spreed', 'You promoted {user} to moderator')
			} else if (selfIsUser(message, conversation.actorId, conversation.actorType)) {
				return cliIsActor(message)
					? t('spreed', 'An administrator promoted you to moderator')
					: t('spreed', '{actor} promoted you to moderator')
			}
			return cliIsActor(message)
				? t('spreed', 'An administrator promoted {user} to moderator')
				: t('spreed', '{actor} promoted {user} to moderator')
		}
		case MESSAGE.SYSTEM_TYPE.MODERATOR_DEMOTED:
		case MESSAGE.SYSTEM_TYPE.GUEST_MODERATOR_DEMOTED: {
			if (selfIsActor(message, conversation.actorId, conversation.actorType)) {
				return t('spreed', 'You demoted {user} from moderator')
			} else if (selfIsUser(message, conversation.actorId, conversation.actorType)) {
				return cliIsActor(message)
					? t('spreed', 'An administrator demoted you from moderator')
					: t('spreed', '{actor} demoted you from moderator')
			}
			return cliIsActor(message)
				? t('spreed', 'An administrator demoted {user} from moderator')
				: t('spreed', '{actor} demoted {user} from moderator')
		}
		case MESSAGE.SYSTEM_TYPE.FILE_SHARED:
		case MESSAGE.SYSTEM_TYPE.OBJECT_SHARED: {
			// Backend transforms both 'file_shared' and 'object_shared' to normal chat message,
			// these should not be received by client
			throw new Error()
		}
		case MESSAGE.SYSTEM_TYPE.HISTORY_CLEARED: {
			return selfIsActor(message, conversation.actorId, conversation.actorType)
				? t('spreed', 'You cleared the history of the conversation')
				: t('spreed', '{actor} cleared the history of the conversation')
		}
		case MESSAGE.SYSTEM_TYPE.POLL_VOTED: {
			return t('spreed', 'Someone voted on the poll {poll}')
		}
		case MESSAGE.SYSTEM_TYPE.POLL_CLOSED: {
			return selfIsActor(message, conversation.actorId, conversation.actorType)
				? t('spreed', 'You ended the poll {poll}')
				: t('spreed', '{actor} ended the poll {poll}')
		}
		case MESSAGE.SYSTEM_TYPE.RECORDING_STARTED: {
			return selfIsActor(message, conversation.actorId, conversation.actorType)
				? t('spreed', 'You started the video recording')
				: t('spreed', '{actor} started the video recording')
		}
		case MESSAGE.SYSTEM_TYPE.RECORDING_STOPPED: {
			return selfIsActor(message, conversation.actorId, conversation.actorType)
				? t('spreed', 'You stopped the video recording')
				: t('spreed', '{actor} stopped the video recording')
		}
		default: {
			// Don't localize non-supported relayed system messages, do polling
			throw new Error()
		}
	}
}
