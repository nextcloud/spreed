/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it } from 'vitest'
import { readableNumber, stringChop } from '../readableNumber.ts'

describe('readableNumber', () => {
	describe('stringChop', () => {
		const TEST_CASES = [
			['123456789', 3, false, ['123', '456', '789']],
			['12345678', 3, false, ['123', '456', '78']],
			['1234567', 3, false, ['123', '456', '7']],
			['123456', 2, false, ['12', '34', '56']],
			['123456', 1, false, ['1', '2', '3', '4', '5', '6']],
			['123456', 0, false, ['123456']],
			['123456', 6, false, ['123456']],
			['123456', 7, false, ['123456']],
			['', 3, false, []],
			['123456789', 3, true, ['123', '456', '789']],
			['12345678', 3, true, ['12', '345', '678']],
			['1234567', 3, true, ['1', '234', '567']],
			['123456', 2, true, ['12', '34', '56']],
			['123456', 1, true, ['1', '2', '3', '4', '5', '6']],
			['123456', 0, true, ['123456']],
			['123456', 6, true, ['123456']],
			['123456', 7, true, ['123456']],
			['', 3, true, []],
		]

		it.each(TEST_CASES)(
			'should return correct array for %s with chunk size %d',
			(string, size, fromRight, output) => {
				expect(output).toMatchObject(stringChop(string, size, fromRight))
			},
		)
	})

	describe('readableNumber', () => {
		const TEST_CASES = [
			[123456789, false, '123 456 789'],
			['123456789', false, '123 456 789'],
			['12345678', false, '123 456 78'],
			['12345678', true, '12 345 678'],
			['1234567', false, '123 4567'],
			['1234567', true, '1 234 567'],
			['', false, ''],
			['', true, ''],
		]

		it.each(TEST_CASES)(
			'should return correct readable number for %s',
			(string, fromRight, output) => {
				expect(output).toBe(readableNumber(string, fromRight))
			},
		)
	})
})
