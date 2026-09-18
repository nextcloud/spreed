/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { setRoomEmojiAvatar } from '../support/room-updates.ts'
import { ConversationPage } from '../support/sections/ConversationPage.ts'
import { saveDocsScreenshot } from './docs-screenshot.ts'
import { test } from './fixtures/demo-users.ts'

test('docs/chat.png: a conversation with a short exchange', async ({ createDemoSession, createConversation }) => {
	const { user: userA, page: pageA } = await createDemoSession('christine')
	const { user: userB, page: pageB } = await createDemoSession('leon')

	const token = await createConversation('Project kickoff', {
		user: [userA.userId, userB.userId],
		moderator: [userA.userId],
	})
	await setRoomEmojiAvatar(pageA, token, '🚀', 'ffc107')

	const convA = new ConversationPage(pageA)
	const convB = new ConversationPage(pageB)
	await convA.open(token)
	await convB.open(token)

	await convB.messageForm.send('Sounds good, see you all at 10am!')
	await expect(convA.messagesList.getByText('Sounds good, see you all at 10am!')).toBeVisible()

	await saveDocsScreenshot(pageA, 'chat.png')
})
