/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import {
	convertToUnix,
	formatDateTime,
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
		['ll LTS', 'Feb 15, 2025 8:30:00 PM'], // 'MMM D YYYY LTS'
		['ddd LT', 'Sat 8:30 PM'], // 'ddd LT'
		['DD-MM-YYYY HH:mm:ss', '15-02-2025 20:30:00'], // as passed
	]

	it.each(LOCALIZED_TEST_CASES)('should return datetime with specified format %s', (format, output) => {
		const result = formatDateTime(TIME, format)
		expect(result).toBe(output)
	})
})
