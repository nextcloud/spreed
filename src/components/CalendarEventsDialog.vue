<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onBeforeMount, ref } from 'vue'

import IconCalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import IconCalendarRefresh from 'vue-material-design-icons/CalendarRefresh.vue'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import usernameToColor from '@nextcloud/vue/dist/Functions/usernameToColor.js'

import { useGroupwareStore } from '../stores/groupware.ts'

const props = defineProps<{
	token: string,
	container?: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const groupwareStore = useGroupwareStore()

const open = ref(false)
const loading = ref(Object.keys(groupwareStore.calendars).length === 0)

const calendars = computed(() => groupwareStore.calendars)
const upcomingEvents = computed(() => {
	const now = moment().unix()
	return groupwareStore.getAllEvents(props.token)
		.sort((a, b) => (a.start && b.start) ? (a.start - b.start) : 0)
		.map(event => {
			const start = event.start
				? (event.start <= now) ? t('spreed', 'Now') : moment(event.start * 1000).calendar()
				: ''
			const color = calendars.value[event.calendarUri]?.color ?? usernameToColor(event.calendarUri).color

			return { ...event, start, color, href: event.calendarAppUrl ?? undefined }
		})
})

onBeforeMount(() => {
	getCalendars()
})

/**
 * Show upcoming events dialog
 */
function openDialog() {
	open.value = true
}

/**
 * Get user's calendars to identify belonging of known and future events
 */
async function getCalendars() {
	await groupwareStore.getPersonalCalendars()
	loading.value = false
}
</script>

<template>
	<div>
		<slot name="trigger" :on-click="openDialog">
			<NcButton :title="t('spreed', 'Upcoming events')"
				:aria-label="t('spreed', 'Upcoming events')"
				@click="openDialog">
				<template #icon>
					<IconCalendarBlank :size="20" />
				</template>
			</NcButton>
		</slot>

		<NcDialog :open.sync="open"
			class="calendar-events"
			:name="t('spreed', 'Upcoming events')"
			size="normal"
			close-on-click-outside
			:container="container">
			<template v-if="!loading && upcomingEvents.length">
				<ul class="calendar-events__list">
					<!-- Upcoming event -->
					<li v-for="event in upcomingEvents" :key="event.uri">
						<a class="calendar-events__item"
							:class="{ 'calendar-events__item--thumb': !event.href }"
							:href="event.href"
							:title="t('spreed', 'Open Calendar')"
							target="_blank">
							<IconCalendarRefresh v-if="event.recurrenceId" :size="20" />
							<IconCalendarBlank v-else :size="20" />
							<div class="calendar-badge" :style="{ backgroundColor: event.color }" />
							<div class="calendar-events__content">
								<p class="calendar-events__header">
									{{ event.summary }}
								</p>
								<p>{{ event.start }}</p>
							</div>
						</a>
					</li>
				</ul>
			</template>
			<NcEmptyContent v-else>
				<template #icon>
					<NcLoadingIcon v-if="loading" />
					<IconCalendarBlank v-else />
				</template>

				<template #description>
					<p>{{ loading ? t('spreed', 'Loading …') : t('spreed', 'No upcoming events') }}</p>
				</template>
			</NcEmptyContent>
		</NcDialog>
	</div>
</template>

<style lang="scss" scoped>
.calendar-events {
	margin-block-end: calc(var(--default-grid-baseline) * 2);

	:deep(.dialog__content) {
		padding-block-end: calc(var(--default-grid-baseline) * 3);
	}

	&__list {
		--item-height: calc(2lh + var(--default-grid-baseline) * 3);
		display: flex;
		flex-direction: column;
		margin: var(--default-grid-baseline);
		gap: var(--default-grid-baseline);
		line-height: 20px;
		max-height: calc(4.5 * var(--item-height) + 4 * var(--default-grid-baseline));
		overflow-y: auto;
	}

	&__item {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
		margin: calc(var(--default-grid-baseline) / 2);
		padding: var(--default-grid-baseline);
		color: var(--color-primary-element-light-text);
		background-color: var(--color-primary-element-light);
		height: 100%;
		border-radius: var(--border-radius);

    &--thumb {
      cursor: default;
    }

		&:hover {
			background-color: var(--color-primary-element-light-hover);
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	&__header {
		font-weight: 500;
	}
}

.calendar-badge {
	display: inline-block;
	width: var(--default-font-size);
	height: var(--default-font-size);
	border-radius: 50%;
	background-color: var(--primary-color);
}
</style>
