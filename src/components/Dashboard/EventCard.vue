<!--
	- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
	- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script lang="ts" setup>

import type { Conversation, DashboardEventRoom } from '../../types/index.ts'

import { getCanonicalLocale, getLanguage, n, t } from '@nextcloud/l10n'
import { imagePath } from '@nextcloud/router'
import { usernameToColor } from '@nextcloud/vue/functions/usernameToColor'
import { useNow } from '@vueuse/core'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import IconCalendarBlankOutline from 'vue-material-design-icons/CalendarBlankOutline.vue'
import IconVideo from 'vue-material-design-icons/Video.vue' // Filled for better indication
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import ConversationIcon from '../ConversationIcon.vue'
import IconTalk from '../../../img/app-dark.svg?raw'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { CONVERSATION } from '../../constants.ts'
import { formattedTime, ONE_DAY_IN_MS } from '../../utils/formattedTime.ts'

type ConversationFromEvent = Pick<Conversation, 'token' | 'type' | 'name' | 'displayName' | 'avatarVersion' | 'callStartTime' | 'hasCall'>

const props = defineProps<{
	eventRoom: DashboardEventRoom
}>()
const store = useStore()
const router = useRouter()
const isInCall = useIsInCall()

const conversation = computed<ConversationFromEvent>(() => {
	return store.getters.conversation(props.eventRoom.roomToken) ?? {
		token: props.eventRoom.roomToken,
		type: props.eventRoom.roomType,
		name: props.eventRoom.roomName,
		displayName: props.eventRoom.roomDisplayName,
		avatarVersion: props.eventRoom.roomAvatarVersion,
		callStartTime: props.eventRoom.roomActiveSince ?? 0,
		hasCall: props.eventRoom.roomActiveSince !== null,
	}
})

const hasCall = computed(() => {
	return (conversation.value.hasCall || props.eventRoom.roomActiveSince !== null)
		&& props.eventRoom.start * 1000 >= (Date.now() - 600_000) // 10 minutes buffer
})

const elapsedTime = computed(() => {
	if (!hasCall.value || !(props.eventRoom.roomActiveSince ?? conversation.value.callStartTime)) {
		return ''
	}
	return formattedTime(+useNow({ interval: 1_000 }).value - (props.eventRoom.roomActiveSince ?? conversation.value.callStartTime) * 1000)
})

const isToday = computed(() => {
	return new Date(props.eventRoom.start * 1000).toDateString() === new Date().toDateString()
})

const eventDateLabel = computed(() => {
	if (hasCall.value) {
		return t('spreed', 'Ongoing')
	}
	const startDate = new Date(props.eventRoom.start * 1000)
	const endDate = new Date(props.eventRoom.end * 1000)
	const isTomorrow = startDate.toDateString() === new Date(Date.now() + ONE_DAY_IN_MS).toDateString()

	let time
	if (startDate.toDateString() === endDate.toDateString()) {
		if (isToday.value || isTomorrow) {
			// show the time only
			const timeRange = Intl.DateTimeFormat(getCanonicalLocale(), {
				hour: 'numeric',
				minute: 'numeric',
			}).formatRange(startDate, endDate)

			const relativeFormatter = new Intl.RelativeTimeFormat(getLanguage(), { numeric: 'auto' })

			// TRANSLATORS: e.g. "Tomorrow 10:00 - 11:00"
			time = t('spreed', '{dayPrefix} {dateTime}', {
				dayPrefix: isToday.value ? relativeFormatter.format(0, 'day') : relativeFormatter.format(1, 'day'),
				dateTime: timeRange,
			})
		} else {
			time = Intl.DateTimeFormat(getCanonicalLocale(), {
				weekday: 'long',
				hour: 'numeric',
				minute: 'numeric',
			}).formatRange(startDate, endDate)
		}
	} else {
		// show the month and the year as well
		time = Intl.DateTimeFormat(getCanonicalLocale(), {
			month: 'long',
			year: 'numeric',
			day: '2-digit',
			hour: 'numeric',
			minute: 'numeric',
		}).formatRange(startDate, endDate)
	}

	return time
})

const totalAttachments = computed(() => Object.values(props.eventRoom.eventAttachments))

const invitesLabel = computed(() => {
	const acceptedInvites = props.eventRoom.accepted ? n('spreed', '%n person accepted', '%n people accepted', props.eventRoom.accepted) : ''
	const declinedInvites = props.eventRoom.declined ? n('spreed', '%n person declined', '%n people declined', props.eventRoom.declined) : ''
	// FIXME should be a translated string ??
	return [acceptedInvites, declinedInvites].filter(Boolean).join(', ')
})

const attachmentInfo = computed(() => {
	if (!totalAttachments.value.length) {
		return null
	}
	const file = totalAttachments.value[0]

	return {
		icon: OC.MimeType.getIconUrl(file.fmttype) || imagePath('core', 'filetypes/file'),
		label: file.filename.replace(/^\//, ''),
		extraLabel: totalAttachments.value.length > 1
			? n('spreed', 'and %n other attachment', 'and %n other attachments', totalAttachments.value.length - 1)
			: '',
		url: file.previewLink ?? undefined,
	}
})

const roomLabel = computed(() => {
	return props.eventRoom.roomType === CONVERSATION.TYPE.ONE_TO_ONE
		? t('spreed', 'With {displayName}', { displayName: props.eventRoom.roomDisplayName }, { escape: false, sanitize: false })
		: t('spreed', 'In {conversation}', { conversation: props.eventRoom.roomDisplayName }, { escape: false, sanitize: false })
})

/**
 * Redirects to the conversation page and opens media settings
 *
 * @param data object
 * @param data.call - if true, opens the media settings
 */
function handleJoin({ call }: { call: boolean }) {
	router.push({
		name: 'conversation',
		params: { token: props.eventRoom.roomToken },
		hash: call ? '#direct-call' : undefined,
	})
}

</script>

<template>
	<div
		class="event-card"
		:class="{
			'event-card--highlighted': isToday,
			'event-card--in-call': hasCall,
		}">
		<h4 class="title">
			<span
				v-for="calendar in props.eventRoom.calendars"
				:key="calendar.principalUri"
				class="calendar-badge"
				:style="{ backgroundColor: calendar.calendarColor ?? usernameToColor(calendar.principalUri).color }" />
			<span class="title_text">
				{{ props.eventRoom.eventName }}
			</span>
		</h4>
		<p class="event-card__date secondary_text">
			<span>{{ eventDateLabel }}</span>
			<template v-if="hasCall">
				<IconVideo :size="20" fill-color="var(--color-border-error)" />
				<span>{{ elapsedTime }}</span>
			</template>
		</p>
		<span class="event-card__room secondary_text">
			<NcChip
				variant="tertiary"
				:text="roomLabel"
				no-close>
				<template #icon>
					<ConversationIcon
						:item="conversation"
						hide-user-status
						:size="20" />
				</template>
			</NcChip>
		</span>
		<span class="event-card__description">{{ props.eventRoom.eventDescription }}</span>
		<template v-if="attachmentInfo">
			<a
				class="event-card__attachment"
				role="link"
				:href="attachmentInfo.url"
				:title="t('spreed', 'View attachment')"
				target="_blank">
				<img
					class="file-preview__image"
					:alt="attachmentInfo.label"
					:src="attachmentInfo.icon">
				<span> {{ attachmentInfo.label }} </span>
			</a>
			<span v-if="attachmentInfo.extraLabel" class="secondary_text">
				{{ attachmentInfo.extraLabel }}
			</span>
		</template>
		<span class="event-card__invitation-info">
			<span v-if="invitesLabel && !hasCall" class="secondary_text">
				{{ invitesLabel }}
			</span>
			<NcButton
				v-if="(hasCall && !isInCall)"
				variant="primary"
				@click="handleJoin({ call: true })">
				<template #icon>
					<IconVideoOutline :size="20" />
				</template>
				{{ t('spreed', 'Join') }}
			</NcButton>
		</span>
		<span class="event-card__invitation-info hovered">
			<NcButton
				variant="tertiary"
				@click="handleJoin({ call: false })">
				<template #icon>
					<NcIconSvgWrapper :svg="IconTalk" :size="20" />
				</template>
				{{ t('spreed', 'View conversation') }}
			</NcButton>
			<NcButton
				variant="tertiary"
				:href="props.eventRoom.eventLink"
				target="_blank"
				:title="t('spreed', 'View event on Calendar')"
				:aria-label="t('spreed', 'View event on Calendar')">
				<template #icon>
					<IconCalendarBlankOutline :size="20" />
				</template>
			</NcButton>
		</span>
	</div>
</template>

<style scoped lang="scss">
.event-card {
	position: relative;
	height: 250px;
	display: flex;
	flex-direction: column;
	flex: 0 0 100%;
	max-width: 300px;
	border: 2px solid var(--color-border);
	padding: calc(var(--default-grid-baseline) * 2);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);

	&--highlighted {
		background-color: var(--color-primary-light);

		&:not(.event-card--in-call) {
			border-color: var(--color-primary-element-light-hover) !important;
		}
	}

	&--in-call {
		border-color: var(--color-primary) !important;
	}

	&:not(.event-card--in-call):hover > .event-card__invitation-info.hovered {
		display: flex;
		background-color: inherit;
	}

	&__date {
		display: flex;
		gap: 2px;

		& > span::first-letter {
			text-transform: capitalize;
		}

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
		margin-block: calc(var(--default-grid-baseline) * 2);
	}

	&__room {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
	}

	&__attachment {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		margin-block-start: var(--default-grid-baseline);
		padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) / 2);

		& > span {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			font-weight: 500;
			font-size: var(--font-size-small);
		}

		&:hover {
			background-color: var(--color-background-hover);
			border-radius: var(--border-radius-large);
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
		border-radius: var(--border-radius-large);
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
	font-size: inherit;
	margin: 0;

	&_text {
		font-weight: bold;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.secondary_text {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small);
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
