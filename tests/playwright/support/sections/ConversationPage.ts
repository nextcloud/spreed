/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import { NewMessage } from './NewMessage.ts'

/**
 * A single conversation's chat view, opened by room token.
 */
export class ConversationPage {

	readonly messageForm: NewMessage

	constructor(private readonly page: Page) {
		this.messageForm = new NewMessage(page)
	}

	get messagesList() {
		return this.page.getByLabel('Conversation messages')
	}

	async open(token: string) {
		await this.page.goto(`/index.php/call/${token}`)
	}

}
