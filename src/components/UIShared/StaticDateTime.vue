<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { useGetStaticDateTime } from '../../composables/useGetStaticDateTime.ts'

const props = withDefaults(defineProps<{
	time: string | number
	calendar?: boolean
}>(), {
	calendar: false,
})

const datetimeString = useGetStaticDateTime(props.time, props.calendar)
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
