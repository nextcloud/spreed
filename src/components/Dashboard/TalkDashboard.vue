<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts" setup>
import { computed, onMounted } from 'vue'

import { t } from '@nextcloud/l10n'

import EventCard from './EventCard.vue'

import { useTalkDashboardStore } from '../../stores/talkdashboard.ts'

const talkDashboardStore = useTalkDashboardStore()
const eventRooms = computed(() => talkDashboardStore.eventrooms)
onMounted(async () => {
	await talkDashboardStore.fetchDashboardEventRooms()
})

</script>
<template>
	<div class="talk-dashboard-wrapper">
		<div class="talk-dashboard__header">
			{{ t('spreed', 'Talk home') }}
		</div>
		<span class="title">{{ t('spreed', 'Upcoming meetings') }}</span>
		<div class="talk-dashboard__event-cards">
			<EventCard v-for="eventRoom in eventRooms"
				:key="eventRoom.eventLink"
				:event-room="eventRoom"
				class="talk-dashboard__event-card" />
		</div>
	</div>
</template>
<style lang="scss" scoped>
.talk-dashboard-wrapper {
	padding-inline: calc(var(--default-grid-baseline) * 2);
}

.talk-dashboard__header {
	font-size: 21px; // NcDialog header font size
	font-weight: bold;
	height: 51px; // top bar height
	line-height: 51px;
	text-align: center;
	margin: 0 auto;
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
	border-radius: var(--border-radius);
	flex: 0 0 calc(25% - var(--default-grid-baseline));
	max-width: 300px;
	min-width: 200px;
	border: 3px solid var(--color-border);
	padding: calc(var(--default-grid-baseline) * 2);
	scroll-snap-align: start;
}

.title {
	font-weight: bold;
}
</style>
