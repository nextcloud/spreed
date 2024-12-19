<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onBeforeMount, ref } from 'vue'

import IconCalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import IconCalendarRefresh from 'vue-material-design-icons/CalendarRefresh.vue'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import usernameToColor from '@nextcloud/vue/dist/Functions/usernameToColor.js'

import { useGroupwareStore } from '../stores/groupware.ts'
import { UpcomingEvent } from '../types/index.ts'

type UpcomingEventFormatted = UpcomingEvent & {
	startTimeLocalized: string,
	style: string,
}

const props = defineProps<{
	token: string,
	container?: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const groupwareStore = useGroupwareStore()

const loading = ref(groupwareStore.calendars.length === 0)

const calendars = computed(() => groupwareStore.calendars)
const upcomingEvents = computed<Record<string, UpcomingEventFormatted[]>>(() => {
	const events = {}

	groupwareStore.getAllEvents(props.token)
		?.sort((a, b) => a.start - b.start)
		.map(event => ({
			...event,
			startTimeLocalized: moment(event.start * 1000).format('LT'),
			dateLocalized: moment(event.start * 1000).format('LL, dddd'),
			style: `background-color: ${calendars.value[event.calendarUri]?.color ?? usernameToColor(event.calendarUri).color}`,
		}))
		.forEach(event => {
			if (events[event.dateLocalized]) {
				events[event.dateLocalized].push(event)
			} else {
				events[event.dateLocalized] = [event]
			}
		})

	return events
})

onBeforeMount(() => {
	getCalendars()
})

/**
 * Get user's calendars to identify belonging of known and future events
 */
async function getCalendars() {
	await groupwareStore.getPersonalCalendars()
	loading.value = false
}
</script>

<template>
	<NcPopover :container="container" :focus-trap="false">
		<template #trigger>
			<slot name="trigger" />
		</template>
		<template #default>
			<ul v-if="!loading && Object.keys(upcomingEvents).length" class="calendar-events__wrapper">
				<template v-for="(day, key) in upcomingEvents">
					<li :key="key" class="calendar-events__date">
						{{ key }}
					</li>
					<li v-for="event in day" :key="event.uri">
						<a class="calendar-events__item"
							:href="event.calendarAppUrl"
							:title="t('spreed', 'Open Calendar')"
							target="_blank">
							<IconCalendarRefresh v-if="event.recurrenceId" :size="20" />
							<IconCalendarBlank v-else :size="20" />
							<span class="calendar-events__time">{{ event.startTimeLocalized }}</span>
							<span class="calendar-events__badge" :style="event.style" />
							<span>{{ event.summary }}</span>
						</a>
					</li>
				</template>
			</ul>
			<NcEmptyContent v-else class="calendar-events__wrapper">
				<template #icon>
					<NcLoadingIcon v-if="loading" />
					<IconCalendarBlank v-else />
				</template>

				<template #description>
					<p>{{ loading ? t('spreed', 'Loading …') : t('spreed', 'No upcoming events') }}</p>
				</template>
			</NcEmptyContent>
		</template>
	</NcPopover>
</template>

<style lang="scss" scoped>
.calendar-events {
	&__wrapper {
		width: 400px;
		padding: calc(var(--default-grid-baseline) * 2);
	}

	&__date {
		padding: var(--default-grid-baseline);
		border-radius: var(--border-radius);
		background-color: var(--color-background-dark);
	}

	&__item {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: var(--default-grid-baseline);
		padding: var(--default-grid-baseline);
		height: 100%;
		border-radius: var(--border-radius);

		&:not(:last-child) {
			border-bottom: 1px solid var(--color-background-dark);
		}
	}

	&__time {
		flex-shrink: 0;
		width: 8ch;
	}

	&__badge {
		display: inline-block;
		flex-shrink: 0;
		width: calc(0.6 * var(--default-font-size));
		height: calc(0.6 * var(--default-font-size));
		border-radius: 50%;
		background-color: var(--primary-color);
	}
}
</style>
