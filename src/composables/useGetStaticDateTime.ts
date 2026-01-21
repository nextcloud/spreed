/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, MaybeRef } from 'vue'

import { getCanonicalLocale, getLanguage, t } from '@nextcloud/l10n'
import { useFormatTime } from '@nextcloud/vue/composables/useFormatDateTime'
import { computed, toValue } from 'vue'
import { getDiffInDays, getRelativeDay } from '../utils/formattedTime.ts'

/**
 *
 * @param time
 */
function isValidDate(time: string | number): boolean {
	return !isNaN(new Date(time).valueOf())
}

/**
 * Composable to calculate static date/time strings (e.g. for date separators)
 *
 * @param time
 * @param calendar
 */
export function useGetStaticDateTime(time: MaybeRef<string | number>, calendar: MaybeRef<boolean> = false): ComputedRef<string> {
	/**
	 * Prepare the options for absolute date Intl formatting
	 */
	const absoluteDateOptions = computed(() => {
		const date = new Date(+toValue(time))
		const isSameYear = date.getFullYear() === new Date().getFullYear()
		const diffInDays = getDiffInDays(date)

		const locale = toValue(calendar) ? getCanonicalLocale() : getLanguage()
		const format: Intl.DateTimeFormatOptions = {
			dateStyle: undefined,
			timeStyle: undefined,
		}

		if (toValue(calendar) && Math.abs(diffInDays) <= 6) {
			// Show weekday and time for nearest 6 days
			format.hour = 'numeric'
			format.minute = 'numeric'
		} else {
			format.year = !isSameYear ? 'numeric' : undefined
			format.month = 'long'
			format.day = 'numeric'
		}

		return { locale, format }
	})

	const absoluteDate = useFormatTime(+toValue(time), absoluteDateOptions)

	/**
	 * Generate the date header between the messages, like "today, November 11", "3 days ago, November 8", "November 5, 2024"
	 */
	return computed(() => {
		if (!isValidDate(toValue(time))) {
			// Custom string, pass as-is
			return String(toValue(time))
		}

		const relativeDate = getRelativeDay(+toValue(time), { limitToWeek: true, showWeekDay: toValue(calendar) })

		if (relativeDate) {
			// TRANSLATORS: <Today>, <March 18, 2024>
			return t('spreed', '{relativeDate}, {absoluteDate}', { relativeDate, absoluteDate: absoluteDate.value }, {
				escape: false, // French "Today" has a `'` in it
			})
		} else {
			return absoluteDate.value
		}
	})
}
