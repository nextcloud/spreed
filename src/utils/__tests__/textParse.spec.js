/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { parseMentions, parseSpecialSymbols } from '../textParse.ts'

jest.mock('@nextcloud/router', () => ({
	getBaseUrl: jest.fn().mockReturnValue('server2.com')
}))

describe('textParse', () => {
	describe('parseMentions', () => {
		it('replaces {mention-call} correctly', () => {
			const input = 'test {mention-call1}'
			const output = 'test @all'
			const parameters = {
				'mention-call1': {
					id: 'room-id',
					name: 'Room Display Name',
					type: 'call',
				},
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces multiple entries correctly', () => {
			const input = 'test {mention-call1} test {mention-call1} test'
			const output = 'test @all test @all test'
			const parameters = {
				'mention-call1': {
					id: 'room-id',
					name: 'Room Display Name',
					type: 'call',
				},
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-user} correctly', () => {
			const input = 'test {mention-user1} test {mention-user2}'
			const output = 'test @alice test @"alice space@mail.com"'
			const parameters = {
				'mention-user1': {
					id: 'alice',
					name: 'Just Alice',
					type: 'user',
				},
				'mention-user2': {
					id: 'alice space@mail.com',
					name: 'Out of space Alice',
					type: 'user',
				}
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-group} correctly', () => {
			const input = 'test {mention-group1} test {mention-group2}'
			const output = 'test @"group/talk" test @"group/space talk"'
			const parameters = {
				'mention-group1': {
					id: 'talk',
					name: 'Talk Group',
					type: 'user-group',
				},
				'mention-group2': {
					id: 'space talk',
					name: 'Out of space Talk Group',
					type: 'user-group',
				}
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-federated-user} correctly (for host and other federations)', () => {
			const input = 'test {mention-federated-user1}'
			const output = 'test @"federated_user/alice@server3.com"'
			const parameters = {
				'mention-federated-user1': {
					id: 'alice',
					name: 'Feder Alice',
					type: 'user',
					server: 'server3.com'
				}
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-federated-user} correctly (for user from server2.com)', () => {
			const input = 'test {mention-federated-user1}'
			const output = 'test @"federated_user/alice@server2.com"'
			const parameters = {
				'mention-federated-user1': {
					id: 'alice',
					name: 'Feder Alice',
					type: 'user',
				}
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})
	})

	describe('parseSpecialSymbols', () => {
		it('converts escaped HTML correctly', () => {
			const input = '&lt;div&gt;Hello&amp;world&lt;/div&gt;'
			const output = '<div>Hello&world</div>'
			expect(parseSpecialSymbols(input)).toBe(output)
		})

		it('converts special characters correctly', () => {
			const input = 'This is the &sect; symbol.'
			const output = 'This is the § symbol.'
			expect(parseSpecialSymbols(input)).toBe(output)
		})

		it('removes trailing and leading whitespaces', () => {
			const input = '   Hello   '
			const output = 'Hello'
			expect(parseSpecialSymbols(input)).toBe(output)
		})

		it('removes line breaks', () => {
			const input = 'Hello\rworld\r\n!'
			const output = 'Hello\nworld\n!'
			expect(parseSpecialSymbols(input)).toBe(output)
		})

		it('returns the same text when there are no special symbols', () => {
			const input = 'Hello world!'
			const output = 'Hello world!'
			expect(parseSpecialSymbols(input)).toBe(output)
		})
	})
})
