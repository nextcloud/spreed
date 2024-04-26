/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { readableNumber, stringChop } from '../readableNumber.ts'

describe('readableNumber', () => {
	describe('stringChop', () => {
		it('should return the correct array of numbers', () => {
			const numbers = {
				1: { number: '123456789', size: 3 },
				2: { number: '12345678', size: 3 },
				3: { number: '1234567', size: 3 },
				4: { number: '123456', size: 2 },
				5: { number: '123456', size: 1 },
				6: { number: '123456', size: 0 },
				7: { number: '123456', size: 6 },
				8: { number: '123456', size: 7 },
				9: { number: '', size: 3 },
			}

			const outputTypes = {
				1: ['123', '456', '789'],
				2: ['123', '456', '78'],
				3: ['123', '456', '7'],
				4: ['12', '34', '56'],
				5: ['1', '2', '3', '4', '5', '6'],
				6: ['123456'],
				7: ['123456'],
				8: ['123456'],
				9: [''],
			}

			for (const i in numbers) {
				const output = i + ': ' + stringChop(numbers[i].number, numbers[i].size)
				expect(output).toBe(i + ': ' + outputTypes[i])
			}
		})
	})

	describe('readableNumber', () => {
		it('should return the correct readable number', () => {
			const numbers = {
				1: 123456789,
				2: '123456789',
				3: '12345678',
				4: '1234567',
				5: '',
			}

			const outputTypes = {
				1: '123 456 789',
				2: '123 456 789',
				3: '123 456 78',
				4: '123 4567',
				5: '',
			}

			for (const i in numbers) {
				const output = i + ': ' + readableNumber(numbers[i])
				expect(output).toBe(i + ': ' + outputTypes[i])
			}
		})
	})
})
