<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router/composables'

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
import LoadingPlaceholder from '../UIShared/LoadingPlaceholder.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import { useTalkDashboardStore } from '../../stores/talkdashboard.ts'
import type { Conversation } from '../../types/index.ts'
import { filterConversation } from '../../utils/conversation.ts'

const FIVE_MINUTES = 5 * 60 * 1000 // 5 minutes
const store = useStore()
const router = useRouter()
const talkDashboardStore = useTalkDashboardStore()

const eventRooms = computed(() => talkDashboardStore.eventrooms)
const eventsInitialised = computed(() => talkDashboardStore.eventRoomsInitialised)
const fetchEventRoomsInterval = ref<ReturnType<typeof setInterval> | null>(null)
onMounted(async () => {
	fetchEventRooms()
	fetchEventRoomsInterval.value = setInterval(fetchEventRooms, FIVE_MINUTES)
})

onBeforeUnmount(() => {
	if (fetchEventRoomsInterval.value) {
		clearInterval(fetchEventRoomsInterval.value)
	}
})

const conversationsList = computed(() => store.getters.conversationsList)
const conversationsInitialised = computed(() => store.getters.conversationsInitialised)
const filteredConversations = computed(() => conversationsList.value?.filter((conversation : Conversation) => {
	return filterConversation(conversation, ['mentions'])
}))

/**
 * Fetches the event rooms
 */
async function fetchEventRooms() {
	try {
		await talkDashboardStore.fetchDashboardEventRooms()
	} catch (error) {
		showError(t('spreed', 'Error fetching event rooms'))
	}
}

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
</script>
<template>
	<div class="talk-dashboard-wrapper">
		<div class="talk-dashboard__header">
			{{ t('spreed', 'Talk home') }}
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
		<div v-if="eventsInitialised && eventRooms.length > 0" class="talk-dashboard__event-cards">
			<EventCard v-for="eventRoom in eventRooms"
				:key="eventRoom.eventLink"
				:event-room="eventRoom"
				class="talk-dashboard__event-card" />
		</div>
		<LoadingPlaceholder v-else-if="!eventsInitialised"
			class="event-cards__loading-placeholder"
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
	</div>
</template>
<style lang="scss" scoped>
@import '../../assets/variables';

.talk-dashboard-wrapper {
	padding-inline: calc(var(--default-grid-baseline) * 2);
	max-width: calc($messages-list-max-width + 400px); // FIXME: to change to a readable value
	margin: 0 auto;
}

.talk-dashboard__header {
	font-size: 21px; // NcDialog header font size
	font-weight: bold;
	height: 51px; // top bar height
	line-height: 51px;
	text-align: center;
	margin: 0 auto;
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
	scroll-snap-type: x mandatory; // Smooth snapping for scrolling
	padding-inline: calc(var(--default-grid-baseline) * 4);
}

.talk-dashboard__event-card {
	flex: 0 0 calc(25% - var(--default-grid-baseline));
	scroll-snap-align: start;
}

.talk-dashboard__devices-button {
	margin: calc(var(--default-grid-baseline) * 4);
}

.talk-dashboard__unread-mentions {
	max-height: 300px;
	width: 300px;
	overflow-y: auto;

	& > .title {
		display: block;
		height: var(--default-clickable-area);
		margin : var(--default-grid-baseline);
	}

	&.loading {
		overflow: hidden;
	}
}

.talk-dashboard__empty-content {
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius);
	padding: calc(var(--default-grid-baseline) * 2);
	margin: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 4);
}

.talk-dashboard__conversations-list {
	margin: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 4);
	height: 100%;
	line-height: 20px;
}

.title {
	font-weight: bold;
}
</style>
