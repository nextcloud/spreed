/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	convertToUnix,
	formatDateTime,
	formatRelativeTime,
	formattedTime,
	futureRelativeTime,
} from '../formattedTime.ts'

const TIME = (61 * 60 + 5) * 1000 // 1 hour, 1 minute, 5 seconds in ms

describe('convertToUnix', () => {
	it('should return the correct UNIX timestamp for given time in ms', () => {
		expect(convertToUnix(1704067200269)).toBe(1704067200)
	})
	it('should return the correct UNIX timestamp for given Date object', () => {
		expect(convertToUnix(new Date('2024-01-01T00:00:00Z'))).toBe(1704067200)
	})
})

describe('formattedTime', () => {
	it('should return the formatted time with optional spacing and padded minutes / seconds', () => {
		const result = formattedTime(TIME)
		expect(result).toBe('1 : 01 : 05')
		const resultCondensed = formattedTime(TIME, true)
		expect(resultCondensed).toBe('1:01:05')
	})

	it('should return fallback string when time value is falsy', () => {
		const result = formattedTime(0)
		expect(result).toBe('-- : --')
		const resultCondensed = formattedTime(0, true)
		expect(resultCondensed).toBe('--:--')
	})
})

describe('futureRelativeTime', () => {
	beforeEach(() => {
		jest.useFakeTimers().setSystemTime(new Date('2024-01-01T00:00:00Z'))
	})

	afterEach(() => {
		jest.useRealTimers()
	})

	it('should return the correct string for time in hours', () => {
		const timeInFuture = Date.now() + (2 * 60 * 60 * 1000) // 2 hours from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 2 hours')
	})

	it('should return the correct string for time in minutes', () => {
		const timeInFuture = Date.now() + (30 * 60 * 1000) // 30 minutes from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 30 minutes')
	})

	it('should return the correct string for time in hours and minutes', () => {
		const timeInFuture = Date.now() + (2 * 60 * 60 * 1000) + (15 * 60 * 1000) // 2 hours and 15 minutes from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 2 hours and 15 minutes')
	})

	it('should return the correct string for 1 hour and minutes', () => {
		const timeInFuture = Date.now() + (60 * 60 * 1000) + (15 * 60 * 1000) // 1 hour and 15 minutes from now
		const result = futureRelativeTime(timeInFuture)
		expect(result).toBe('In 1 hour and 15 minutes')
	})
})

describe('formatDateTime', () => {
	const TIME = new Date('2025-02-15T20:30:00Z')

	const LOCALIZED_TEST_CASES = [
		['LT', '8:30 PM'], // 'h:mm A'
		['LTS', '8:30:00 PM'], // 'h:mm:ss A'
		['L', '02/15/2025'], // 'MM/DD/YYYY'
		['l', '2/15/2025'], // 'M/D/YYYY'
		['LL', 'February 15, 2025'], // 'MMMM Do YYYY'
		['ll', 'Feb 15, 2025'], // 'MMM D YYYY'
		['LLL', 'February 15, 2025 at 8:30 PM'], // 'MMMM Do YYYY LT'
		['lll', 'Feb 15, 2025, 8:30 PM'], // 'MMM D YYYY LT'
		['LLLL', 'Saturday, February 15, 2025 at 8:30 PM'], // 'dddd, MMMM Do YYYY LT'
		['llll', 'Sat, Feb 15, 2025, 8:30 PM'], // 'ddd, MMM D YYYY L'
		['Lo', '02/15'], // 'MM/DD'
		['lo', '2/15'], // 'M/D'
		['LLo', 'February 15'], // 'MMMM Do'
		['llo', 'Feb 15'], // 'MMM D'
		['LLLo', 'February 15 at 8:30 PM'], // 'MMMM Do LT'
		['lllo', 'Feb 15, 8:30 PM'], // 'MMM D LT'
		['LLLLo', 'Saturday, February 15 at 8:30 PM'], // 'dddd, MMMM Do LT'
		['llllo', 'Sat, Feb 15, 8:30 PM'], // 'ddd, MMM D L'
		['ll LTS', 'Feb 15, 2025 8:30:00 PM'], // 'MMM D YYYY LTS'
		['ddd LT', 'Sat 8:30 PM'], // 'ddd LT'
		['DD-MM-YYYY HH:mm:ss', '15-02-2025 20:30:00'], // as passed
	]

	it.each(LOCALIZED_TEST_CASES)('should return datetime with specified format %s', (format, output) => {
		const result = formatDateTime(TIME, format)
		expect(result).toBe(output)
	})
})

describe('formatRelativeTime', () => {
	const TIME = new Date('2025-02-15T20:30:00Z')

	const RELATIVE_TEST_CASES = [
		['-365', new Date('2024-02-15T20:30:00Z'), 'February 15, 2024', 'February 15, 2024'],
		['-7', new Date('2025-02-08T20:30:00Z'), 'February 8', 'February 8, 2025'],
		['-6', new Date('2025-02-09T20:30:00Z'), '6 days ago, February 9', 'Sunday, 8:30 PM'],
		['-5', new Date('2025-02-10T20:30:00Z'), '5 days ago, February 10', 'Monday, 8:30 PM'],
		['-4', new Date('2025-02-11T20:30:00Z'), '4 days ago, February 11', 'Tuesday, 8:30 PM'],
		['-3', new Date('2025-02-12T20:30:00Z'), '3 days ago, February 12', 'Wednesday, 8:30 PM'],
		['-2', new Date('2025-02-13T20:30:00Z'), '2 days ago, February 13', 'Thursday, 8:30 PM'],
		['-1', new Date('2025-02-14T20:30:00Z'), 'yesterday, February 14', 'yesterday, 8:30 PM'],
		['0', new Date('2025-02-15T20:30:00Z'), 'today, February 15', 'today, 8:30 PM'],
		['1', new Date('2025-02-16T20:30:00Z'), 'tomorrow, February 16', 'tomorrow, 8:30 PM'],
		['2', new Date('2025-02-17T20:30:00Z'), 'in 2 days, February 17', 'Monday, 8:30 PM'],
		['3', new Date('2025-02-18T20:30:00Z'), 'in 3 days, February 18', 'Tuesday, 8:30 PM'],
		['4', new Date('2025-02-19T20:30:00Z'), 'in 4 days, February 19', 'Wednesday, 8:30 PM'],
		['5', new Date('2025-02-20T20:30:00Z'), 'in 5 days, February 20', 'Thursday, 8:30 PM'],
		['6', new Date('2025-02-21T20:30:00Z'), 'in 6 days, February 21', 'Friday, 8:30 PM'],
		['7', new Date('2025-02-22T20:30:00Z'), 'February 22', 'February 22, 2025'],
	]

	it.each(RELATIVE_TEST_CASES)('should return datetime with specified format %s days from the base', (diffDays, time, numericOutput, weekdayOutput) => {
		const numericResult = formatRelativeTime(+time, { from: TIME, weekPrefix: 'numeric', weekSuffix: 'LL', omitSameYear: true })
		const weekdayResult = formatRelativeTime(+time, { from: TIME, weekPrefix: 'weekday', weekSuffix: 'LT' })
		expect(numericResult).toBe(numericOutput)
		expect(weekdayResult).toBe(weekdayOutput)
	})
})
