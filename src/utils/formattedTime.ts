/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCanonicalLocale, getLanguage, n, t } from '@nextcloud/l10n'

const ONE_HOUR_IN_MS = 3600000
const ONE_DAY_IN_MS = 86400000

const locale = getCanonicalLocale()

const absoluteTimeFormat = {
	shortTime: new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: 'numeric' }), // '8:30 PM'
	longDate: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric' }), // 'February 15, 2025'
	longDateWithTime: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'February 15, 2025 at 8:30 PM'
	shortDate: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric' }), // 'Feb 15, 2025'
	shortDateNumeric: new Intl.DateTimeFormat(locale, { year: 'numeric', month: '2-digit', day: '2-digit' }), // '02/15/2025'
	shortDateWithTime: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Feb 15, 2025, 8:30 PM'
	shortDateWithTimeSeconds: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric' }), // 'Feb 15, 2025, 8:30:00 PM'
	shortWeekdayWithTime: new Intl.DateTimeFormat(locale, { weekday: 'short', hour: 'numeric', minute: 'numeric' }), // 'Sat 8:30 PM'
} as const

/** Formatters in user's language */

/** '1 day ago', 'in 1 day' */
const relativeTimeFormatter = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'always' })
/** 'yesterday', 'tomorrow' */
const idiomaticTimeFormatter = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'auto' })
/** 'friday', 'saturday' */
const weekdayTimeFormatter = new Intl.DateTimeFormat(getLanguage(), { weekday: 'long' })

/**
 * Converts the given time to UNIX timestamp
 *
 * @param time given time in ms or Date object
 */
function convertToUnix(time: number | Date): number {
	return Math.floor(+time / 1000)
}

/**
 * Calculates the stopwatch string given the time (ms)
 *
 * @param time the time in ms
 * @param [condensed] the format of string to show
 */
function formattedTime(time: number, condensed: boolean = false): string {
	if (!time) {
		return condensed ? '--:--' : '-- : --'
	}

	const timeInSec = convertToUnix(time)
	const seconds = timeInSec % 60
	const minutes = Math.floor(timeInSec / 60) % 60
	const hours = Math.floor(timeInSec / 3600) % 24

	return [
		hours,
		minutes.toString().padStart(2, '0'),
		seconds.toString().padStart(2, '0'),
	].filter((num) => !!num).join(condensed ? ':' : ' : ')
}

/**
 * Calculates the future relative time string given the time (ms)
 *
 * @param time the time in ms
 */
function futureRelativeTime(time: number): string {
	const diff = time - Date.now()
	// If the time is in the past, return an empty string
	if (diff <= 0) {
		return ''
	}

	const hours = Math.floor(diff / ONE_HOUR_IN_MS)
	const minutes = Math.ceil((diff - hours * ONE_HOUR_IN_MS) / (60 * 1000))
	if (hours >= 1) {
		if (minutes === 0) {
			// TRANSLATORS: hint for the time when the meeting starts (only hours)
			return n('spreed', 'In %n hour', 'In %n hours', hours)
		} else {
			// TRANSLATORS: hint for the time when the meeting starts (hours and minutes)
			return t('spreed', 'In {hours} and {minutes}', {
				hours: n('spreed', '%n hour', '%n hours', hours),
				minutes: n('spreed', '%n minute ', '%n minutes', minutes),
			})
		}
	} else {
		// TRANSLATORS: hint for the time when the meeting starts (only minutes)
		return n('spreed', 'In %n minute', 'In %n minutes', minutes)
	}
}

/**
 * Converts the given time to human-readable formats
 *
 * @param time time in ms or Date object
 * @param format format to use
 */
function formatDateTime(time: Date | number, format: keyof typeof absoluteTimeFormat): string {
	return absoluteTimeFormat[format].format(new Date(time))
}

/**
 * Calculates the difference (in days) from now (positive for future time, negative for the past)
 *
 * @param dateOrTimestamp Date object to calculate from (or timestamp in ms)
 */
function getDiffInDays(dateOrTimestamp: Date | number): number {
	const date = new Date(dateOrTimestamp)
	const currentDate = new Date()

	// drop the time part of Date objects
	date.setHours(0, 0, 0, 0)
	currentDate.setHours(0, 0, 0, 0)

	return (+date - +currentDate) / ONE_DAY_IN_MS
}

/**
 * Calculates the relative time (in days) from now in user language
 *
 * @param dateOrTimestamp Date object to calculate from (or timestamp in ms)
 * @param options function options
 * @param options.limitToWeek whether to return prefix for interval larger than a week
 * @param options.showWeekDay whether to return weekday names for -6 to +6 days range
 */
function getRelativeDay(
	dateOrTimestamp: Date | number,
	{ limitToWeek, showWeekDay } = { limitToWeek: false, showWeekDay: false },
): string {
	const date = new Date(dateOrTimestamp)
	const diffInDays = getDiffInDays(date)

	if (limitToWeek) {
		if (Math.abs(diffInDays) === 7) {
			if (showWeekDay) {
				// Do not return the same weekday as the current one
				return ''
			}
			return relativeTimeFormatter.format(diffInDays / 7, 'week')
		} else if (Math.abs(diffInDays) > 7) {
			return ''
		}
	}

	if (showWeekDay && Math.abs(diffInDays) > 1) {
		return weekdayTimeFormatter.format(date)
	}

	return idiomaticTimeFormatter.format(diffInDays, 'day')
}

export {
	convertToUnix,
	formatDateTime,
	formattedTime,
	futureRelativeTime,
	getDiffInDays,
	getRelativeDay,
	ONE_DAY_IN_MS,
	ONE_HOUR_IN_MS,
}
