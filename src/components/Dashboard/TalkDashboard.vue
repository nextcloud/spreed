<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { computed, onMounted, onBeforeUnmount, watch, nextTick, ref } from 'vue'
import { useRouter } from 'vue-router/composables'

import IconAlarm from 'vue-material-design-icons/Alarm.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import IconAt from 'vue-material-design-icons/At.vue'
import IconCalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import IconMicrophone from 'vue-material-design-icons/Microphone.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import EventCard from './EventCard.vue'
import ConversationsListVirtual from '../LeftSidebar/ConversationsList/ConversationsListVirtual.vue'
import SearchMessageItem from '../RightSidebar/SearchMessages/SearchMessageItem.vue'
import LoadingPlaceholder from '../UIShared/LoadingPlaceholder.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useDashboardStore } from '../../stores/dashboard.ts'
import type { Conversation } from '../../types/index.ts'
import { filterConversation } from '../../utils/conversation.ts'

const supportsUpcomingReminders = hasTalkFeature('local', 'upcoming-reminders')

const FIVE_MINUTES = 5 * 60 * 1000 // 5 minutes
const store = useStore()
const router = useRouter()
const dashboardStore = useDashboardStore()
const forwardScrollable = ref(false)
const backwardScrollable = ref(false)
const eventCardsWrapper = ref<HTMLInputElement | null>(null)
const eventRooms = computed(() => dashboardStore.eventRooms || [])
const upcomingReminders = computed(() => dashboardStore.upcomingReminders || [])
const eventsInitialised = computed(() => dashboardStore.eventRoomsInitialised)
const remindersInitialised = computed(() => dashboardStore.upcomingRemindersInitialised)
let actualiseDataInterval: ReturnType<typeof setInterval> | null = null

// Data fetching handlers

/**
 * Fetches all necessary data for the dashboard.
 */
async function actualiseData() {
	await Promise.all([
		dashboardStore.fetchDashboardEventRooms(),
		dashboardStore.fetchUpcomingReminders(),
	])
}

onMounted(() => {
	actualiseData()
	actualiseDataInterval = setInterval(actualiseData, FIVE_MINUTES)
})

onBeforeUnmount(() => {
	if (actualiseDataInterval) {
		clearInterval(actualiseDataInterval)
	}

	if (eventCardsWrapper?.value) {
		eventCardsWrapper.value.removeEventListener('scroll', updateScrollableFlags)
		resizeObserver.disconnect()
	}
})

watch(eventCardsWrapper, (newValue) => {
	if (newValue) {
		newValue.addEventListener('scroll', updateScrollableFlags)
		resizeObserver.observe(newValue)
		updateScrollableFlags()
	}
})

/**
 * Updates the scrollable flags based on the current scroll position.
 */
async function updateScrollableFlags() {
	await nextTick()
	if (eventCardsWrapper.value) {
		const { scrollLeft, scrollWidth, clientWidth } = eventCardsWrapper.value
		backwardScrollable.value = scrollLeft > 0
		forwardScrollable.value = scrollLeft + clientWidth < scrollWidth - 10 // 10px tolerance
	}
}

// Use ResizeObserver to detect size changes
const resizeObserver = new ResizeObserver(() => {
	updateScrollableFlags()
})
const conversationsList = computed(() => store.getters.conversationsList)
const conversationsInitialised = computed(() => store.getters.conversationsInitialised)
const filteredConversations = computed(() => conversationsList.value?.filter((conversation : Conversation) => {
	return filterConversation(conversation, ['mentions'])
}))

/**
 * Creates a new group conversation and navigates to the conversation page.
 */
async function startMeeting() {
	try {
		const conversation = await store.dispatch('createGroupConversation', {
			roomName: t('spreed', 'Meeting'), // NOTE: it uses user language statically
			roomType: CONVERSATION.TYPE.PUBLIC,
			objectType: CONVERSATION.OBJECT_TYPE.INSTANT_MEETING,
			objectId: `${Math.floor(Date.now() / 1000)}`,
		})
		router.push({
			name: 'conversation',
			params: { token: conversation.token },
			hash: '#direct-call',
		})
	} catch (error) {
		console.error('Error creating conversation:', error)
		showError(t('spreed', 'Error creating a meeting'))
	}
}

/**
 * Scrolls the event cards wrapper in the specified direction.
 * @param {string} direction - The direction to scroll ('backward' or 'forward').
 */
function scroll({ direction } : { direction: 'backward' | 'forward' }) {
	const scrollDirection = direction === 'backward' ? -1 : 1
	if (eventCardsWrapper.value) {
		let scrollAmount = 0
		const visibleItems = Math.floor(eventCardsWrapper.value.clientWidth / (300 + 4))
		if (visibleItems === 0) {
			// FIXME: mobile view, scroll by 1 item
			scrollAmount = eventCardsWrapper.value.clientWidth * scrollDirection
		}
		scrollAmount = visibleItems * (300 + 4) * scrollDirection - 34 * scrollDirection
		eventCardsWrapper.value.scrollBy({
			left: scrollAmount,
			behavior: 'smooth',
		})

	}
}
</script>
<template>
	<div class="talk-dashboard-wrapper">
		<div class="talk-dashboard__header">
			{{ t('spreed', 'Hello, {displayName}', { displayName: store.getters.getDisplayName() }) }}
		</div>
		<div class="talk-dashboard__title">
			<span class="title">{{ t('spreed', 'Upcoming meetings') }}</span>
			<NcButton type="primary"
				@click="startMeeting">
				<template #icon>
					<IconVideo />
				</template>
				{{ t('spreed', 'Start meeting now') }}
			</NcButton>
		</div>
		<div v-if="eventsInitialised && eventRooms.length > 0"
			class="talk-dashboard__event-cards-wrapper"
			:class="{'forward-scrollable': forwardScrollable, 'backward-scrollable': backwardScrollable}">
			<div ref="eventCardsWrapper"
				class="talk-dashboard__event-cards">
				<EventCard v-for="eventRoom in eventRooms"
					:key="eventRoom.eventLink"
					:event-room="eventRoom"
					class="talk-dashboard__event-card" />
			</div>
			<div class="talk-dashboard__event-cards__scroll-indicator">
				<NcButton v-show="backwardScrollable"
					class="button-slide backward"
					type="tertiary"
					:title="t('spreed', 'Scroll backward')"
					:aria-label="t('spreed', 'Scroll backward')"
					@click="scroll({direction: 'backward'})">
					<template #icon>
						<IconArrowLeft class="bidirectional-icon" />
					</template>
				</NcButton>
				<NcButton v-show="forwardScrollable"
					class="button-slide forward"
					type="tertiary"
					:title="t('spreed', 'Scroll forward')"
					:aria-label="t('spreed', 'Scroll forward')"
					@click="scroll({direction: 'forward'})">
					<template #icon>
						<IconArrowRight class="bidirectional-icon" />
					</template>
				</NcButton>
			</div>
		</div>
		<LoadingPlaceholder v-else-if="!eventsInitialised"
			type="event-cards" />
		<NcEmptyContent v-else
			class="talk-dashboard__empty-content"
			:name="t('spreed', 'No upcoming meetings')"
			:description="t('spreed', 'You have no upcoming meetings scheduled in the next 7 days')">
			<template #icon>
				<IconCalendarBlank :size="40" />
			</template>
		</NcEmptyContent>
		<NcButton class="talk-dashboard__devices-button"
			type="tertiary"
			@click="emit('talk:media-settings:show', 'device-check')">
			<template #icon>
				<IconMicrophone :size="20" />
			</template>
			{{ t('spreed', 'Check devices') }}
		</NcButton>
		<div class="talk-dashboard__chats">
			<div class="talk-dashboard__unread-mentions"
				:class="{'loading': !conversationsInitialised}">
				<span class="title">{{ t('spreed', 'Unread mentions') }}</span>
				<ConversationsListVirtual v-if="filteredConversations.length > 0 || !conversationsInitialised"
					class="talk-dashboard__conversations-list"
					:conversations="filteredConversations"
					:loading="!conversationsInitialised" />
				<NcEmptyContent v-else
					class="talk-dashboard__empty-content"
					:name="t('spreed', 'All caught up!')"
					:description="t('spreed', 'You have no unread mentions')">
					<template #icon>
						<IconAt :size="40" />
					</template>
				</NcEmptyContent>
			</div>
			<div v-if="supportsUpcomingReminders"
				class="talk-dashboard__upcoming-reminders">
				<span class="title">{{ t('spreed', 'Upcoming reminders') }}</span>
				<div v-if="upcomingReminders.length > 0" class="upcoming-reminders-list">
					<SearchMessageItem v-for="reminder in upcomingReminders"
						:key="reminder.messageId"
						:message-id="reminder.messageId"
						:title="reminder.actorDisplayName"
						:subline="reminder.message"
						:token="reminder.roomToken"
						:to="{
							name: 'conversation',
							params: { token: reminder.roomToken, skipLeaveWarning: true },
							hash: `#message_${reminder.messageId}`
						}"
						:actor-id="reminder.actorId"
						:actor-type="reminder.actorType"
						:timestamp="`${reminder.reminderTimestamp}`" />
				</div>
				<LoadingPlaceholder v-else-if="!remindersInitialised"
					class="upcoming-reminders__loading-placeholder"
					type="conversations" />
				<NcEmptyContent v-else
					class="talk-dashboard__empty-content"
					:name="t('spreed', 'No Reminders Scheduled')"
					:description="t('spreed', 'You have no reminders scheduled')">
					<template #icon>
						<IconAlarm :size="40" />
					</template>
				</NcEmptyContent>
			</div>
		</div>
	</div>
</template>
<style lang="scss" scoped>
@import '../../assets/variables';

.talk-dashboard-wrapper {
	--title-height: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 2);
	--section-width: 300px;
	--section-height: 300px;
	padding-inline: calc(var(--default-grid-baseline) * 2);
	max-width: calc($messages-list-max-width + 400px); // FIXME: to change to a readable value
	margin: 0 auto;
}

.talk-dashboard__header {
	font-size: 21px; // NcDialog header font size
	font-weight: bold;
	height: 51px; // top bar height
	line-height: 51px;
	margin: 0 auto;
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline)); // navigation button
}

.talk-dashboard__title {
	width: 100%;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: var(--default-grid-baseline);
}

.talk-dashboard__event-cards {
	display: flex;
	flex-wrap: nowrap;
	gap: var(--default-grid-baseline);
	margin-block: var(--default-grid-baseline);
	overflow-x: auto;
	scrollbar-width: none;
}

.talk-dashboard__event-cards-wrapper {
	position: relative;
	&::before,
	&::after {
		content: '';
		position: absolute;
		top: 0;
		bottom: 0;
		width: var(--default-clickable-area);
		pointer-events: none;
		z-index: 2;
	}

	&.backward-scrollable::before {
		inset-inline-start: 0;
		background: linear-gradient(to right, rgba(var(--color-main-background-rgb), 1), rgba(var(--color-main-background-rgb), 0));
	}

	&.forward-scrollable::after {
		inset-inline-end: 0;
		background: linear-gradient(to left, rgba(var(--color-main-background-rgb), 1), rgba(var(--color-main-background-rgb), 0));
	}

	.button-slide {
		position: absolute !important;
		display: flex;
		top: 0;
		padding: 0;
		height: 100%;
		margin: 0 !important;
		z-index: 3;

		&.backward {
			inset-inline-start: 0;
			justify-content: left;
		}

		&.forward {
			inset-inline-end: 0;
			justify-content: left;
		}
	}
}

.talk-dashboard__devices-button {
	margin: calc(var(--default-grid-baseline) * 2) 0;
}

.talk-dashboard__chats {
	display: flex;
	gap: var(--default-grid-baseline);
	padding-block-end: calc(var(--default-grid-baseline) * 2);

	& .title {
		display: block;
		height: var(--default-clickable-area);
		margin : var(--default-grid-baseline);
	}
}

.talk-dashboard__unread-mentions {
	max-height: var(--section-height);
	width: var(--section-width);

	&.loading {
		overflow: hidden;
	}
}

.talk-dashboard__upcoming-reminders {
	max-height: var(--section-height);
	width: var(--section-width);

	&-list {
		overflow-y: auto;
		max-height: calc(100% - var(--title-height));
	}
}

.upcoming-reminders {
	&-list {
		overflow-y: auto;
		max-height: calc(100% - var(--title-height));
	}

	&__loading-placeholder {
		overflow: hidden;
		max-height: calc(100% - var(--title-height));
	}
}

.talk-dashboard__empty-content {
	border-radius: var(--border-radius-large);
	padding: calc(var(--default-grid-baseline) * 2);
	margin: var(--default-grid-baseline) 0;
}

.talk-dashboard__conversations-list {
	margin: var(--default-grid-baseline) 0;
	height: 100%;
	line-height: 20px;
	overflow-y: auto;
	max-height: calc(100% - var(--title-height));
}

.title {
	font-weight: bold;
}
</style>
