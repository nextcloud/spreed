/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { parseMentions, parseSpecialSymbols } from '../textParse.ts'

vi.mock('@nextcloud/router', () => ({
	getBaseUrl: vi.fn().mockReturnValue('https://server2.com'),
}))

describe('textParse', () => {
	describe('parseMentions', () => {
		it('replaces mentions correctly if mention-id is available', () => {
			const input = 'test {mention-call1} test {mention-user1} test {mention-group1} test {mention-federated-user1}'
			const output = 'test @"all" test @"alice" test @"group/talk" test @"federated_user/alice@server2.com"'
			const parameters = {
				'mention-call1': {
					id: 'room-id',
					name: 'Room Display Name',
					type: 'call',
					'mention-id': 'all',
				},
				'mention-user1': {
					id: 'alice',
					name: 'Just Alice',
					type: 'user',
					'mention-id': 'alice',
				},
				'mention-group1': {
					id: 'talk',
					name: 'Talk Group',
					type: 'user-group',
					'mention-id': 'group/talk',
				},
				'mention-federated-user1': {
					id: 'alice',
					name: 'Feder Alice',
					type: 'user',
					server: 'https://server2.com',
					'mention-id': 'federated_user/alice@server2.com',
				},
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

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
			const output = 'test @"alice" test @"alice space@mail.com"'
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
				},
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
				},
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-team} correctly', () => {
			const input = 'test {mention-team1} test {mention-team2}'
			const output = 'test @"team/talk" test @"team/space talk"'
			const parameters = {
				'mention-team1': {
					id: 'talk',
					name: 'Talk Group',
					type: 'circle',
				},
				'mention-team2': {
					id: 'space talk',
					name: 'Out of space Talk Group',
					type: 'team',
				},
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-guest} correctly', () => {
			const input = 'test {mention-guest1} test {mention-guest2}'
			const output = 'test @"guest/abcd" test @"guest/efgh"'
			const parameters = {
				'mention-guest1': {
					id: 'guest/abcd',
					name: 'Guest A',
					type: 'guest',
				},
				'mention-guest2': {
					id: 'guest/efgh',
					name: 'Guest E',
					type: 'guest',
				},
			}
			expect(parseMentions(input, parameters)).toBe(output)
		})

		it('replaces {mention-email} correctly', () => {
			const input = 'test {mention-email1} test {mention-email2}'
			const output = 'test @"email/abcd" test @"email/efgh"'
			const parameters = {
				'mention-email1': {
					id: 'abcd',
					name: 'Email Guest A',
					type: 'email',
				},
				'mention-email2': {
					id: 'efgh',
					name: 'Email Guest E',
					type: 'email',
				},
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
					type: 'federated_user',
					server: 'https://server3.com',
				},
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
				},
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
