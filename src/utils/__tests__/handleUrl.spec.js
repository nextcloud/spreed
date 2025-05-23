/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { showError, showSuccess } from '@nextcloud/dialogs'
import {
	copyConversationLinkToClipboard,
	generateAbsoluteUrl,
	generateFullConversationLink,
} from '../handleUrl.ts'

jest.mock('@nextcloud/dialogs', () => ({
	showSuccess: jest.fn(),
	showError: jest.fn(),
}))

describe('handleUrl', () => {
	describe('generateAbsoluteUrl', () => {
		it('should generate absolute url', () => {
			const output = generateAbsoluteUrl('/path/{foo}', { foo: 'bar' })
			expect(output).toBe('http://localhost/nc-webroot/path/bar')
		})

		it('should generate absolute url with specified base', () => {
			const output = generateAbsoluteUrl('/path/{foo}', { foo: 'bar' }, { baseURL: 'https://external.ltd/root' })
			expect(output).toBe('https://external.ltd/root/path/bar')
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
