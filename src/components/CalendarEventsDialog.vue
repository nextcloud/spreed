<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onBeforeMount, ref, watch } from 'vue'

import IconCalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDateTimePickerNative from '@nextcloud/vue/dist/Components/NcDateTimePickerNative.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'
import usernameToColor from '@nextcloud/vue/dist/Functions/usernameToColor.js'

import { useStore } from '../composables/useStore.js'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { useGroupwareStore } from '../stores/groupware.ts'

const props = defineProps<{
	token: string,
	container?: string,
}>()
const emit = defineEmits<{
	(event: 'close'): void,
}>()

const hideTriggers = (triggers: string[]) => [...triggers, 'click']

const store = useStore()
const groupwareStore = useGroupwareStore()
const isMobile = useIsMobile()

const isFormOpen = ref(false)
const loading = ref(Object.keys(groupwareStore.calendars).length === 0)
const submitting = ref(false)

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

type CalendarOption = { value: string, label: string, color: string }
const calendarOptions = computed<CalendarOption[]>(() => groupwareStore.writeableCalendars.map(calendar => ({
	value: calendar.uri,
	label: calendar.displayname,
	color: calendar.color ?? usernameToColor(calendar.uri).color
})))
const canScheduleMeeting = computed(() => {
	return hasTalkFeature(props.token, 'schedule-meeting') && store.getters.isModerator && calendarOptions.value.length
})

const selectedCalendar = ref<CalendarOption | null>(null)
const selectedDateTimeStart = ref(new Date(moment().add(1, 'hours').startOf('hour')))
const selectedDateTimeEnd = ref(new Date(moment().add(2, 'hours').startOf('hour')))
const newMeetingTitle = ref('')
const newMeetingDescription = ref('')
const invalid = ref<string|null>(null)
const invalidHint = computed(() => {
	switch (invalid.value) {
	case null:
		return ''
	case 'calendar':
		return t('spreed', 'Error: Invalid calendar selected')
	case 'start':
		return t('spreed', 'Error: Invalid start time selected')
	case 'end':
		return t('spreed', 'Error: Invalid end time selected')
	case 'unknown':
	default:
		return t('spreed', 'Error: Unknown error occurred')
	}
})

onBeforeMount(() => {
	getCalendars()
})

watch(isFormOpen, (value) => {
	if (!value) {
		return
	}

	// Reset the default form values
	selectedCalendar.value = calendarOptions.value.find(o => o.value === groupwareStore.defaultCalendarUri) ?? null
	selectedDateTimeStart.value = new Date(moment().add(1, 'hours').startOf('hour'))
	selectedDateTimeEnd.value = new Date(moment().add(2, 'hours').startOf('hour'))
	newMeetingTitle.value = ''
	newMeetingDescription.value = ''
	invalid.value = null
})

watch([selectedCalendar, selectedDateTimeStart, selectedDateTimeEnd], () => {
	invalid.value = null
})

/**
 * Get user's calendars to identify belonging of known and future events
 */
async function getCalendars() {
	await groupwareStore.getDefaultCalendarUri()
	await groupwareStore.getPersonalCalendars()
	loading.value = false
}

/**
 * Get user's calendars to identify belonging of known and future events
 */
async function submitNewMeeting() {
	if (!selectedCalendar.value) {
		invalid.value = 'calendar'
		return
	}
	if (selectedDateTimeStart.value < new Date()) {
		invalid.value = 'start'
		return
	}
	if (selectedDateTimeEnd.value < new Date() || selectedDateTimeEnd.value < selectedDateTimeStart.value) {
		invalid.value = 'end'
		return
	}

	try {
		submitting.value = true
		await groupwareStore.scheduleMeeting(props.token, {
			calendarUri: selectedCalendar.value.value,
			start: selectedDateTimeStart.value.getTime() / 1000,
			end: selectedDateTimeEnd.value.getTime() / 1000,
			title: newMeetingTitle.value || null,
			description: newMeetingDescription.value || null,
		})
		isFormOpen.value = false
	} catch (error) {
		// @ts-expect-error Vue: Property response does not exist
		invalid.value = error?.response?.data?.ocs?.data?.error ?? 'unknown'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<div>
		<NcPopover :container="container"
			:popper-hide-triggers="hideTriggers"
			popup-role="dialog">
			<template #trigger>
				<NcButton class="upcoming-meeting"
					:title="t('spreed', 'Upcoming meetings')"
					:aria-label="t('spreed', 'Upcoming meetings')">
					<template #icon>
						<IconCalendarBlank :size="20" />
					</template>
					<template v-if="upcomingEvents[0] && !isMobile" #default>
						<span class="upcoming-meeting__header">
							{{ t('spreed', 'Next meeting') }}
						</span>
						<span> {{ upcomingEvents[0].start }} </span>
					</template>
				</NcButton>
			</template>
			<template #default>
				<template v-if="!loading && upcomingEvents.length">
					<ul class="calendar-events__list">
						<!-- Upcoming event -->
						<li v-for="event in upcomingEvents" :key="event.uri">
							<a class="calendar-events__item"
								:class="{ 'calendar-events__item--thumb': !event.href }"
								:href="event.href"
								:title="t('spreed', 'Open Calendar')"
								target="_blank">
								<span class="calendar-badge" :style="{ backgroundColor: event.color }" />
								<span class="calendar-events__content">
									<span class="calendar-events__header">
										<span class="calendar-events__header-text">{{ event.summary }}</span>
										<IconReload v-if="event.recurrenceId" :size="13" />
									</span>
									<span>{{ event.start }}</span>
								</span>
							</a>
						</li>
					</ul>
				</template>
				<NcEmptyContent v-else class="calendar-events__empty-content">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<IconCalendarBlank v-else />
					</template>

					<template #description>
						<p>{{ loading ? t('spreed', 'Loading …') : t('spreed', 'No upcoming events') }}</p>
					</template>
				</NcEmptyContent>
				<div v-if="canScheduleMeeting" class="calendar-events__buttons">
					<NcButton wide @click="isFormOpen = true">
						<template #icon>
							<IconPlus :size="20" />
						</template>
						{{ t('spreed', 'Schedule a meeting') }}
					</NcButton>
				</div>
			</template>
		</NcPopover>

		<NcDialog v-if="canScheduleMeeting"
			:open.sync="isFormOpen"
			class="calendar-events"
			:name="t('spreed', 'Schedule a meeting')"
			size="normal"
			close-on-click-outside
			:container="container">
			<div class="calendar-meeting">
				<NcTextField v-model="newMeetingTitle"
					:label="t('spreed', 'Meeting title')"
					label-visible />
				<NcTextArea v-model="newMeetingDescription"
					:label="t('spreed', 'Description')"
					resize="vertical"
					label-visible />
				<div class="calendar-meeting__flex-wrapper">
					<NcDateTimePickerNative id="schedule_meeting_input"
						v-model="selectedDateTimeStart"
						:class="{ 'invalid-time': invalid === 'start' }"
						:min="new Date()"
						:step="300"
						:label="t('spreed', 'From')"
						type="datetime-local" />
					<NcDateTimePickerNative id="schedule_meeting_input"
						v-model="selectedDateTimeEnd"
						:class="{ 'invalid-time': invalid === 'end' }"
						:min="new Date()"
						:step="300"
						:label="t('spreed', 'To')"
						type="datetime-local" />
				</div>
				<NcSelect id="schedule_meeting_select"
					v-model="selectedCalendar"
					:options="calendarOptions"
					:input-label="t('spreed', 'Calendar')">
					<template #selected-option="option">
						<span class="calendar-badge" :style="{ backgroundColor: option.color }" />
						{{ option.label }}
					</template>
					<template #option="option">
						<span class="calendar-badge" :style="{ backgroundColor: option.color }" />
						{{ option.label }}
					</template>
				</NcSelect>
				<p v-if="invalidHint" class="calendar-meeting__invalid-hint">
					{{ invalidHint }}
				</p>
			</div>

			<template #actions>
				<NcButton type="primary"
					:disabled="!selectedCalendar || submitting || !!invalid"
					@click="submitNewMeeting">
					<template #icon>
						<NcLoadingIcon v-if="submitting" :size="20" />
						<IconCheck v-else :size="20" />
					</template>
					{{ t('spreed', 'Save') }}
				</NcButton>
			</template>
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
		margin: calc(var(--default-grid-baseline) / 2);
		line-height: 20px;
		max-height: calc(4.5 * var(--item-height) + 4 * var(--default-grid-baseline));
		overflow-y: auto;

		& > * {
			margin-inline: calc(var(--default-grid-baseline) / 2);
			&:not(:last-child) {
				border-bottom: 1px solid var(--color-border-dark);
			}
		}
	}

	&__item {
		display: flex;
		flex-direction: row;
		align-items: center;
		margin-block: var(--default-grid-baseline);
		padding-inline: var(--default-grid-baseline);
		height: 100%;
		border-radius: var(--border-radius);

		&--thumb {
			cursor: default;
		}

		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		justify-content: center;
	}

	&__header {
		display: flex;
		gap: var(--default-grid-baseline);
		max-width: 150px;
		font-weight: 500;

		&-text {
			display: inline-block;
			width: 100%;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		:deep(.material-design-icon) {
			margin-top: 2px;
		}
	}

	&__empty-content {
		margin-top: calc(var(--default-grid-baseline) * 3);
	}

	&__buttons {
		padding: var(--default-grid-baseline);
	}
}

.calendar-meeting {
	display: flex;
	flex-direction: column;
	margin: calc(var(--default-grid-baseline) / 2);
	gap: var(--default-grid-baseline);

	&__header {
		margin-block: calc(var(--default-grid-baseline) * 3);
		text-align: center;
	}

	&__invalid-hint {
		color: var(--color-error);
	}

	&__flex-wrapper {
		display: flex;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	// Overwrite default NcDateTimePickerNative styles
	:deep(.native-datetime-picker) {
		width: calc(50% - var(--default-grid-baseline));
		margin-bottom: var(--default-grid-baseline);

		label {
			margin-bottom: 2px;
		}

		input {
			margin: 0;
			border-width: 1px;
		}

		&.invalid-time input {
			border-width: 2px;
			border-color: var(--color-error);
		}
	}
}

.upcoming-meeting {
	// Overwrite default NcButton styles
	:deep(.button-vue__text) {
		padding-block: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		line-height: 20px;
		font-weight: 400;
	}

	&__header {
		font-weight: 500;
	}
}

.calendar-badge {
	display: inline-block;
	width: var(--default-font-size);
	height: var(--default-font-size);
	margin-inline: calc((var(--default-clickable-area) - var(--default-font-size)) / 2);
	border-radius: 50%;
	background-color: var(--primary-color);
}
</style>
