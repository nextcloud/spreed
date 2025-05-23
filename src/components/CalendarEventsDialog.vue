<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onBeforeMount, provide, ref, watch } from 'vue'

import IconAccountPlus from 'vue-material-design-icons/AccountPlus.vue'
import IconAccountSearch from 'vue-material-design-icons/AccountSearch.vue'
import IconCalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { t, n } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import usernameToColor from '@nextcloud/vue/functions/usernameToColor'

import SelectableParticipant from './BreakoutRoomsEditor/SelectableParticipant.vue'
import CalendarEventSmall from './UIShared/CalendarEventSmall.vue'
import ContactSelectionBubble from './UIShared/ContactSelectionBubble.vue'
import SearchBox from './UIShared/SearchBox.vue'
import TransitionWrapper from './UIShared/TransitionWrapper.vue'

import { useStore } from '../composables/useStore.js'
import { ATTENDEE, CONVERSATION } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { useGroupwareStore } from '../stores/groupware.ts'
import type { Conversation, Participant } from '../types/index.ts'
import { convertToUnix } from '../utils/formattedTime.ts'
import { getDisplayNameWithFallback } from '../utils/getDisplayName.ts'

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

// Add a visual bulk selection state for SelectableParticipant component
provide('bulkParticipantsSelection', true)

const isFormOpen = ref(false)
const isSelectorOpen = ref(false)
const loading = ref(Object.keys(groupwareStore.calendars).length === 0)
const submitting = ref(false)

const calendars = computed(() => groupwareStore.calendars)
const upcomingEvents = computed(() => {
	const now = convertToUnix(Date.now())
	return groupwareStore.getAllEvents(props.token)
		.sort((a, b) => (a.start && b.start) ? (a.start - b.start) : 0)
		.map((event) => {
			const start = event.start
				? (event.start <= now) ? t('spreed', 'Now') : moment(event.start * 1000).calendar()
				: ''
			const color = calendars.value[event.calendarUri]?.color ?? usernameToColor(event.calendarUri).color

			return { ...event, start, color, href: event.calendarAppUrl ?? undefined }
		})
})

type CalendarOption = { value: string, label: string, color: string }
const calendarOptions = computed<CalendarOption[]>(() => groupwareStore.writeableCalendars.map((calendar) => ({
	value: calendar.uri,
	label: calendar.displayname,
	color: calendar.color ?? usernameToColor(calendar.uri).color,
})))
const canScheduleMeeting = computed(() => {
	return hasTalkFeature(props.token, 'schedule-meeting') && store.getters.isModerator && calendarOptions.value.length !== 0
		&& conversation.value.type !== CONVERSATION.TYPE.ONE_TO_ONE_FORMER
})

const selectedCalendar = ref<CalendarOption | null>(null)
const selectedDateTimeStart = ref(getCurrentDateInStartOfNthHour(1))
const selectedDateTimeEnd = ref(getCurrentDateInStartOfNthHour(2))
const newMeetingTitle = ref('')
const newMeetingDescription = ref('')
const invalid = ref<string | null>(null)
const invalidHint = computed(() => {
	switch (invalid.value) {
		case null:
			return ''
		case 'calendar':
			return t('spreed', 'Invalid calendar selected')
		case 'start':
			return t('spreed', 'Invalid start time selected')
		case 'end':
			return t('spreed', 'Invalid end time selected')
		case 'unknown':
		default:
			return t('spreed', 'Unknown error occurred')
	}
})

const selectAll = ref(true)
const selectedAttendeeIds = ref<number[]>([])
const attendeeHint = computed(() => {
	if (!selectedAttendeeIds.value?.length) {
		return t('spreed', 'Sending no invitations')
	}

	const list: Participant[] = selectedParticipants.value.slice(0, 2)
	const remainingCount = selectedParticipants.value.length - list.length
	const summary = list.map((participant) => getDisplayNameWithFallback(participant.displayName, participant.actorType))

	if (remainingCount === 0) {
		// Amount is 2 or less
		switch (summary.length) {
			case 1: {
				return t('spreed', '{participant0} will receive an invitation', { participant0: summary[0] }, undefined, {
					escape: false,
					sanitize: false,
				})
			}
			case 2: {
				return t('spreed', '{participant0} and {participant1} will receive invitations', { participant0: summary[0], participant1: summary[1] }, undefined, {
					escape: false,
					sanitize: false,
				})
			}
			case 0:
			default: {
				return ''
			}
		}
	} else {
		return n('spreed', '{participant0}, {participant1} and %n other will receive invitations', '{participant0}, {participant1} and %n others will receive invitations', remainingCount, { participant0: summary[0], participant1: summary[1] }, {
			escape: false,
			sanitize: false,
		})
	}
})

const searchText = ref('')
const isMatch = (string: string = '') => string.toLowerCase().includes(searchText.value.toLowerCase())

const conversation = computed<Conversation>(() => store.getters.conversation(props.token))
const participants = computed(() => {
	if (isOneToOneConversation.value && store.getters.participantsList(props.token).length === 1) {
		// Second participant is not yet added to conversation, need to fake data from conversation object
		// We do not have an attendeeId, so 'attendeeIds' in payload should be 'null' (selectAll === true)
		return [{ id: conversation.value.name, source: ATTENDEE.ACTOR_TYPE.USERS, displayName: conversation.value.displayName }]
	}
	return store.getters.participantsList(props.token).filter((participant: Participant) => {
		return [ATTENDEE.ACTOR_TYPE.USERS, ATTENDEE.ACTOR_TYPE.EMAILS].includes(participant.actorType)
			&& participant.attendeeId !== conversation.value.attendeeId
	})
})
const participantsInitialised = computed(() => store.getters.participantsInitialised(props.token))
const filteredParticipants = computed(() => participants.value.filter((participant: Participant) => {
	return isMatch(participant.displayName)
		|| (participant.actorType === ATTENDEE.ACTOR_TYPE.USERS && isMatch(participant.actorId))
		|| (participant.actorType === ATTENDEE.ACTOR_TYPE.EMAILS && participant.invitedActorId && isMatch(participant.invitedActorId))
}))
const selectedParticipants = computed(() => participants.value
	.filter((participant: Participant) => selectedAttendeeIds.value.includes(participant.attendeeId))
	.sort((a: Participant, b: Participant) => {
		if (a.actorType === ATTENDEE.ACTOR_TYPE.USERS && b.actorType === ATTENDEE.ACTOR_TYPE.EMAILS) {
			return -1
		} else if (a.actorType === ATTENDEE.ACTOR_TYPE.EMAILS && b.actorType === ATTENDEE.ACTOR_TYPE.USERS) {
			return 1
		} else if (a.actorType === ATTENDEE.ACTOR_TYPE.EMAILS && b.actorType === ATTENDEE.ACTOR_TYPE.EMAILS
			&& (!a.displayName || !b.displayName)) {
			return a.displayName ? -1 : 1
		}
		return 0
	}),
)

const isOneToOneConversation = computed(() => {
	return conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE
		|| conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
})

const inviteLabel = computed(() => {
	return isOneToOneConversation.value
		? t('spreed', 'Invite {user}', { user: conversation.value.displayName })
		: t('spreed', 'Invite all users and emails in this conversation')
})

onBeforeMount(() => {
	getCalendars()
})

watch(isFormOpen, (value) => {
	if (!value) {
		return
	}

	// Reset the default form values
	selectedCalendar.value = calendarOptions.value.find((o) => o.value === groupwareStore.defaultCalendarUri) ?? null
	selectedDateTimeStart.value = getCurrentDateInStartOfNthHour(1)
	selectedDateTimeEnd.value = getCurrentDateInStartOfNthHour(2)
	newMeetingTitle.value = ''
	newMeetingDescription.value = ''
	selectedAttendeeIds.value = participants.value.map((participant: Participant) => participant.attendeeId)
	searchText.value = ''
	selectAll.value = true
	invalid.value = null
})

watch([selectedCalendar, selectedDateTimeStart, selectedDateTimeEnd], () => {
	invalid.value = null
})

watch(participants, (value) => {
	if (selectAll.value) {
		selectedAttendeeIds.value = value.map((participant: Participant) => participant.attendeeId)
	}
})

/**
 * Returns Date object with N hours from now at the start of hour
 * @param hours amount of hours to add
 */
function getCurrentDateInStartOfNthHour(hours: number) {
	const date = new Date()
	date.setHours(date.getHours() + hours, 0, 0, 0)
	return date
}

/**
 * Toggle selected attendees
 * @param value switch value
 */
function toggleAll(value: boolean) {
	selectedAttendeeIds.value = value ? participants.value.map((participant: Participant) => participant.attendeeId) : []
}

/**
 * Remove selected attendee from contact bubble
 * @param value switch value
 */
function removeSelectedParticipant(value: Participant) {
	selectedAttendeeIds.value = selectedAttendeeIds.value.filter((id) => value.attendeeId !== id)
}

/**
 * Check selected attendees
 * @param value array of ids
 */
function checkSelection(value: number[]) {
	selectAll.value = participants.value.length === value.length
}

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
			start: convertToUnix(selectedDateTimeStart.value),
			end: convertToUnix(selectedDateTimeEnd.value),
			title: newMeetingTitle.value || null,
			description: newMeetingDescription.value || null,
			attendeeIds: selectAll.value ? null : selectedAttendeeIds.value,
		})
		showSuccess(t('spreed', 'Meeting created'))
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
			:focus-trap="canScheduleMeeting || upcomingEvents.length !== 0"
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
						<CalendarEventSmall v-for="event in upcomingEvents"
							:key="event.uri"
							:name="event.summary"
							:start="event.start"
							:href="event.href"
							:color="event.color"
							:is-recurring="!!event.recurrenceId" />
					</ul>
				</template>
				<NcEmptyContent v-else class="calendar-events__empty-content">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<IconCalendarBlank v-else />
					</template>

					<template #description>
						<p>{{ loading ? t('spreed', 'Loading …') : t('spreed', 'No upcoming meetings') }}</p>
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

		<template v-if="canScheduleMeeting">
			<NcDialog id="calendar-meeting"
				:open.sync="isFormOpen"
				class="calendar-meeting"
				:name="t('spreed', 'Schedule a meeting')"
				size="normal"
				close-on-click-outside
				:container="container">
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
				<h5 v-if="!isOneToOneConversation" class="calendar-meeting__header">
					{{ t('spreed', 'Attendees') }}
				</h5>
				<div v-if="!participantsInitialised"
					class="calendar-meeting--loading">
					<NcLoadingIcon />
					{{ t('spreed', 'Loading …') }}
				</div>
				<p v-else-if="participants.length === 0">
					{{ t('spreed', 'No other participants to send invitations to.') }}
				</p>
				<template v-else>
					<NcCheckboxRadioSwitch v-model="selectAll" @update:modelValue="toggleAll">
						{{ inviteLabel }}
					</NcCheckboxRadioSwitch>
					<NcButton v-if="!isOneToOneConversation && !selectAll" type="tertiary" @click="isSelectorOpen = true">
						<template #icon>
							<IconAccountPlus :size="20" />
						</template>
						{{ t('spreed', 'Add attendees') }}
					</NcButton>
					<p>{{ attendeeHint }}</p>
				</template>

				<template #actions>
					<p v-if="invalidHint" class="calendar-meeting__invalid-hint">
						{{ invalidHint }}
					</p>
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

			<NcDialog v-if="isSelectorOpen"
				:open.sync="isSelectorOpen"
				:name="t('spreed', 'Add attendees')"
				class="calendar-meeting"
				close-on-click-outside
				container="#calendar-meeting">
				<SearchBox class="calendar-meeting__searchbox"
					:value.sync="searchText"
					is-focused
					:placeholder-text="t('spreed', 'Search participants')"
					@abort-search="searchText = ''" />
				<!-- Selected results -->
				<TransitionWrapper v-if="selectedAttendeeIds.length"
					class="calendar-meeting__attendees-selected"
					name="zoom"
					tag="div"
					group>
					<ContactSelectionBubble v-for="participant in selectedParticipants"
						:key="participant.actorType + participant.actorId"
						:participant="participant"
						@update="removeSelectedParticipant" />
				</TransitionWrapper>
				<ul v-if="participantsInitialised && filteredParticipants.length" class="calendar-meeting__attendees">
					<SelectableParticipant v-for="participant in filteredParticipants"
						:key="participant.attendeeId"
						:checked.sync="selectedAttendeeIds"
						:participant="participant"
						@update:checked="checkSelection" />
				</ul>
				<NcEmptyContent v-else
					class="calendar-meeting__empty-content"
					:name="!participantsInitialised ? t('spreed', 'Loading …') : t('spreed', 'No results')">
					<template #icon>
						<NcLoadingIcon v-if="!participantsInitialised" />
						<IconAccountSearch v-else />
					</template>
				</NcEmptyContent>
				<template #actions>
					<NcButton type="primary" @click="isSelectorOpen = false">
						<template #icon>
							<IconCheck :size="20" />
						</template>
						{{ t('spreed', 'Done') }}
					</NcButton>
				</template>
			</NcDialog>
		</template>
	</div>
</template>

<style lang="scss" scoped>
.calendar-events {
	&__list {
		--item-height: calc(2lh + var(--default-grid-baseline) * 3);
		display: flex;
		flex-direction: column;
		margin: calc(var(--default-grid-baseline) / 2);
		line-height: 20px;
		max-height: calc(4.5 * var(--item-height) + 4 * var(--default-grid-baseline));
		max-width: 200px;
		overflow-y: auto;

		& > * {
			margin-inline: calc(var(--default-grid-baseline) / 2);

			&:not(:last-child) {
				border-bottom: 1px solid var(--color-border-dark);
			}
		}
	}

	&__empty-content {
		min-width: 150px;
		margin-top: calc(var(--default-grid-baseline) * 3);
		padding: var(--default-grid-baseline);
	}

	&__buttons {
		padding: var(--default-grid-baseline);
	}
}

.calendar-meeting {
	--item-height: calc(2lh + var(--default-grid-baseline) * 2);

	:deep(.dialog__content) {
		display: flex;
		flex-direction: column;
		margin: calc(var(--default-grid-baseline) / 2);
		gap: var(--default-grid-baseline);
	}

	:deep(.dialog__actions) {
		align-items: center;
	}

	&__header {
		margin-block: calc(var(--default-grid-baseline) * 2);
	}

	&__invalid-hint {
		color: var(--color-error);
	}

	&__flex-wrapper {
		display: flex;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__searchbox {
		margin-inline: var(--default-grid-baseline);
		margin-block-end: var(--default-grid-baseline);
		width: calc(100% - var(--default-grid-baseline) * 2) !important;
	}

	&__attendees {
		height: calc(5.5 * var(--item-height));
		padding-block: var(--default-grid-baseline);
		overflow-y: auto;
	}

	&__attendees-selected {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
		border-bottom: 1px solid var(--color-background-darker);
		padding: var(--default-grid-baseline) 0;
		max-height: 97px;
		overflow-y: auto;
		flex: 1 0 auto;
		align-content: flex-start;
	}

	&__empty-content {
		height: calc(5.5 * var(--item-height));
		margin-block: auto !important;
	}

	&--loading {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		height: 32px;
	}

	// Overwrite default NcDateTimePickerNative styles
	:deep(.native-datetime-picker) {
		width: calc(50% - var(--default-grid-baseline));
		margin-bottom: var(--default-grid-baseline);

		&.invalid-time input {
			--border-width-input: 2px;
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
