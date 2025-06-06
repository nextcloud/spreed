<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { getFormattedDate, getRelativeDay } from '../../utils/formattedTime.ts'

const props = defineProps<{
	time: string | number
}>()

const datetimeString = computed(() => {
	return getDateTimeWithCommaDelimiter(+props.time)
})

/**
 * Generate the date header between the messages
 *
 * @param dateTimestamp The day and year timestamp (in ms)
 * @return Translated string like "Today, November 11", "3 days ago, November 8", "November 5, 2024"
 */
function getDateTimeWithCommaDelimiter(dateTimestamp: number): string {
	const date = new Date(dateTimestamp)
	const relativeDate = getRelativeDay(date, { limitToWeek: true })
	const absoluteDate = getFormattedDate(date, { showSameYear: false })

	if (relativeDate) {
		// TRANSLATORS: <Today>, <March 18, 2024>
		return t('spreed', '{relativeDate}, {absoluteDate}', { relativeDate, absoluteDate }, {
			escape: false, // French "Today" has a `'` in it
		})
	} else {
		return absoluteDate
	}
}

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
