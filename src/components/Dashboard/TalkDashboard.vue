<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import type { Conversation } from '../../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { isRTL, t } from '@nextcloud/l10n'
import { generateUrl, imagePath } from '@nextcloud/router'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import { useIsMobile, useIsSmallMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import IconCalendarBlankOutline from 'vue-material-design-icons/CalendarBlankOutline.vue'
import IconList from 'vue-material-design-icons/FormatListBulleted.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconPhoneOutline from 'vue-material-design-icons/PhoneOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import ConversationsListVirtual from '../LeftSidebar/ConversationsList/ConversationsListVirtual.vue'
import SearchMessageItem from '../RightSidebar/SearchMessages/SearchMessageItem.vue'
import LoadingPlaceholder from '../UIShared/LoadingPlaceholder.vue'
import DashboardSection from './DashboardSection.vue'
import EventCard from './EventCard.vue'
import { CONVERSATION } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature, localCapabilities } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useDashboardStore } from '../../stores/dashboard.ts'
import { hasUnreadMentions } from '../../utils/conversation.ts'
import { convertToUnix } from '../../utils/formattedTime.ts'
import { copyConversationLinkToClipboard } from '../../utils/handleUrl.ts'

const supportsUpcomingReminders = hasTalkFeature('local', 'upcoming-reminders')
const canModerateSipDialOut = hasTalkFeature('local', 'sip-support-dialout')
	&& getTalkConfig('local', 'call', 'sip-enabled')
	&& getTalkConfig('local', 'call', 'sip-dialout-enabled')
	&& getTalkConfig('local', 'call', 'can-enable-sip')
const isCallEnabled = getTalkConfig('local', 'call', 'enabled')
const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')
const isCalendarEnabled = localCapabilities.calendar?.webui ?? false
const isDirectionRTL = isRTL()
const isDarkTheme = useIsDarkTheme()
const isMobile = useIsMobile()
const isSmallMobile = useIsSmallMobile()

/**
 * Path of the dashboard illustration matching the current direction and theme.
 * Right-to-left locales use the mirrored variant, dark themes the dark one.
 *
 * @param name - File name in img/dashboard/, without the extension and the `-rtl` / `-dark` suffixes
 */
function illustration(name: string): string {
	const variant = name + (isDirectionRTL ? '-rtl' : '') + (isDarkTheme.value ? '-dark' : '')
	return imagePath('spreed', `dashboard/${variant}.webp`)
}

const store = useStore()
const router = useRouter()
const dashboardStore = useDashboardStore()
const actorStore = useActorStore()
const forwardScrollable = ref(false)
const backwardScrollable = ref(false)
const eventCardsWrapper = ref<HTMLDivElement | null>(null)
const eventRooms = computed(() => dashboardStore.eventRooms || [])
const upcomingReminders = computed(() => dashboardStore.upcomingReminders || [])
const eventsInitialised = computed(() => dashboardStore.eventRoomsInitialised)
const remindersInitialised = computed(() => dashboardStore.upcomingRemindersInitialised)
const conversationName = ref('')
let actualizeDataInterval: ReturnType<typeof setInterval> | null = null

// Data fetching handlers

/**
 * Fetches all necessary data for the dashboard.
 */
async function actualizeData() {
	await Promise.all([
		dashboardStore.fetchDashboardEventRooms(),
		dashboardStore.fetchUpcomingReminders(),
	])
}

/**
 * Initializes the data fetching interval and fetches initial data.
 */
function initActualizeData() {
	if (actualizeDataInterval) {
		clearInterval(actualizeDataInterval)
	}
	actualizeData()
	actualizeDataInterval = setInterval(actualizeData, 300_000)
}

initActualizeData()
EventBus.on('refresh-talk-dashboard', initActualizeData)

onBeforeUnmount(() => {
	if (actualizeDataInterval) {
		clearInterval(actualizeDataInterval)
	}

	if (eventCardsWrapper?.value) {
		resizeObserver.disconnect()
	}

	EventBus.off('refresh-talk-dashboard', initActualizeData)
})

watch(eventCardsWrapper, (newValue) => {
	if (newValue) {
		resizeObserver.observe(newValue)
	}
})

/**
 * Updates the scrollable flags based on the current scroll position.
 */
async function updateScrollableFlags() {
	await nextTick()
	if (eventCardsWrapper.value) {
		const { scrollLeft, scrollWidth, clientWidth } = eventCardsWrapper.value
		backwardScrollable.value = isDirectionRTL ? scrollLeft < 0 : scrollLeft > 0
		forwardScrollable.value = (isDirectionRTL ? -1 : 1) * scrollLeft + clientWidth < scrollWidth - 10 // 10px tolerance
	}
}

// Use ResizeObserver to detect size changes
const resizeObserver = new ResizeObserver(() => {
	updateScrollableFlags()
})

const conversationsInitialised = computed(() => store.getters.conversationsInitialised)
const filteredConversations = computed(() => {
	return (store.getters.conversationsList as Conversation[])
		.filter(hasUnreadMentions)
		.sort((conversation1, conversation2) => conversation2.lastActivity - conversation1.lastActivity)
})

/**
 * Creates a new group conversation and navigates to the conversation page.
 */
async function startMeeting() {
	try {
		const conversation = await store.dispatch('createGroupConversation', {
			// TRANSLATORS: Section header for meeting-related settings; also a static name fallback for instant meeting conversation
			roomName: conversationName.value || t('spreed', 'Meeting'),
			roomType: CONVERSATION.TYPE.PUBLIC,
			objectType: CONVERSATION.OBJECT_TYPE.INSTANT_MEETING,
			objectId: convertToUnix(new Date()).toString(),
		})
		await copyConversationLinkToClipboard(conversation.token)
		await router.push({
			name: 'conversation',
			params: { token: conversation.token },
			hash: '#direct-call',
		})
	} catch (error) {
		console.error('Error creating conversation:', error)
		showError(t('spreed', 'Error while creating the conversation'))
	}
}

/**
 * Scrolls the event cards wrapper in the specified direction.
 *
 * @param payload
 * @param payload.direction - The direction to scroll ('backward' or 'forward').
 */
function scrollEventCards({ direction }: { direction: 'backward' | 'forward' }) {
	const scrollDirection = (direction === 'backward' ? -1 : 1) * (isDirectionRTL ? -1 : 1)
	if (eventCardsWrapper.value) {
		const ITEM_WIDTH = 300 + 8 // 300px width + 8px gap
		let scrollAmount = 0
		const visibleItems = Math.floor(eventCardsWrapper.value.clientWidth / ITEM_WIDTH)
		if (visibleItems === 0) {
			scrollAmount = eventCardsWrapper.value.clientWidth * scrollDirection
		} else {
			scrollAmount = visibleItems * ITEM_WIDTH * scrollDirection
			// Arrow buttons are 34px wide
			if (!backwardScrollable.value && scrollDirection === 1) {
				scrollAmount -= 34
			} else if (!forwardScrollable.value && scrollDirection === -1) {
				scrollAmount += 34
			}
		}

		eventCardsWrapper.value.scrollBy({
			left: scrollAmount,
			behavior: 'smooth',
		})
	}
}
</script>

<template>
	<div
		class="talk-dashboard-wrapper"
		:class="{
			'talk-dashboard-wrapper--mobile': isMobile,
			'talk-dashboard-wrapper--small-mobile': isSmallMobile,
		}">
		<div class="talk-dashboard__menu">
			<h2 class="talk-dashboard__header">
				{{ t('spreed', 'Hello, {displayName}', { displayName: actorStore.displayName }, { escape: false }) }}
			</h2>
			<div class="talk-dashboard__actions">
				<NcPopover
					v-if="canStartConversations"
					class="talk-dashboard__action"
					popupRole="dialog">
					<template #trigger>
						<NcButton
							v-if="isCallEnabled"
							variant="primary">
							<template #icon>
								<IconVideoOutline />
							</template>
							{{ t('spreed', 'Start meeting now') }}
						</NcButton>
					</template>
					<div
						role="dialog"
						aria-labelledby="instant_meeting_dialog"
						class="instant-meeting__dialog"
						aria-modal="true">
						<strong>{{ t('spreed', 'Give your meeting a title') }}</strong>
						<NcInputField
							id="room-name"
							v-model="conversationName"
							:placeholder="t('spreed', 'Meeting')"
							@keyup.enter="startMeeting" />
						<NcButton
							variant="primary"
							@click="startMeeting">
							{{ t('spreed', 'Create and copy link') }}
						</NcButton>
					</div>
				</NcPopover>
				<NcButton
					v-if="canStartConversations"
					class="talk-dashboard__action"
					@click="EventBus.emit('new-conversation-dialog:show')">
					<template #icon>
						<IconPlus :size="20" />
					</template>
					{{ t('spreed', 'New conversation') }}
				</NcButton>

				<NcButton
					class="talk-dashboard__action"
					@click="EventBus.emit('open-conversations-list:show')">
					<template #icon>
						<IconList :size="20" />
					</template>
					{{ t('spreed', 'Join conversations') }}
				</NcButton>

				<NcButton
					v-if="isCallEnabled && canModerateSipDialOut"
					class="talk-dashboard__action"
					@click="EventBus.emit('call-phone-dialog:show')">
					<template #icon>
						<IconPhoneOutline :size="20" />
					</template>
					{{ t('spreed', 'Call a phone number') }}
				</NcButton>
				<NcButton
					v-if="isCallEnabled"
					class="talk-dashboard__action"
					variant="secondary"
					@click="emit('talk:media-settings:show', 'device-check')">
					<template #icon>
						<IconMicrophoneOutline :size="20" />
					</template>
					{{ t('spreed', 'Check devices') }}
				</NcButton>
			</div>
		</div>
		<div class="talk-dashboard__items">
			<div class="event-section">
				<DashboardSection
					v-if="!eventsInitialised || eventRooms.length > 0"
					:title="t('spreed', 'Upcoming meetings')"
					:backgroundImage="illustration('meetings')">
					<template #list>
						<LoadingPlaceholder
							v-if="!eventsInitialised"
							type="event-cards" />
						<div
							v-else
							class="talk-dashboard__event-cards-wrapper"
							:class="{ 'forward-scrollable': forwardScrollable, 'backward-scrollable': backwardScrollable }">
							<div
								ref="eventCardsWrapper"
								class="talk-dashboard__event-cards"
								@scroll.passive="updateScrollableFlags">
								<EventCard
									v-for="eventRoom in eventRooms"
									:key="eventRoom.eventLink"
									:eventRoom="eventRoom"
									class="talk-dashboard__event-card" />
							</div>
							<div class="talk-dashboard__event-cards__scroll-indicator">
								<NcButton
									v-show="backwardScrollable"
									class="button-slide backward"
									variant="tertiary"
									:title="t('spreed', 'Scroll backward')"
									:aria-label="t('spreed', 'Scroll backward')"
									@click="scrollEventCards({ direction: 'backward' })">
									<template #icon>
										<IconArrowLeft class="bidirectional-icon" />
									</template>
								</NcButton>
								<NcButton
									v-show="forwardScrollable"
									class="button-slide forward"
									variant="tertiary"
									:title="t('spreed', 'Scroll forward')"
									:aria-label="t('spreed', 'Scroll forward')"
									@click="scrollEventCards({ direction: 'forward' })">
									<template #icon>
										<IconArrowRight class="bidirectional-icon" />
									</template>
								</NcButton>
							</div>
						</div>
					</template>
				</DashboardSection>
				<DashboardSection
					v-else
					class="event-section--empty"
					wide
					:title="t('spreed', 'Schedule meetings')"
					:subtitle="t('spreed', 'You don\'t have any upcoming meetings')"
					:description="t('spreed', 'Calendar events with a conversation link as the location are shown here')"
					:backgroundImage="illustration('meetings')">
					<template #action>
						<NcButton
							v-if="isCalendarEnabled"
							variant="secondary"
							:href="generateUrl('apps/calendar')"
							target="_blank">
							<template #icon>
								<IconCalendarBlankOutline :size="20" />
							</template>
							{{ t('spreed', 'Open calendar') }}
						</NcButton>
					</template>
				</DashboardSection>
			</div>
			<div class="talk-dashboard__chats">
				<div class="talk-dashboard__unread-mentions">
					<DashboardSection
						v-if="filteredConversations.length > 0 || !conversationsInitialised"
						:title="t('spreed', 'Unread mentions')"
						:backgroundImage="illustration('mentions')">
						<template #list>
							<ConversationsListVirtual
								class="talk-dashboard__conversations-list"
								:conversations="filteredConversations"
								:loading="!conversationsInitialised" />
						</template>
					</DashboardSection>
					<DashboardSection
						v-else
						:title="t('spreed', 'Unread mentions')"
						:description="t('spreed', 'Messages where you were mentioned will show up here. You can mention people by typing @ followed by their name')"
						:backgroundImage="illustration('mentions')" />
				</div>
				<div
					v-if="supportsUpcomingReminders"
					class="talk-dashboard__upcoming-reminders">
					<DashboardSection
						v-if="upcomingReminders.length > 0 || !remindersInitialised"
						:title="t('spreed', 'Upcoming reminders')"
						:backgroundImage="illustration('reminders')">
						<template #list>
							<ul v-if="remindersInitialised" class="upcoming-reminders-list">
								<SearchMessageItem
									v-for="reminder in upcomingReminders"
									:key="reminder.messageId"
									:messageId="reminder.messageId"
									:title="reminder.actorDisplayName"
									:subline="reminder.message"
									:messageParameters="reminder.messageParameters"
									:token="reminder.roomToken"
									:to="{
										name: 'conversation',
										params: { token: reminder.roomToken },
										hash: `#message_${reminder.messageId}`,
									}"
									:actorId="reminder.actorId"
									:actorType="reminder.actorType"
									:timestamp="reminder.reminderTimestamp"
									isReminder />
							</ul>
							<LoadingPlaceholder
								v-else
								class="upcoming-reminders__loading-placeholder"
								type="conversations" />
						</template>
					</DashboardSection>
					<DashboardSection
						v-else
						:title="t('spreed', 'Message reminders')"
						:description="t('spreed', 'Set a reminder on a message to be notified')"
						:backgroundImage="illustration('reminders')" />
				</div>
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
@use '../../assets/variables.scss' as *;

.talk-dashboard-wrapper {
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
	width: min(100%, calc(100vw - 300px - var(--body-container-margin) * 2)); // 300px for the left sidebar and body container margins
	margin: 0 auto;
	display: flex;
	flex-direction: column;
	height: 100%;
	max-height: 800px;
	max-width: 900px;

	&--mobile {
		width: 100%;

		.talk-dashboard__header {
			margin-block-start: 0;
			padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline)); // navigation button
		}
	}

	&--small-mobile {
		width: 100%;
		height: auto;
		max-height: none;

		.talk-dashboard__chats {
			grid-template-columns: 1fr;
			gap: calc(var(--default-grid-baseline) * 5);
		}
	}
}

.talk-dashboard__menu {
	margin-bottom: calc(var(--default-grid-baseline) * 4);
}

.talk-dashboard__header {
	font-size: 21px; // NcDialog header font size
	font-weight: bold;
	margin-inline: auto;
	margin-block: clamp(0px, calc(100vh - 800px), calc(var(--default-clickable-area) + var(--default-grid-baseline)))
		calc(var(--default-grid-baseline) * 2);
}

.talk-dashboard__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 3);
	padding-block: var(--default-grid-baseline);
	flex-wrap: wrap;
	flex-direction: row;

	:deep(.button-vue),
	:deep(.v-popper--theme-dropdown) {
		height: var(--header-menu-item-height);
		border-radius: var(--border-radius-large);
	}

	:deep(.button-vue) {
		padding-inline: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
	}
}

// Spread the actions across the full width of every row they wrap onto
.talk-dashboard__action {
	flex-grow: 1;

	// The popover only wraps its trigger, so the button inside has to stretch as well
	:deep(.button-vue) {
		width: 100%;
	}
}

.event-section {
	margin-block-end: calc(var(--default-grid-baseline) * 6);

	:deep(.dashboard-section--list) {
		.dashboard-section__content {
			padding-inline: 0;
		}

		.dashboard-section__title {
			padding-inline: var(--dashboard-section-inline-padding);
		}
	}

	&--empty {
		height: 225px;
	}
}

.talk-dashboard__event-cards {
	display: flex;
	flex-wrap: nowrap;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block: var(--default-grid-baseline);
	overflow-x: auto;
	scrollbar-width: none;

	// Keep the outermost cards off the edges of the section, in line with the section title
	> :first-child {
		margin-inline-start: var(--dashboard-section-inline-padding);
	}

	> :last-child {
		margin-inline-end: var(--dashboard-section-inline-padding);
	}
}

.talk-dashboard__event-cards-wrapper {
	position: relative;
	margin-bottom: calc(var(--default-grid-baseline) * 2);

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

	.button-slide {
		position: absolute !important;
		display: flex;
		top: calc(var(--default-grid-baseline) * 2);
		padding: 0;
		height: calc(100% - var(--default-grid-baseline) * 4);
		margin: 0 var(--default-grid-baseline) !important;
		z-index: 3;
		justify-content: left;
		background: var(--color-main-background);
		border-radius: var(--border-radius-large);
		box-shadow: 0 1px 5px rgba(var(--color-box-shadow-rgb), 0.2);

		&.backward {
			inset-inline-start: 0;
		}

		&.forward {
			inset-inline-end: 0;
		}
	}
}

.talk-dashboard__calendar-button {
	position: absolute !important;
	bottom: calc(var(--default-grid-baseline) * 2);
	inset-inline-start: calc(var(--default-grid-baseline) * 2);
}

.talk-dashboard__items {
	display: flex;
	flex-direction: column;
	justify-content: space-around;
	min-width: 0;
	flex-grow: 3;
}

.talk-dashboard__chats {
	display: grid;
	gap: calc(var(--default-grid-baseline) * 8);
	grid-template-columns: 1fr 1fr;
	flex-grow: 1;

	&> div {
		max-height: 320px;
	}
}

// In these two sections use main text color for a11y
.talk-dashboard__unread-mentions,
.talk-dashboard__upcoming-reminders {
	:deep(.list-item-content__subname) {
		color: var(--color-main-text);
	}
}

.talk-dashboard__unread-mentions {
	// Sit the text at the bottom of the card, clear of the illustration above it
	:deep(.dashboard-section__content) {
		justify-content: end;
	}
}

// Keep the text off the illustration on the trailing side of the card. Only on desktop,
// where the card is wide enough to spare the room.
.talk-dashboard-wrapper:not(.talk-dashboard-wrapper--mobile) {
	.talk-dashboard__unread-mentions,
	.talk-dashboard__upcoming-reminders {
		:deep(.dashboard-section__title),
		:deep(.dashboard-section__description) {
			max-width: 75%;
		}
	}
}

.upcoming-reminders {
	&-list {
		overflow-y: auto;
	}

	&__loading-placeholder {
		overflow: hidden;
	}
}

.talk-dashboard__conversations-list {
	flex-grow: 1;
	margin-block: var(--default-grid-baseline);
	line-height: 20px;
}

.instant-meeting__dialog {
	padding: calc(var(--default-grid-baseline) * 2);
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline) ;
	align-items: center;
}

// Override NcButton styles for narrow screen size
@media screen and (max-width: $breakpoint-mobile-small) {
	.talk-dashboard__actions {
		:deep(.button-vue),
		& > div {
			width: 100%;
		}

		:deep(.button-vue) {
			padding-inline-end: calc(var(--default-grid-baseline) * 2);
		}
	}
}
</style>
