/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../../support/fixtures/users.ts'
import { createRoomViaApi, setRoomDescription, setRoomEmojiAvatar, setRoomName } from '../../support/room-updates.ts'
import { ConversationPage } from '../../support/sections/ConversationPage.ts'

test('a moderator can create a room and set its avatar, name and description', async ({ createSession }) => {
	const { page } = await createSession()

	// The creator is the room's owner, i.e. already a moderator — no need to
	// promote anyone before the calls below.
	const room = await createRoomViaApi(page, 'New room')
	const token = room.token

	// Visual confirmation: the header reflects the new name and avatar, and
	// the chat itself narrates every change via system messages.
	const conv = new ConversationPage(page)
	await conv.open(token)

	await expect(conv.messagesList.getByText('You created the conversation')).toBeVisible()

	const after = await setRoomEmojiAvatar(page, token, '🎉', '00ff00')
	expect(after.isCustomAvatar).toBe(true)

	const nameResult = await setRoomName(page, token, 'Emoji avatar room')
	expect(nameResult.name).toBe('Emoji avatar room')

	const descriptionResult = await setRoomDescription(page, token, 'A short description')
	expect(descriptionResult.description).toBe('A short description')

	await expect(page.locator('.conversation-header .title')).toHaveText('Emoji avatar room')
	await expect(page.locator('.top-bar__icon-wrapper img.avatar')).toHaveAttribute('src', new RegExp(`[?&]v=${after.avatarVersion}(&|$)`))

	await expect(conv.messagesList.getByText('You set the conversation picture').first()).toBeVisible()
	await expect(conv.messagesList.getByText('You renamed the conversation from "New room" to "Emoji avatar room"')).toBeVisible()
	await expect(conv.messagesList.getByText('You set the description')).toBeVisible()
})
