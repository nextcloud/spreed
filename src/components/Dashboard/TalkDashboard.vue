<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { isRTL, t } from '@nextcloud/l10n'
import { generateUrl, imagePath } from '@nextcloud/router'
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
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useDashboardStore } from '../../stores/dashboard.ts'
import { hasUnreadMentions } from '../../utils/conversation.ts'
import { copyConversationLinkToClipboard } from '../../utils/handleUrl.ts'

const supportsUpcomingReminders = hasTalkFeature('local', 'upcoming-reminders')
const canModerateSipDialOut = hasTalkFeature('local', 'sip-support-dialout')
	&& getTalkConfig('local', 'call', 'sip-enabled')
	&& getTalkConfig('local', 'call', 'sip-dialout-enabled')
	&& getTalkConfig('local', 'call', 'can-enable-sip')
const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')
const isDirectionRTL = isRTL()
const isMobile = useIsMobile()
const isSmallMobile = useIsSmallMobile()

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
const filteredConversations = computed(() => store.getters.conversationsList.filter(hasUnreadMentions))

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
			objectId: Math.floor(Date.now() / 1000).toString(),
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
 * @param {string} direction - The direction to scroll ('backward' or 'forward').
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
	<div class="talk-dashboard-wrapper"
		:class="{
			'talk-dashboard-wrapper--mobile': isMobile,
			'talk-dashboard-wrapper--small-mobile': isSmallMobile,
		}">
		<div class="talk-dashboard__menu">
			<h2 class="talk-dashboard__header">
				{{ t('spreed', 'Hello, {displayName}', { displayName: actorStore.displayName }, { escape: false }) }}
			</h2>
			<div class="talk-dashboard__actions">
				<NcPopover v-if="canStartConversations"
					popup-role="dialog">
					<template #trigger>
						<NcButton variant="primary">
							<template #icon>
								<IconVideoOutline />
							</template>
							{{ t('spreed', 'Start meeting now') }}
						</NcButton>
					</template>
					<div role="dialog"
						aria-labelledby="instant_meeting_dialog"
						class="instant-meeting__dialog"
						aria-modal="true">
						<strong>{{ t('spreed', 'Give your meeting a title') }}</strong>
						<NcInputField id="room-name"
							v-model="conversationName"
							:placeholder="t('spreed', 'Meeting')" />
						<NcButton variant="primary"
							@click="startMeeting">
							{{ t('spreed', 'Create and copy link') }}
						</NcButton>
					</div>
				</NcPopover>
				<NcButton v-if="canStartConversations"
					@click="EventBus.emit('new-conversation-dialog:show')">
					<template #icon>
						<IconPlus :size="20" />
					</template>
					{{ t('spreed', 'Create a new conversation') }}
				</NcButton>

				<NcButton @click="EventBus.emit('open-conversations-list:show')">
					<template #icon>
						<IconList :size="20" />
					</template>
					{{ t('spreed', 'Join open conversations') }}
				</NcButton>

				<NcButton v-if="canModerateSipDialOut"
					@click="EventBus.emit('call-phone-dialog:show')">
					<template #icon>
						<IconPhoneOutline :size="20" />
					</template>
					{{ t('spreed', 'Call a phone number') }}
				</NcButton>
				<NcButton variant="secondary"
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
				<template v-if="eventsInitialised && eventRooms.length > 0">
					<h3 class="title">
						{{ t('spreed', 'Upcoming meetings') }}
					</h3>
					<div class="talk-dashboard__event-cards-wrapper"
						:class="{ 'forward-scrollable': forwardScrollable, 'backward-scrollable': backwardScrollable }">
						<div ref="eventCardsWrapper"
							class="talk-dashboard__event-cards"
							@scroll.passive="updateScrollableFlags">
							<EventCard v-for="eventRoom in eventRooms"
								:key="eventRoom.eventLink"
								:event-room="eventRoom"
								class="talk-dashboard__event-card" />
						</div>
						<div class="talk-dashboard__event-cards__scroll-indicator">
							<NcButton v-show="backwardScrollable"
								class="button-slide backward"
								variant="tertiary"
								:title="t('spreed', 'Scroll backward')"
								:aria-label="t('spreed', 'Scroll backward')"
								@click="scrollEventCards({ direction: 'backward' })">
								<template #icon>
									<IconArrowLeft class="bidirectional-icon" />
								</template>
							</NcButton>
							<NcButton v-show="forwardScrollable"
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
				<LoadingPlaceholder v-else-if="!eventsInitialised"
					type="event-cards" />
				<DashboardSection v-else
					class="event-section--empty"
					wide
					:title="t('spreed', 'Schedule meetings')"
					:subtitle="t('spreed', 'You don\'t have any upcoming meetings')"
					:description="t('spreed', 'Schedule a meeting from your calendar. A Talk conversation needs to be set as location to show up here')">
					<template #image>
						<img :src="imagePath('spreed', 'dashboard/meetings.png')">
					</template>
					<template #action>
						<NcButton
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
					<DashboardSection v-if="filteredConversations.length > 0 || !conversationsInitialised"
						:title="t('spreed', 'Unread mentions')">
						<template #list>
							<ConversationsListVirtual
								class="talk-dashboard__conversations-list"
								:conversations="filteredConversations"
								:loading="!conversationsInitialised" />
						</template>
					</DashboardSection>
					<DashboardSection v-else
						:title="t('spreed', 'Unread mentions')"
						:description="t('spreed', 'Messages where you were mentioned will show up here. You can mention people by typing @ followed by their name')">
						<template #image>
							<img :src="imagePath('spreed', 'dashboard/mentions.png')">
						</template>
					</DashboardSection>
				</div>
				<div v-if="supportsUpcomingReminders"
					class="talk-dashboard__upcoming-reminders">
					<DashboardSection v-if="upcomingReminders.length > 0 || !remindersInitialised"
						:title="t('spreed', 'Upcoming reminders')">
						<template #list>
							<ul v-if="remindersInitialised" class="upcoming-reminders-list">
								<SearchMessageItem v-for="reminder in upcomingReminders"
									:key="reminder.messageId"
									:message-id="reminder.messageId"
									:title="reminder.actorDisplayName"
									:subline="reminder.message"
									:message-parameters="reminder.messageParameters"
									:token="reminder.roomToken"
									:to="{
										name: 'conversation',
										params: { token: reminder.roomToken },
										hash: `#message_${reminder.messageId}`,
									}"
									:actor-id="reminder.actorId"
									:actor-type="reminder.actorType"
									:timestamp="reminder.reminderTimestamp"
									is-reminder />
							</ul>
							<LoadingPlaceholder v-else
								class="upcoming-reminders__loading-placeholder"
								type="conversations" />
						</template>
					</DashboardSection>
					<DashboardSection v-else
						:title="t('spreed', 'Message reminders')"
						:description="t('spreed', 'Set a reminder on a message to be notified')">
						<template #image>
							<img :src="imagePath('spreed', 'dashboard/reminders.png')">
						</template>
					</DashboardSection>
				</div>
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
@import '../../assets/variables';

.talk-dashboard-wrapper {
	padding: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 3);
	width: calc(100vw - 300px - var(--body-container-margin) * 2); // 300px for the left sidebar and body container margins
	margin: 0 auto;
	display: flex;
	flex-direction: column;
	height: 100%;
	max-height: 800px;
	max-width: 1200px;

	&--mobile {
		width: 100%;
	}

	&--small-mobile {
		width: 100%;
		height: auto;

		.talk-dashboard__chats {
			grid-template-columns: 1fr;
			gap: calc(var(--default-grid-baseline) * 6);
		}
	}
}

.talk-dashboard__menu {
	margin-bottom: calc(var(--default-grid-baseline) * 5);
}

.talk-dashboard__header {
	font-size: 21px; // NcDialog header font size
	font-weight: bold;
	margin: 0 auto calc(var(--default-grid-baseline) * 2);
	padding-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline)); // navigation button
}

.talk-dashboard__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 3);
	padding-block: var(--default-grid-baseline);
	flex-wrap: wrap;
	justify-content: space-evenly;

	:deep(.button-vue),
	:deep(.v-popper--theme-dropdown) {
		height: var(--header-menu-item-height);
		border-radius: var(--border-radius-large);
		flex: 1;
		width: 100%;
	}

	:deep(.button-vue) {
		padding-inline: calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 4);
	}
}

.event-section {
	margin-block-end: calc(var(--default-grid-baseline) * 6);

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
	border-radius: var(--border-radius-large);
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
		top: 0;
		padding: 0;
		height: 100%;
		margin: 0 !important;
		z-index: 3;
		justify-content: left;
		background: var(--color-main-background);
		border-radius: var(--border-radius-large);

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
		max-height: 360px;
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
	margin: var(--default-grid-baseline) 0;
	height: 225px;
	line-height: 20px;
}

.title {
	font-size: 1.25rem;
    font-weight: bold;
	margin-block: 0 calc(var(--default-grid-baseline) * 2);
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
		:deep(.v-popper--theme-dropdown) {
			flex: initial;
		}

		:deep(.button-vue) {
			padding-inline-end: calc(var(--default-grid-baseline) * 2);
		}
	}
}
</style>
