<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { getLanguage, t } from '@nextcloud/l10n'
import { useFormatTime } from '@nextcloud/vue/composables/useFormatDateTime'
import { computed } from 'vue'
import { getRelativeDay } from '../../utils/formattedTime.ts'

const props = defineProps<{
	time: string | number
}>()

const absoluteDateOptions = computed(() => {
	const isSameYear = new Date(+props.time).getFullYear() === new Date().getFullYear()
	const format: Intl.DateTimeFormatOptions = {
		dateStyle: undefined,
		year: !isSameYear ? 'numeric' : undefined,
		month: 'long',
		day: 'numeric',
	}
	return { locale: getLanguage(), format }
})
const absoluteDate = useFormatTime(+props.time, absoluteDateOptions)

/**
 * Generate the date header between the messages, like "Today, November 11", "3 days ago, November 8", "November 5, 2024"
 */
const datetimeString = computed(() => {
	const relativeDate = getRelativeDay(+props.time, { limitToWeek: true })

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
