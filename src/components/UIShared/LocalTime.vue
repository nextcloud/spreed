<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'

import { t, getCanonicalLocale } from '@nextcloud/l10n'

import { useCurrentTime } from '../../composables/useCurrentTime.ts'

const props = defineProps<{
	timezone: string
}>()

const currentTime = useCurrentTime()

const time = computed(() => t('spreed', 'Local time: {time}', {
	time: Intl.DateTimeFormat(getCanonicalLocale(), {
		timeZone: props.timezone,
		hour: '2-digit',
		minute: '2-digit',
	}).format(currentTime.value),
}))
</script>

<template>
	<span>
		<slot name="icon" />
		{{ time }}
	</span>
</template>
