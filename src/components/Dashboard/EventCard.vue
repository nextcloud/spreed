<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>

import { useNow } from '@vueuse/core'
import { computed } from 'vue'
import { useRouter } from 'vue-router/composables'

import IconCalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'
import IconTextBox from 'vue-material-design-icons/TextBox.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'

import { t, n, getCanonicalLocale } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import usernameToColor from '@nextcloud/vue/functions/usernameToColor'

import ConversationIcon from '../ConversationIcon.vue'

import IconTalk from '../../../img/app-dark.svg?raw'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import type { DashboardEventRoom } from '../../types/index.ts'
import { formattedTime } from '../../utils/formattedTime.ts'

const props = defineProps<{
	eventRoom: DashboardEventRoom,
}>()
const store = useStore()
const router = useRouter()
const isInCall = useIsInCall()
const elapsedTime = computed(() => {
	if (!hasCall.value || !(props.eventRoom.roomActiveSince ?? conversation.value.callStartTime)) {
		return ''
	}
	return formattedTime(+useNow({ interval: 1_000 }).value - (props.eventRoom.roomActiveSince ?? conversation.value.callStartTime) * 1000)
})

const isToday = computed(() => {
	return new Date(props.eventRoom.start * 1000).toDateString() === new Date().toDateString()
})

const conversation = computed(() => {
	return store.getters.conversation(props.eventRoom.roomToken)
})

const eventDateLabel = computed(() => {
	if (hasCall.value) {
		return t('spreed', 'Ongoing')
	}
	const startDate = new Date(props.eventRoom.start * 1000)
	const endDate = new Date(props.eventRoom.end * 1000)
	const startDateString = startDate.toLocaleString(getCanonicalLocale(), { hour: '2-digit', minute: '2-digit' })
	const endDateString = endDate.toLocaleString(getCanonicalLocale(), { hour: '2-digit', minute: '2-digit' })
	const dateString = isToday.value
		? t('spreed', 'Today')
		: moment(startDate).calendar(null, {
			nextDay: '[Tomorrow]',
			nextWeek: 'dddd',
			sameElse: 'dddd'
		})
	return t('spreed', '{dateString} {startDateString} - {endDateString}', {
		dateString,
		startDateString,
		endDateString,
	})
})

const hasAttachments = computed(() => {
	return Object.keys(props.eventRoom.eventAttachments).length > 0
})

const invitesLabel = computed(() => {
	const acceptedInvites = props.eventRoom.accepted ? n('spreed', '%n person accepted', '%n people accepted', props.eventRoom.accepted) : ''
	const declinedInvites = props.eventRoom.declined ? n('spreed', '%n person declined', '%n people declined', props.eventRoom.declined) : ''
	// FIXME should be a translated string ??
	return [acceptedInvites, declinedInvites].filter(Boolean).join(', ')
})

const hasCall = computed(() => {
	return (conversation.value.hasCall || props.eventRoom.roomActiveSince !== null)
		&& props.eventRoom.start * 1000 <= (Date.now() - 600_000) // 10 minutes buffer
})

/**
 * Redirects to the conversation page and opens media settings
 *
 * @param data object
 * @param data.call - if true, opens the media settings
 */
function handleJoin({ call = false } = {}) {
	router.push({
		name: 'conversation',
		params: { token: props.eventRoom.roomToken },
		hash: call ? '#direct-call' : undefined,
	})
}

</script>
<template>
	<div class="event-card"
		:class="{
			'event-card--highlighted': isToday,
			'event-card--in-call': hasCall,
		}">
		<span class="title">
			<span v-for="calendar in props.eventRoom.calendars"
				:key="calendar.principalUri"
				class="calendar-badge"
				:style="{ backgroundColor: calendar.calendarColor ?? usernameToColor(calendar.principalUri).color }" />
			<h4 class="title_text">
				{{ props.eventRoom.eventName }}
			</h4>
		</span>
		<p class="event-card__date secondary_text">
			{{ eventDateLabel }}
			<template v-if="hasCall">
				<IconVideo :size="20" :fill-color="'#E9322D'" />
				<span>{{ elapsedTime }}</span>
			</template>
		</p>
		<span class="event-card__room secondary_text">
			<span class="event-card__room-prefix">
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
		<span class="event-card__invitation-info initial">
			<span v-if="invitesLabel && !hasCall" class="secondary_text">
				{{ invitesLabel }}
			</span>
			<NcButton v-if="(hasCall && !isInCall)"
				type="primary"
				@click="handleJoin({call: true})">
				<template #icon>
					<IconVideo :size="20" />
				</template>
				{{ t('spreed', 'Join') }}
			</NcButton>
		</span>
		<span class="event-card__invitation-info hovered">
			<NcButton type="tertiary"
				@click="handleJoin">
				<template #icon>
					<NcIconSvgWrapper :svg="IconTalk" :size="20" />
				</template>
				{{ t('spreed', 'View conversation') }}
			</NcButton>
			<NcButton type="tertiary"
				:href="props.eventRoom.eventLink"
				target="_blank"
				:title="t('spreed', 'View event on Calendar')"
				:aria-label="t('spreed', 'View event on Calendar')">
				<template #icon>
					<IconCalendarBlank :size="20" />
				</template>
			</NcButton>
		</span>
	</div>
</template>
<style scoped lang="scss">
.event-card {
	position: relative;
	height: 225px;
	display: flex;
	flex-direction: column;
	flex: 0 0 100%;
	max-width: 300px;
	border: 3px solid var(--color-border);
	padding: calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius-large);

	&--highlighted {
		background-color: var(--color-primary-light);

		&:not(.event-card--in-call) {
			border-color: var(--color-primary-light) !important;
		}
	}

	&--in-call {
		border-color: var(--color-primary) !important;
	}

	&:not(.event-card--in-call):hover > .event-card__invitation-info.initial {
		display: none;
	}

	&:not(.event-card--in-call):hover > .event-card__invitation-info.hovered {
		display: flex;
	}

	&__date {
		display: flex;
		gap: 2px;

		& > * {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
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
		align-items: center;
		gap: var(--default-grid-baseline);

		&-prefix {
			line-height: var(--chip-size);
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			flex-shrink: 0;
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
		position: absolute;
		bottom: 0;
		inset-inline-start: 0;
		width: 100%;
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: calc(var(--default-grid-baseline) * 2);

		&.hovered {
			display: none;
		}
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
		font-size: inherit;
		margin: 0;
	}
}

.secondary_text {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
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
