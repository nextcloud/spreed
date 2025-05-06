<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>

import { computed } from 'vue'

import IconTextBox from 'vue-material-design-icons/TextBox.vue'

import { t, n, getLocale } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcChip from '@nextcloud/vue/components/NcChip'
import usernameToColor from '@nextcloud/vue/functions/usernameToColor'

import ConversationIcon from '../ConversationIcon.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import type { DashboardEventRoom } from '../../types/index.ts'

// props
const props = defineProps<{
	eventRoom: DashboardEventRoom
}>()
const store = useStore()

const isToday = computed(() => {
	const startDate = new Date(props.eventRoom.start * 1000)
	const today = new Date()
	return startDate.toDateString() === today.toDateString()
})

const conversation = computed(() => {
	return store.getters.conversation(props.eventRoom.roomToken)
})

const eventDateLabel = computed(() => {
	const startDate = new Date(props.eventRoom.start * 1000)
	const endDate = new Date(props.eventRoom.end * 1000)
	const startDateString = startDate.toLocaleString(getLocale(), { hour: '2-digit', minute: '2-digit' })
	const endDateString = endDate.toLocaleString(getLocale(), { hour: '2-digit', minute: '2-digit' })
	const dateString = isToday.value
		? t('spreed', 'Today')
		: moment(startDate).calendar(null, {
			nextDay: '[Tomorrow]',
			nextWeek: 'dddd',
			sameElse: 'dddd'
		})
	return `${dateString} ${startDateString} - ${endDateString}`
})

const hasAttachments = computed(() => {
	return Object.keys(props.eventRoom.eventAttachments).length > 0
})

const invitesLabel = computed(() => {
	const acceptedInvites = props.eventRoom.accepted ? n('spreed', '%n person accepted', '%n people accepted', props.eventRoom.accepted) : ''
	const declinedInvites = props.eventRoom.declined ? n('spreed', '%n person declined', '%n people declined', props.eventRoom.declined) : ''
	const separator = acceptedInvites && declinedInvites ? ', ' : ''
	return `${acceptedInvites}${separator}${declinedInvites}`
})

</script>
<template>
	<div class="event-card"
		:class="{
			'event-card--highlighted': isToday,
		}">
		<span class="title">
			<span v-for="calendar in props.eventRoom.calendars"
				:key="calendar.principalUri"
				class="calendar-badge"
				:style="{ backgroundColor: calendar.calendarColor ?? usernameToColor(calendar.principalUri).color }" />
			<span class="title_text">
				{{ props.eventRoom.eventName }}
			</span>
		</span>
		<p class="secondary_text event-card__date">
			{{ eventDateLabel }}
		</p>
		<span class="event-card__room secondary_text">
			<span>
				{{ props.eventRoom.roomType === CONVERSATION.TYPE.ONE_TO_ONE ? t('spreed', 'With') : t('spreed', 'In') }}
			</span>
			<NcChip type="tertiary"
				no-close
				:text="props.eventRoom.roomDisplayName">
				<template #icon>
					<ConversationIcon :item="conversation"
						hide-user-status
						:size="20" />
				</template>
			</NcChip>
		</span>
		<span class="event-card__description">{{ props.eventRoom.eventDescription }}</span>
		<span v-if="hasAttachments" class="event-card__attachment">
			<IconTextBox :size="15" />
			<!--FIXME-->
			{{ Object.entries(props.eventRoom.eventAttachments)[0]?.[1]?.filename }}
		</span>
		<span class="event-card__invitation-info">
			{{ invitesLabel }}
		</span>
	</div>
</template>
<style scoped lang="scss">
.event-card {
	&--highlighted {
		background-color: var(--color-primary-light);
		border-color: var(--color-primary-light) !important;
	}

	&__date {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__description {
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: normal;
		margin-block: 8px;
	}

	&__room {
		display: flex;
		gap: 3px;

		& > span {
			line-height: var(--chip-size);
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}

	&__attachment {
		display: block;
		align-items: center;
		gap: var(--default-grid-baseline);
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		font-weight: 500;
		margin-block: var(--default-grid-baseline);

		& > * {
			display: inline-block;
			vertical-align: middle;
		}
	}

	&__invitation-info {
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
		padding-top: var(--default-grid-baseline);
	}
}

.title {
	display: flex;
	align-items: center;
	padding-inline-start: 6px; // revert negative margin
	gap: var(--default-grid-baseline);

	&_text {
		font-weight: bold;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.secondary_text {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.calendar-badge {
	display: inline-flex;
	width: var(--default-font-size);
	height: var(--default-font-size);
	border-radius: 50%;
	margin-inline-start: -6px; // negative margin to overlap
	position: relative;
	z-index: 1;
	box-shadow: 0 0 0 1px var(--color-main-background);
	flex-shrink: 0;
}

:deep(.nc-chip) {
	background-color: unset;
	overflow: hidden;
}
</style>
