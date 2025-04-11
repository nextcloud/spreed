/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t, n, getCanonicalLocale } from '@nextcloud/l10n'

const ONE_HOUR_IN_MS = 3600000
const ONE_DAY_IN_MS = 86400000

const locale = getCanonicalLocale()
const absoluteTimeFormat = {
	LT: new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: 'numeric' }), // '8:30 PM'
	LTS: new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: 'numeric', second: 'numeric' }), // '8:30:00 PM'
	L: new Intl.DateTimeFormat(locale, { year: 'numeric', month: '2-digit', day: '2-digit' }), // '02/15/2025'
	l: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'numeric', day: 'numeric' }), // '2/15/2025'
	LL: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric' }), // 'February 15, 2025'
	ll: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric' }), // 'Feb 15, 2025'
	LLL: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'February 15, 2025 at 8:30 PM'
	lll: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Feb 15, 2025, 8:30 PM'
	LLLL: new Intl.DateTimeFormat(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Saturday, February 15, 2025 at 8:30 PM'
	llll: new Intl.DateTimeFormat(locale, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Sat, Feb 15, 2025, 8:30 PM'
	ddd: new Intl.DateTimeFormat(locale, { weekday: 'short' }), // 'Sat'
	dddd: new Intl.DateTimeFormat(locale, { weekday: 'long' }), // 'Saturday'
	MMM: new Intl.DateTimeFormat(locale, { month: 'short' }), // 'Feb'
	MMMM: new Intl.DateTimeFormat(locale, { month: 'long' }), // 'February'
	// Locale formatters with omitted year
	Lo: new Intl.DateTimeFormat(locale, { month: '2-digit', day: '2-digit' }), // '02/15/2025'
	lo: new Intl.DateTimeFormat(locale, { month: 'numeric', day: 'numeric' }), // '2/15/2025'
	LLo: new Intl.DateTimeFormat(locale, { month: 'long', day: 'numeric' }), // 'February 15, 2025'
	llo: new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric' }), // 'Feb 15, 2025'
	LLLo: new Intl.DateTimeFormat(locale, { month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'February 15, 2025 at 8:30 PM'
	lllo: new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Feb 15, 2025, 8:30 PM'
	LLLLo: new Intl.DateTimeFormat(locale, { weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Saturday, February 15, 2025 at 8:30 PM'
	llllo: new Intl.DateTimeFormat(locale, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }), // 'Sat, Feb 15, 2025, 8:30 PM'
} as const
const availableFormats = Object.keys(absoluteTimeFormat) as Array<keyof typeof absoluteTimeFormat>

const relativeTimeFormat = {
	numeric: new Intl.RelativeTimeFormat(locale, { numeric: 'always' }), // 'in 1 day'
	auto: new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }), // 'tomorrow'
} as const

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
 * @param [condensed=false] the format of string to show
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
	].filter(num => !!num).join(condensed ? ':' : ' : ')
}

/**
 * Calculates the future relative time string given the time (ms)
 *
 * @param time the time in ms
 */
function futureRelativeTime(time: number): string {
	const diff = time - Date.now()
	const hours = Math.floor(diff / ONE_HOUR_IN_MS)
	const minutes = Math.floor((diff - hours * ONE_HOUR_IN_MS) / (60 * 1000))
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
 * Converts the given time to human-readable formats. Supported formats:
 * - combination of datetime numeric representations, like 'YYYYMMDD_HHmmss'
 * - localized formats aligned with moment.js for easier migration: https://momentjs.com/docs/#/displaying/format/
 * @see {@link absoluteTimeFormat}
 * @param time time in ms or Date object
 * @param format format to use
 */
function formatDateTime(time: Date | number, format: string): string {
	const dateTime = new Date(time)

	return format
		.split(' ')
		.map(part => availableFormats.includes(part)
			? absoluteTimeFormat[part as keyof typeof absoluteTimeFormat].format(dateTime)
			: part
		)
		.join(' ')
		.replace('DD', dateTime.getDate().toString().padStart(2, '0'))
		.replace('MM', (dateTime.getMonth() + 1).toString().padStart(2, '0'))
		.replace('YYYY', dateTime.getFullYear().toString())
		.replace('YY', dateTime.getFullYear().toString().slice(-2))
		.replace('HH', dateTime.getHours().toString().padStart(2, '0'))
		.replace('mm', dateTime.getMinutes().toString().padStart(2, '0'))
		.replace('ss', dateTime.getSeconds().toString().padStart(2, '0'))
}

type FormatRelativeOptions = {
	from?: number,
	weekPrefix?: 'weekday' | 'numeric',
	weekSuffix?: keyof typeof absoluteTimeFormat,
	suffix?: keyof typeof absoluteTimeFormat,
	omitSameYear?: boolean,
}

/**
 * Converts the given relative time to human-readable formats. Return '{relativeDate}, {absoluteDate}', where:
 * - relativeDate: numeric - <3 days ago>, <today>, <in 3 days>; or weekday - <Friday>
 * - absoluteDate: any requested absolute format
 * @see {@link formatDateTime}
 * @param time event start time in ms
 * @param options relative time formatting options
 * @param options.from timestamp to count from in ms (current time by default)
 * @param options.weekPrefix appearance of prefix: 'weekday' - Monday | 'numeric' - in 3 days (default)
 * @param options.weekSuffix appearance of suffix for timestamps < 7 days (LT by default)
 * @param options.suffix appearance of suffix for timestamps >= 7 days (LL by default)
 * @param options.omitSameYear omit year part if date to count from has the same year
 */
function formatRelativeTime(time: number, { from = Date.now(), weekPrefix = 'numeric', weekSuffix = 'LT', suffix = 'LL', omitSameYear = false }: FormatRelativeOptions = {}) {
	const daysDiff = Math.floor((new Date(time).setHours(0, 0, 0, 0) - new Date(from).setHours(0, 0, 0, 0)) / ONE_DAY_IN_MS)

	// if omitSameYear is passed, use another Intl formatter
	if (omitSameYear && new Date(time).getFullYear() === new Date(from).getFullYear()) {
		weekSuffix += weekSuffix.match(/\b(L{1,4}|l{1,4})\b/g) ? 'o' : ''
		suffix += suffix.match(/\b(L{1,4}|l{1,4})\b/g) ? 'o' : ''
	}

	switch (daysDiff) {
	case -1:
	case 0:
	case 1:
		// TRANSLATORS: <yesterday, March 18th, 2024> or <in 3 days, 4:30 PM>
		return t('spreed', '{relativeDate}, {absoluteDate}', {
			relativeDate: relativeTimeFormat.auto.format(daysDiff, 'day'),
			absoluteDate: formatDateTime(time, weekSuffix),
		}, undefined, { escape: false })
	case -6:
	case -5:
	case -4:
	case -3:
	case -2:
	case 2:
	case 3:
	case 4:
	case 5:
	case 6:
		return t('spreed', '{relativeDate}, {absoluteDate}', {
			relativeDate: weekPrefix === 'weekday'
				? formatDateTime(time, 'dddd')
				: relativeTimeFormat.numeric.format(daysDiff, 'day'),
			absoluteDate: formatDateTime(time, weekSuffix),
		}, undefined, { escape: false })
	default:
		return formatDateTime(time, suffix)
	}
}

export {
	ONE_HOUR_IN_MS,
	ONE_DAY_IN_MS,
	convertToUnix,
	formatDateTime,
	formatRelativeTime,
	formattedTime,
	futureRelativeTime,
}
