<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router/composables'

import IconMicrophone from 'vue-material-design-icons/Microphone.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import EventCard from './EventCard.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import { useTalkDashboardStore } from '../../stores/talkdashboard.ts'

const store = useStore()
const router = useRouter()
const talkDashboardStore = useTalkDashboardStore()
const eventRooms = computed(() => talkDashboardStore.eventrooms)
onMounted(async () => {
	await talkDashboardStore.fetchDashboardEventRooms()
})

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
		<div class="talk-dashboard__event-cards">
			<EventCard v-for="eventRoom in eventRooms"
				:key="eventRoom.eventLink"
				:event-room="eventRoom"
				class="talk-dashboard__event-card" />
		</div>
		<NcButton class="talk-dashboard__devices-button"
			type="tertiary"
			@click="emit('talk:media-settings:show', 'device-check')">
			<template #icon>
				<IconMicrophone :size="20" />
			</template>
			{{ t('spreed', 'Check devices') }}
		</NcButton>
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

.title {
	font-weight: bold;
}
</style>
