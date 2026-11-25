/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { CONVERSATION } from '../../constants.ts'
import { filterConversation, hasUnreadMentionsOrImportant } from '../conversation.ts'

const base = { type: CONVERSATION.TYPE.GROUP, unreadMessages: 0, unreadMention: false, unreadMentionDirect: false, isImportant: false }

describe('filterConversation', () => {
	it('shows conversations with unread mentions or unread messages in important conversations for mentions filter', () => {
		expect(filterConversation({ ...base, unreadMessages: 1, unreadMention: true }, ['mentions'])).toBe(true)
		expect(filterConversation({ ...base, unreadMessages: 1, isImportant: true }, ['mentions'])).toBe(true)
		expect(filterConversation({ ...base, unreadMessages: 1 }, ['mentions'])).toBe(false)
		expect(filterConversation({ ...base, isImportant: true }, ['mentions'])).toBe(false)
	})
})

describe('hasUnreadMentionsOrImportant', () => {
	it('is true for unread mentions and for unread messages in important conversations', () => {
		expect(hasUnreadMentionsOrImportant({ ...base, unreadMessages: 1, unreadMentionDirect: true })).toBe(true)
		expect(hasUnreadMentionsOrImportant({ ...base, unreadMessages: 1, isImportant: true })).toBe(true)
		expect(hasUnreadMentionsOrImportant({ ...base, unreadMessages: 1 })).toBe(false)
		expect(hasUnreadMentionsOrImportant({ ...base, isImportant: true })).toBe(false)
	})
})
