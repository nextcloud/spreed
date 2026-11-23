/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { CONVERSATION } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { hasUnreadDirectMentions, supportsDirectMentionsNotification } from '../conversation.ts'

vi.mock('../../services/CapabilitiesManager.ts', async (importOriginal) => ({
	...(await importOriginal()),
	hasTalkFeature: vi.fn(() => true),
}))

describe('conversation', () => {
	describe('hasUnreadDirectMentions', () => {
		const TEST_CASES = [
			[CONVERSATION.TYPE.GROUP, 1, false, true, false],
			[CONVERSATION.TYPE.GROUP, 1, true, true, true],
			[CONVERSATION.TYPE.GROUP, 1, true, false, true],
			[CONVERSATION.TYPE.ONE_TO_ONE, 1, false, false, true],
			[CONVERSATION.TYPE.ONE_TO_ONE, 0, false, false, false],
		]

		it.each(TEST_CASES)('returns correct value for type %s with %s unread, direct mention %s and mention %s', (type, unreadMessages, unreadMentionDirect, unreadMention, expected) => {
			expect(hasUnreadDirectMentions({ type, unreadMessages, unreadMentionDirect, unreadMention })).toBe(expected)
		})
	})

	describe('supportsDirectMentionsNotification', () => {
		const TEST_CASES = [
			[CONVERSATION.TYPE.GROUP, true, true],
			[CONVERSATION.TYPE.PUBLIC, true, true],
			[CONVERSATION.TYPE.ONE_TO_ONE, true, false],
			[CONVERSATION.TYPE.ONE_TO_ONE_FORMER, true, false],
			[CONVERSATION.TYPE.GROUP, false, false],
		]

		it.each(TEST_CASES)('returns correct value for type %s with capability %s', (type, hasFeature, expected) => {
			hasTalkFeature.mockReturnValue(hasFeature)
			expect(supportsDirectMentionsNotification({ token: 'token', type })).toBe(expected)
		})
	})
})
