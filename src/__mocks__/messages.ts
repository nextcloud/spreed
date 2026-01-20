/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage } from '../types/index.ts'

import { t } from '@nextcloud/l10n'

export const mockedChatMessages: Record<string, ChatMessage> = {
	appearance1: {
		id: 1,
		token: '',
		actorType: 'deleted_users',
		actorId: 'deleted_users',
		// TRANSLATORS fake user to show chat appearance in settings
		actorDisplayName: t('spreed', 'Another user'),
		timestamp: 1768826595,
		// TRANSLATORS fake message to show chat appearance in settings
		message: t('spreed', 'Hey! Are you using Talk in list style or with message bubbles?'),
		messageParameters: {},
		systemMessage: '',
		messageType: 'comment',
		isReplyable: false,
		referenceId: '',
		reactions: {},
		expirationTimestamp: 0,
		markdown: true,
		threadId: 1,
	},
	appearance2: {
		id: 2,
		token: '',
		actorType: 'deleted_users',
		actorId: 'deleted_users',
		actorDisplayName: t('spreed', 'Another user'),
		timestamp: 1768826627,
		message: t('spreed', 'I picked list style'),
		messageParameters: {},
		systemMessage: '',
		messageType: 'comment',
		isReplyable: false,
		referenceId: '',
		reactions: {},
		expirationTimestamp: 0,
		markdown: true,
		threadId: 2,
	},
}
