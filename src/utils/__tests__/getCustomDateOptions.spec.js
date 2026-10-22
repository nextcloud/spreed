/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { getCustomDateOptions } from '../getCustomDateOptions.ts'

describe('getCustomDateOptions', () => {
	afterEach(() => {
		vi.useRealTimers()
	})

	// 2026-10-19 is a Monday
	const tests = [
		['Monday', new Date(2026, 9, 19, 10), ['laterToday', 'tomorrow', 'thisWeekend', 'nextWeek']],
		['Thursday', new Date(2026, 9, 22, 18), ['tomorrow', 'thisWeekend', 'nextWeek']],
		['Friday', new Date(2026, 9, 23, 10), ['laterToday', 'tomorrow', 'nextWeek']],
		['Saturday', new Date(2026, 9, 24, 10), ['laterToday', 'tomorrow', 'nextWeek', 'nextWeekend']],
		['Sunday', new Date(2026, 9, 25, 10), ['laterToday', 'tomorrow', 'nextWeekend']],
	]

	it.each(tests)('returns expected options on %s', (_, now, expected) => {
		vi.useFakeTimers()
		vi.setSystemTime(now)
		expect(getCustomDateOptions().map((option) => option.key)).toEqual(expected)
	})

	it.each([
		['Saturday', new Date(2026, 9, 24, 10)],
		['Sunday', new Date(2026, 9, 25, 10)],
	])('schedules next weekend for the following Saturday 08:00 on %s', (_, now) => {
		vi.useFakeTimers()
		vi.setSystemTime(now)
		const option = getCustomDateOptions().find((option) => option.key === 'nextWeekend')
		expect(option.timestamp).toBe(new Date(2026, 9, 31, 8).getTime())
	})
})
