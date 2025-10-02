<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { getCanonicalLocale, getLanguage, t } from '@nextcloud/l10n'
import { useFormatTime } from '@nextcloud/vue/composables/useFormatDateTime'
import { computed } from 'vue'
import { getDiffInDays, getRelativeDay } from '../../utils/formattedTime.ts'

const props = withDefaults(defineProps<{
	time: string | number
	calendar?: boolean
}>(), {
	calendar: false,
})

const isValidDate = computed(() => !isNaN(new Date(props.time).valueOf()))

const absoluteDateOptions = computed(() => {
	const date = new Date(+props.time)
	const isSameYear = date.getFullYear() === new Date().getFullYear()
	const diffInDays = getDiffInDays(date)

	const locale = props.calendar ? getCanonicalLocale() : getLanguage()
	const format: Intl.DateTimeFormatOptions = {
		dateStyle: undefined,
		timeStyle: undefined,
	}

	if (props.calendar && Math.abs(diffInDays) <= 6) {
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
const absoluteDate = useFormatTime(+props.time, absoluteDateOptions)

/**
 * Generate the date header between the messages, like "Today, November 11", "3 days ago, November 8", "November 5, 2024"
 */
const datetimeString = computed(() => {
	if (!isValidDate.value) {
		// Custom string, pass as-is
		return props.time
	}

	const relativeDate = getRelativeDay(+props.time, { limitToWeek: true, showWeekDay: props.calendar })

	if (relativeDate) {
		// TRANSLATORS: <Today>, <March 18, 2024>
		return t('spreed', '{relativeDate}, {absoluteDate}', { relativeDate, absoluteDate: absoluteDate.value }, {
			escape: false, // French "Today" has a `'` in it
		})
	} else {
		return absoluteDate.value
	}
})

</script>

<template>
	<span class="static-datetime">
		{{ datetimeString }}
	</span>
</template>

<style lang="scss" scoped>
.static-datetime {
	&::first-letter {
		text-transform: capitalize;
	}
}
</style>
