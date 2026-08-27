/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

/**
 * The message composer at the bottom of a conversation.
 */
export class NewMessage {

	constructor(private readonly page: Page) {
	}

	private get input() {
		return this.page.locator('form.new-message-form .rich-contenteditable__input')
	}

	private get sendButton() {
		return this.page.getByRole('button', { name: 'Send message', exact: true })
	}

	async send(text: string) {
		await this.input.fill(text)
		await this.sendButton.click()
	}

}
