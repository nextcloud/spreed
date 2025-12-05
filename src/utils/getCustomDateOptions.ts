/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { formatDateTime } from './formattedTime.ts'

type CustomDateOption = {
	key: string
	timestamp: number
	label: string
	ariaLabel: string
}

/**
 * Returns array of custom date options for reminders / schedulers
 */
export function getCustomDateOptions() {
	const currentDate = new Date()
	const currentDayOfWeek = currentDate.getDay()

	const options: CustomDateOption[] = []

	// Same day 18:00 PM (hidden if after 17:00 PM now)
	if (currentDate.getHours() < 17) {
		const laterTodayTime = new Date().setHours(18, 0, 0, 0)

		options.push({
			key: 'laterToday',
			timestamp: laterTodayTime,
			label: t('spreed', 'Later today – {timeLocale}', { timeLocale: formatDateTime(laterTodayTime, 'shortTime') }),
			ariaLabel: t('spreed', 'Set time for later today'),
		})
	}

	// Tomorrow 08:00 AM
	const nextDay = new Date()
	nextDay.setDate(currentDate.getDate() + 1)
	const tomorrowTime = nextDay.setHours(8, 0, 0, 0)

	options.push({
		key: 'tomorrow',
		timestamp: tomorrowTime,
		label: t('spreed', 'Tomorrow – {timeLocale}', { timeLocale: formatDateTime(tomorrowTime, 'shortWeekdayWithTime') }),
		ariaLabel: t('spreed', 'Set time for tomorrow'),
	})

	// Saturday 08:00 AM (hidden if Friday, Saturday or Sunday now)
	if (![0, 5, 6].includes(currentDayOfWeek)) {
		const nextSaturday = new Date()
		nextSaturday.setDate(currentDate.getDate() + ((6 + 7 - currentDayOfWeek) % 7 || 7))
		const thisWeekendTime = nextSaturday.setHours(8, 0, 0, 0)

		options.push({
			key: 'thisWeekend',
			timestamp: thisWeekendTime,
			label: t('spreed', 'This weekend – {timeLocale}', { timeLocale: formatDateTime(thisWeekendTime, 'shortWeekdayWithTime') }),
			ariaLabel: t('spreed', 'Set time for this weekend'),
		})
	}

	// Next Monday 08:00 AM (hidden if Sunday now)
	// TODO: use getFirstDay from nextcloud/l10n
	if (currentDayOfWeek !== 0) {
		const nextMonday = new Date()
		nextMonday.setDate(currentDate.getDate() + ((1 + 7 - currentDayOfWeek) % 7 || 7))
		const nextWeekTime = nextMonday.setHours(8, 0, 0, 0)

		options.push({
			key: 'nextWeek',
			timestamp: nextWeekTime,
			label: t('spreed', 'Next week – {timeLocale}', { timeLocale: formatDateTime(nextWeekTime, 'shortWeekdayWithTime') }),
			ariaLabel: t('spreed', 'Set time for next week'),
		})
	}

	return options
}
