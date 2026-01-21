<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { useGetStaticDateTime } from '../../composables/useGetStaticDateTime.ts'
import { getDiffInDays } from '../../utils/formattedTime.ts'

const props = defineProps<{
	time: string | number
}>()

const datetime = useGetStaticDateTime(props.time)

const datetimeString = computed(() => {
	const diffInDays = getDiffInDays(+props.time)

	if (diffInDays >= 2 && diffInDays <= 7) {
		// TRANSLATORS: 'Scheduled <in 2 days, March 18>', 'Scheduled <in 1 week, April 24>'
		return t('spreed', 'Scheduled {datetime}', { datetime: datetime.value }, { escape: false })
	}

	// TRANSLATORS: 'Scheduled for today, March 18', 'Scheduled for April 24, 2026'
	return t('spreed', 'Scheduled for {datetime}', { datetime: datetime.value }, { escape: false })
})
</script>

<template>
	<span>{{ datetimeString }}</span>
</template>
