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

import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAppSidebarHeader from '@nextcloud/vue/components/NcAppSidebarHeader'
import NcButton from '@nextcloud/vue/components/NcButton'

import { CONVERSATION } from '../../constants.ts'
import { getUserProfile } from '../../services/coreService.ts'
import type {
	ApiErrorResponse,
	Conversation,
	UserProfileData,
} from '../../types/index.ts'

const props = defineProps<{
	isUser: boolean,
	conversation: Conversation,
	state: 'default' | 'search',
	mode: 'compact' | 'preview' | 'full',
}>()

const emit = defineEmits<{
	(event: 'update:search', value: boolean): void
}>()

// FIXME cache in store until reload
let isGetProfileAllowed = true

const profileLoading = ref(false)

// FIXME cache in store to make less requests
const profileData = ref<UserProfileData | null>(null)
const profileActions = computed<UserProfileData['actions']>(() => {
	if (!profileData.value) {
		return []
	}
	return profileData.value.actions.filter(action => action.id !== 'talk')
})

const isOneToOneConversation = computed(() => [CONVERSATION.TYPE.ONE_TO_ONE, CONVERSATION.TYPE.ONE_TO_ONE_FORMER].includes(props.conversation.type))

const sidebarTitle = computed(() => {
	if (props.state === 'search') {
		return t('spreed', 'Search in {name}', { name: props.conversation.displayName }, {
			escape: false,
			sanitize: false,
		})
	}
	return props.conversation.displayName
})

const profileInformation = computed(() => {
	if (!profileData.value) {
		return []
	}

	const fields = []

	if (profileData.value.role || profileData.value.pronouns) {
		fields.push({
			key: 'person',
			icon: IconAccount,
			label: joinFields(profileData.value.role, profileData.value.pronouns)
		})
	}
	if (profileData.value.organisation || profileData.value.address) {
		fields.push({
			key: 'organisation',
			icon: IconOfficeBuilding,
			label: joinFields(profileData.value.organisation, profileData.value.address)
		})
	}

	const currentTime = moment(new Date().setSeconds(new Date().getTimezoneOffset() * 60 + profileData.value.timezoneOffset))
	fields.push({
		key: 'timezone',
		icon: IconClockOutline,
		label: t('spreed', 'Local time: {time}', { time: currentTime.format('LT') })
	})

	return fields
})

watch(() => props.conversation.token, () => {
	if (isOneToOneConversation.value) {
		getUserProfileInformation(props.conversation.name)
	} else {
		profileData.value = null
	}
}, { immediate: true })

/**
 * Request and parse profile information
 * @param userId internal user identifier
 */
async function getUserProfileInformation(userId: string) {
	if (!isGetProfileAllowed || !userId) {
		profileData.value = null
		return
	}

	try {
		profileLoading.value = true
		const response = await getUserProfile(userId)
		profileData.value = response.data.ocs.data
	} catch (error) {
		if ((error as ApiErrorResponse)?.response?.status === 405) {
			// Method does not exist on current server version
			// Skip further requests
			isGetProfileAllowed = false
		} else {
			console.error(error)
		}
	} finally {
		profileLoading.value = false
	}
}

/**
 * Concatenates profile strings
 * @param firstSubstring first part of string
 * @param secondSubstring second part of string
 */
function joinFields(firstSubstring?: string | null, secondSubstring?: string | null): string {
	return [firstSubstring, secondSubstring].filter(Boolean).join(' · ')
}
</script>

<template>
	<div class="content">
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

			<div class="content__header">
				<NcAppSidebarHeader class="content__name content__name--has-actions"
					:class="{ 'content__name--has-profile-actions': profileActions.length }"
					:name="sidebarTitle"
					:title="sidebarTitle" />
				<div v-if="profileInformation.length"
					class="content__info">
					<p v-for="row in profileInformation"
						:key="row.key"
						class="content__info-row">
						<component :is="row.icon" :size="16" />
						{{ row.label }}
					</p>
				</div>
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
	&__header {
		flex-grow: 1;
		display: flex;
		flex-direction: column;
		align-items: start;
		gap: var(--default-grid-baseline);
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
	}

	&__profile-action {
		// Override NcActionLink styles
		:deep(.action-link__longtext) {
			white-space: nowrap !important;
		}
	}
}
</style>
