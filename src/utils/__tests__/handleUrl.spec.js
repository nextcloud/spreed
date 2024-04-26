/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { showError, showSuccess } from '@nextcloud/dialogs'

import {
	generateAbsoluteUrl,
	generateFullConversationLink,
	copyConversationLinkToClipboard,
} from '../handleUrl.ts'

jest.mock('@nextcloud/dialogs', () => ({
	showSuccess: jest.fn(),
	showError: jest.fn(),
}))

describe('handleUrl', () => {
	describe('generateAbsoluteUrl', () => {
		it('should generate url with IS_DESKTOP=false correctly', () => {
			const output = generateAbsoluteUrl('/path')
			expect(output).toBe('http://localhost/nc-webroot/path')
		})

		it('should generate url with IS_DESKTOP=true correctly', () => {
			const originalIsDesktop = global.IS_DESKTOP
			global.IS_DESKTOP = true

			const output = generateAbsoluteUrl('/path')
			expect(output).toBe('/nc-webroot/path')

			global.IS_DESKTOP = originalIsDesktop
		})
	})

	describe('generateFullConversationLink', () => {
		it('should generate links with given token', () => {
			const link = generateFullConversationLink('TOKEN')
			expect(link).toBe('http://localhost/nc-webroot/call/TOKEN')
		})

		it('should generate links with given token and message id', () => {
			const link = generateFullConversationLink('TOKEN', '123')
			expect(link).toBe('http://localhost/nc-webroot/call/TOKEN#message_123')
		})
	})

	describe('copyConversationLinkToClipboard', () => {
		it('should copy the conversation link and show success message', async () => {
			Object.assign(navigator, { clipboard: { writeText: jest.fn().mockResolvedValueOnce() } })

			await copyConversationLinkToClipboard('TOKEN', '123')

			expect(navigator.clipboard.writeText).toHaveBeenCalledWith('http://localhost/nc-webroot/call/TOKEN#message_123')
			expect(showSuccess).toHaveBeenCalled()
		})

		it('should show error message when copying fails', async () => {
			Object.assign(navigator, { clipboard: { writeText: jest.fn().mockRejectedValueOnce() } })

			await copyConversationLinkToClipboard('TOKEN', '123')

			expect(navigator.clipboard.writeText).toHaveBeenCalledWith('http://localhost/nc-webroot/call/TOKEN#message_123')
			expect(showError).toHaveBeenCalled()
		})
	})
})
