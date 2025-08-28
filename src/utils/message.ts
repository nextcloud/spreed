/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage } from '../types/index.ts'

import { MESSAGE } from '../constants.ts'

/**
 * Returns whether the given system message should be hidden in the UI
 *
 * @param message Chat message
 * @return whether the message is hidden in the UI
 */
export function isHiddenSystemMessage(message: ChatMessage): boolean {
	return [
		MESSAGE.SYSTEM_TYPE.REACTION,
		MESSAGE.SYSTEM_TYPE.REACTION_DELETED,
		MESSAGE.SYSTEM_TYPE.REACTION_REVOKED,
		MESSAGE.SYSTEM_TYPE.POLL_VOTED,
		MESSAGE.SYSTEM_TYPE.MESSAGE_DELETED,
		MESSAGE.SYSTEM_TYPE.MESSAGE_EDITED,
		MESSAGE.SYSTEM_TYPE.THREAD_CREATED,
		MESSAGE.SYSTEM_TYPE.THREAD_RENAMED,
	].includes(message.systemMessage)
}
