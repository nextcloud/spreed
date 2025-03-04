/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { t, n, getCanonicalLocale } from '@nextcloud/l10n'

const ONE_HOUR_IN_MS = 3600000
const ONE_DAY_IN_MS = 86400000

const locale = getCanonicalLocale()
const absoluteTimeFormat = {
	LT: new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: 'numeric' }),
	LTS: new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: 'numeric', second: 'numeric' }),
	L: new Intl.DateTimeFormat(locale, { year: 'numeric', month: '2-digit', day: '2-digit' }),
	l: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'numeric', day: 'numeric' }),
	LL: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric' }),
	ll: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric' }),
	LLL: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }),
	lll: new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }),
	LLLL: new Intl.DateTimeFormat(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric' }),
	llll: new Intl.DateTimeFormat(locale, { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' }),
	ddd: new Intl.DateTimeFormat(locale, { weekday: 'short' }),
	dddd: new Intl.DateTimeFormat(locale, { weekday: 'long' }),
	MMM: new Intl.DateTimeFormat(locale, { month: 'short' }),
	MMMM: new Intl.DateTimeFormat(locale, { month: 'long' }),
} as const
const availableFormats = Object.keys(absoluteTimeFormat) as Array<keyof typeof absoluteTimeFormat>

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

export {
	ONE_HOUR_IN_MS,
	ONE_DAY_IN_MS,
	convertToUnix,
	formatDateTime,
	formattedTime,
	futureRelativeTime,
}
