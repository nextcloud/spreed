/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { calculateVotePercentage } from '../calculateVotePercentage.ts'

describe('calculateVotePercentage', () => {
	const tests = [
		[0, [], 0],
		[1, [1], 100],
		// Math rounded to 100%
		[4, [1, 3], 100],
		[11, [1, 2, 8], 100],
		[13, [11, 2], 100],
		[13, [9, 4], 100],
		[26, [16, 5, 5], 100],
		// Rounded to 100% by largest remainder
		[1000, [132, 494, 92, 282], 100],
		[1000, [135, 480, 97, 288], 100],
		// Best effort is 99%
		[3, [1, 1, 1], 99],
		[7, [2, 2, 3], 99],
		[1000, [133, 491, 93, 283], 99],
		[1000, [134, 488, 94, 284], 99],
		// Best effort is 98%
		[1000, [136, 482, 96, 286], 98],
		[1000, [135, 140, 345, 95, 285], 98],
		// Best effort is 97%
		[1000, [137, 132, 347, 97, 287], 97],
	]

	it.each(tests)('test %d votes in %o distribution rounds to %d%%', (total, votes, result) => {
		const percentageMap = calculateVotePercentage(votes, total)

		expect(votes.reduce((a, b) => a + b, 0)).toBe(total)
		expect(percentageMap.reduce((a, b) => a + b, 0)).toBe(result)
	})
})
