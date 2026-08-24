/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { ConversationPage } from '../support/sections/ConversationPage.ts'
import { test } from '../support/fixtures/conversations.ts'

test('a reply from one user shows up for the other', async ({ createSession, createConversation }) => {
	const { user: userA, page: pageA } = await createSession()
	const { user: userB, page: pageB } = await createSession()

	// Both throwaway accounts are already invited, so neither page has to
	// drive the "new conversation" / "add participant" UI.
	const token = await createConversation('Two user chat', { user: [userA.userId, userB.userId] })

	const convA = new ConversationPage(pageA)
	const convB = new ConversationPage(pageB)
	await convA.open(token)
	await convB.open(token)

	const reply = `Hello from ${userB.userId}`
	await convB.messageForm.send(reply)

	// Scoped to the message list: the same text also appears in the left
	// sidebar's conversation preview, which would otherwise match too.
	await expect(convA.messagesList.getByText(reply)).toBeVisible()
})
