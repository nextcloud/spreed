<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import IconAccount from 'vue-material-design-icons/Account.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'
import IconOfficeBuilding from 'vue-material-design-icons/OfficeBuilding.vue'

import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppSidebarHeader from '@nextcloud/vue/components/NcAppSidebarHeader'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'

import CalendarEventSmall from '../UIShared/CalendarEventSmall.vue'

import { useStore } from '../../composables/useStore.js'
import { CONVERSATION } from '../../constants.ts'
import { getConversationAvatarOcsUrl } from '../../services/avatarService.ts'
import { useGroupwareStore } from '../../stores/groupware.ts'
import type {
	Conversation,
	DashboardEvent,
	UserProfileData,
} from '../../types/index.ts'
import { getFallbackIconClass } from '../../utils/conversation.ts'
import { convertToUnix } from '../../utils/formattedTime.ts'

type MutualEvent = {
	uri: DashboardEvent['eventLink'],
	name: DashboardEvent['eventName'],
	start: string,
	href: DashboardEvent['eventLink'],
	color: string,
}

const props = defineProps<{
	isUser: boolean,
	state: 'default' | 'search',
	mode: 'compact' | 'preview' | 'full',
}>()

const emit = defineEmits<{
	(event: 'update:search', value: boolean): void
	(event: 'update:mode', value: 'compact' | 'preview' | 'full'): void
}>()

const store = useStore()
const groupwareStore = useGroupwareStore()

const isDarkTheme = useIsDarkTheme()

const profileLoading = ref(false)
const profileImageFailed = ref(false)

const token = computed(() => store.getters.getToken())

const conversation = computed(() => store.getters.conversation(token.value) as Conversation)
const isOneToOneConversation = computed(() => conversation.value.type === CONVERSATION.TYPE.ONE_TO_ONE)

const profileInfo = computed(() => groupwareStore.profileInfo[token.value])
const profileActions = computed<UserProfileData['actions']>(() => {
	if (!profileInfo.value) {
		return []
	}
	return profileInfo.value.actions.filter(action => action.id !== 'talk')
})

const sidebarTitle = computed(() => {
	if (props.state === 'search') {
		return t('spreed', 'Search in {name}', { name: conversation.value.displayName }, {
			escape: false,
			sanitize: false,
		})
	}
	return conversation.value.displayName
})

const iconClass = computed(() => getFallbackIconClass(conversation.value, profileImageFailed.value))

const avatarUrl = computed(() => {
	if (iconClass.value) {
		return undefined
	}

	return isOneToOneConversation.value
		? generateUrl('avatar/{userId}/512' + (isDarkTheme.value ? '/dark' : ''), { userId: conversation.value.name })
		: getConversationAvatarOcsUrl(token.value, isDarkTheme.value, conversation.value.avatarVersion)
})

const profileInformation = computed(() => {
	if (!profileInfo.value) {
		return []
	}

	const fields = []

	if (profileInfo.value.role || profileInfo.value.pronouns) {
		fields.push({
			key: 'person',
			icon: IconAccount,
			label: joinFields(profileInfo.value.role, profileInfo.value.pronouns)
		})
	}
	if (profileInfo.value.organisation || profileInfo.value.address) {
		fields.push({
			key: 'organisation',
			icon: IconOfficeBuilding,
			label: joinFields(profileInfo.value.organisation, profileInfo.value.address)
		})
	}

	const currentTime = moment(new Date().setSeconds(new Date().getTimezoneOffset() * 60 + profileInfo.value.timezoneOffset))
	fields.push({
		key: 'timezone',
		icon: IconClockOutline,
		label: t('spreed', 'Local time: {time}', { time: currentTime.format('LT') })
	})

	return fields
})

const mutualEventsInformation = computed<MutualEvent[]>(() => {
	if (!groupwareStore.mutualEvents[token.value]) {
		return []
	}

	const now = convertToUnix(Date.now())
	return groupwareStore.mutualEvents[token.value].map(event => {
		const start = event.start
			? (event.start <= now) ? t('spreed', 'Now') : moment(event.start * 1000).calendar()
			: ''
		return {
			uri: event.eventLink,
			name: event.eventName,
			start,
			href: event.eventLink,
			color: event.calendars[0]?.calendarColor ?? 'var(--color-primary)',
		}
	})
})

watch(token, async () => {
	profileLoading.value = true
	await Promise.all([
		groupwareStore.getUserProfileInformation(conversation.value),
		groupwareStore.getUserMutualEvents(conversation.value),
	])
	profileLoading.value = false
}, { immediate: true })

/**
 * Concatenates profile strings
 * @param firstSubstring first part of string
 * @param secondSubstring second part of string
 */
function joinFields(firstSubstring?: string | null, secondSubstring?: string | null): string {
	return [firstSubstring, secondSubstring].filter(Boolean).join(' · ')
}

/**
 * Handles image load error
 */
function onError() {
	profileImageFailed.value = true
}
</script>

<template>
	<div class="content"
		:class="{['content--' + mode]: state === 'default'}">
		<template v-if="state === 'default'">
			<div v-if="isUser" class="content__actions">
				<NcActions v-if="profileActions.length" force-menu>
					<NcActionLink v-for="action in profileActions"
						:key="action.id"
						class="content__profile-action"
						:icon="action.icon"
						:href="action.target"
						close-after-click>
						{{ action.title }}
					</NcActionLink>
				</NcActions>
				<NcButton type="tertiary"
					:title="t('spreed', 'Search messages')"
					:aria-label="t('spreed', 'Search messages')"
					@click="emit('update:search', true)">
					<template #icon>
						<IconMagnify :size="20" />
					</template>
				</NcButton>
			</div>

			<div class="content__scroller animated">
				<!-- User / conversation avatar image -->
				<div class="content__image-wrapper animated">
					<div v-if="iconClass"
						class="content__image animated icon"
						:class="iconClass" />
					<img v-else
						class="content__image animated"
						:src="avatarUrl"
						:alt="conversation.displayName"
						@error="onError"
						@click="mode === 'preview' && emit('update:mode', 'full')">
				</div>
				<!-- User / conversation profile information -->
				<div class="content__header">
					<NcAppSidebarHeader class="content__name content__name--has-actions"
						:class="{ 'content__name--has-profile-actions': profileActions.length }"
						:name="sidebarTitle"
						:title="sidebarTitle" />
					<div v-if="mode !== 'compact' && profileInformation.length"
						class="content__info">
						<p v-for="row in profileInformation"
							:key="row.key"
							class="content__info-row">
							<component :is="row.icon" :size="16" />
							{{ row.label }}
						</p>
					</div>
				</div>
			</div>
			<div v-if="mode !== 'compact' && mutualEventsInformation.length"
				class="content__events">
				<NcAppNavigationCaption :name="t('spreed', 'Upcoming meetings')" />
				<ul class="content__events-list">
					<CalendarEventSmall v-for="event in mutualEventsInformation"
						:key="event.uri"
						:name="event.name"
						:start="event.start"
						:href="event.href"
						:color="event.color" />
				</ul>
			</div>
		</template>

		<!-- Search messages in this conversation -->
		<template v-else-if="isUser && state === 'search'">
			<div class="content__header content__header--row">
				<NcButton type="tertiary"
					:title="t('spreed', 'Back')"
					:aria-label="t('spreed', 'Back')"
					@click="emit('update:search', false)">
					<template #icon>
						<IconArrowLeft class="bidirectional-icon" :size="20" />
					</template>
				</NcButton>

				<NcAppSidebarHeader class="content__name"
					:name="sidebarTitle"
					:title="sidebarTitle" />
			</div>
		</template>
	</div>
</template>

<style lang="scss" scoped>
.content {
	.animated,
	.animated::after {
		transition-property: inset, padding, width, height, border-radius, opacity;
		transition-duration: var(--animation-slow);
		transition-timing-function: ease-in-out;
	}

	&--compact {
		// default
		.content__image-wrapper {
			width: 0;
			height: 0;
			padding: 0;

			.content__image {
				opacity: 0;
			}
		}
	}

	&--preview {
		// avatar on the left
		.content__scroller {
			flex-wrap: wrap;
		}

		.content__header {
			width: 75%;
		}

		.content__image-wrapper {
			width: 25%;
			height: 25%;
			padding: var(--default-grid-baseline);
			padding-block-start: calc(4 * var(--default-grid-baseline));

			&::after {
				inset-block-start: calc(4 * var(--default-grid-baseline)) !important;
				height: calc(100% - 5 * var(--default-grid-baseline)) !important;
			}
		}
	}

	&--full {
		// avatar in full size
		.content__scroller {
			flex-direction: column;
			align-items: start;
		}

		.content__header {
			padding-inline: calc(2 * var(--default-grid-baseline));
		}

		.content__name {
			padding-inline-end: 0 !important;
		}

		.content__image-wrapper {
			width: 100%;
			height: 100%;
			padding: 0;

			&::after {
				width: 100% !important;
				height: 100% !important;
				inset: 0 !important;
				border-radius: 0 !important;
				opacity: 1 !important;
			}

			.content__image {
				border-radius: 0;
			}
		}

		// Overwrite NcButton styles
		& :deep(.button-vue--icon-only),
		& ~ :deep(.button-vue--icon-only) {
			filter: invert(1);
		}

		// Do not overwrite for dark theme
		body[data-theme-dark] & :deep(.button-vue--icon-only),
		body[data-theme-dark] & ~ :deep(.button-vue--icon-only) {
			filter: none;
		}

		@media (prefers-color-scheme: dark) {
			body[data-theme-default] & :deep(.button-vue--icon-only),
			body[data-theme-default] & ~ :deep(.button-vue--icon-only) {
				filter: none;
			}
		}
	}

	&__scroller {
		display: flex;
	}

	.content__image-wrapper {
		position: relative;
		flex-shrink: 0;
		max-height: var(--app-sidebar-width);
		overflow: hidden;

		&::after {
			position: absolute;
			inset: var(--default-grid-baseline);
			content: '';
			z-index: 1;
			width: calc(100% - 2 * var(--default-grid-baseline));
			height: calc(100% - 2 * var(--default-grid-baseline));
			border-radius: 50%;
			background: linear-gradient(180deg, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0) 30%);
			opacity: 0;
			pointer-events: none;
		}
	}

	.content__image {
		display: block;
		max-width: 100%;
		max-height: 100%;
		width: 100%;
		height: 100%;
		aspect-ratio: 1;
		border-radius: 50%;
		object-fit: cover;
		object-position: top;

		&.icon {
			background-size: 50%;
			background-color: var(--color-text-maxcontrast-default);
		}
	}

	&__header {
		flex-grow: 1;
		display: flex;
		flex-direction: column;
		align-items: start;
		gap: var(--default-grid-baseline);
		width: 100%;
		padding-block: calc(2 * var(--default-grid-baseline)) var(--default-grid-baseline);
		padding-inline-start: calc(2 * var(--default-grid-baseline));

		&--row {
			flex-direction: row;
			align-items: center;
		}

		.content__name {
			--actions-offset: calc(var(--default-grid-baseline) + var(--default-clickable-area));
			width: 100%;
			margin: 0;
			padding-inline-end: var(--app-sidebar-close-button-offset);
			font-size: 20px;
			line-height: var(--default-clickable-area);
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;

			&--has-actions {
				padding-inline-end: calc(var(--actions-offset) + var(--app-sidebar-close-button-offset));
			}

			&--has-profile-actions {
				padding-inline-end: calc(2 * var(--actions-offset) + var(--app-sidebar-close-button-offset));
			}
		}

		.content__info {
			display: flex;
			flex-direction: column;
			gap: var(--default-grid-baseline);
			padding-inline-end: var(--default-grid-baseline);

			&-row {
				display: flex;
				gap: var(--default-grid-baseline);
			}
		}
	}

	&__actions {
		position: absolute !important;
		z-index: 2;
		top: calc(2 * var(--default-grid-baseline));
		inset-inline-end: calc(var(--default-grid-baseline) + var(--app-sidebar-close-button-offset));
		display: flex;
		gap: var(--default-grid-baseline);

		// Copy opaque styles of close button
		:deep(.button-vue--icon-only),
		:deep(.action-item) {
			opacity: 0.7;

			&:hover,
			&:focus,
			&:active,
			&.action-item--open {
				opacity: 1;
			}
		}
	}

	&__events {
		// To align with NcAppSidebarTab content width
		margin-inline: 10px;

		&-list {
			--item-height: calc(2lh + var(--default-grid-baseline) * 3);
			display: flex;
			flex-direction: column;
			margin: calc(var(--default-grid-baseline) / 2);
			line-height: 20px;
			max-height: calc(4.5 * var(--item-height) + 4 * var(--default-grid-baseline));
			overflow-y: auto;

			& > * {
				margin-inline: calc(var(--default-grid-baseline) / 2);

				&:not(:last-child) {
					border-bottom: 1px solid var(--color-border-dark);
				}
			}
		}
	}

	&__profile-action {
		// Override NcActionLink styles
		:deep(.action-link__longtext) {
			white-space: nowrap !important;
		}
	}
}
</style>
