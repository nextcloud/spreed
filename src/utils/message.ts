/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { MESSAGE } from '../constants.ts'

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
 * Returns whether the given system message should be hidden in the UI
 *
 * @param message Chat message
 * @return whether the message is hidden in the UI
 */
export function isHiddenSystemMessage(message: ChatMessage): boolean {
	return SYSTEM_MESSAGE_TYPE_HIDDEN.includes(message.systemMessage)
}

/**
 * Returns whether the given system message should be hidden in the UI
 *
 * @param message Chat message
 * @return whether the message is hidden in the UI
 */
export function tryLocalizeSystemMessage(message: ChatMessage): string {
	if (isHiddenSystemMessage(message)) {
		// Don't localize hidden system messages
		return message.message
	}

	if (!SYSTEM_MESSAGE_TYPE_RELAY.includes(message.systemMessage)) {
		// Don't localize non-supported relayed system messages
		throw new Error()
	}

	// FIXME do it normal way
	return t('spreed', message.message)
}
