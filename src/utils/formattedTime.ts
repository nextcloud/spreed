/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getLanguage, n, t } from '@nextcloud/l10n'

const ONE_HOUR_IN_MS = 3600000
const ONE_DAY_IN_MS = 86400000

/** Formatters in user's language */

/** '1 day ago', 'in 1 day' */
const relativeTimeFormatter = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'always' })
/** 'yesterday', 'tomorrow' */
const idiomaticTimeFormatter = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'auto' })

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
 * Calculates the relative time (in days) from now in user language
 *
 * @param dateOrTimestamp Date object to calculate from (or timestamp in ms)
 * @param options function options
 * @param options.limitToWeek whether to return prefix for interval larger than a week
 */
function getRelativeDay(
	dateOrTimestamp: Date | number,
	{ limitToWeek } = { limitToWeek: false },
): string {
	const date = new Date(dateOrTimestamp)
	const currentDate = new Date()

	// drop the time part of Date objects
	date.setHours(0, 0, 0, 0)
	currentDate.setHours(0, 0, 0, 0)

	const diffInDays = (+date - +currentDate) / ONE_DAY_IN_MS

	if (limitToWeek) {
		if (Math.abs(diffInDays) === 7) {
			return relativeTimeFormatter.format(diffInDays / 7, 'week')
		} else if (Math.abs(diffInDays) > 7) {
			return ''
		}
	}

	return idiomaticTimeFormatter.format(diffInDays, 'day')
}

export {
	convertToUnix,
	formattedTime,
	futureRelativeTime,
	getRelativeDay,
	ONE_DAY_IN_MS,
	ONE_HOUR_IN_MS,
}
