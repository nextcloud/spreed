<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { RouteLocationAsRelative } from 'vue-router'
import type {
	ThreadInfo,
} from '../../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { usernameToColor } from '@nextcloud/vue/functions/usernameToColor'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconArrowLeftTop from 'vue-material-design-icons/ArrowLeftTop.vue'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconBellOutline from 'vue-material-design-icons/BellOutline.vue'
import IconBellRingOutline from 'vue-material-design-icons/BellRingOutline.vue'
import IconForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import { PARTICIPANT } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useChatExtrasStore } from '../../../stores/chatExtras.ts'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const { thread } = defineProps<{ thread: ThreadInfo }>()

const notificationLevelIcons = {
	[PARTICIPANT.NOTIFY.DEFAULT]: IconBellOutline,
	[PARTICIPANT.NOTIFY.ALWAYS]: IconBellRingOutline,
	[PARTICIPANT.NOTIFY.MENTION]: IconBellOutline,
	[PARTICIPANT.NOTIFY.NEVER]: IconBellOffOutline,
} as const

const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.DEFAULT, label: t('spreed', 'Default'), description: t('spreed', 'Follow conversation settings') },
	{ value: PARTICIPANT.NOTIFY.ALWAYS, label: t('spreed', 'All messages'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.MENTION, label: t('spreed', '@-mentions only'), description: undefined },
	{ value: PARTICIPANT.NOTIFY.NEVER, label: t('spreed', 'Off'), description: undefined },
] as const

const router = useRouter()
const route = useRoute()
const store = useStore()

const actorStore = useActorStore()
const chatExtrasStore = useChatExtrasStore()

const submenu = ref<string | null>(null)

const lastActivity = computed(() => thread.thread.lastActivity * 1000)
const subname = computed(() => {
	const threadMessage = thread.last ?? thread.first
	if (!threadMessage) {
		return t('spreed', 'No messages')
	}

	const actor = getDisplayNameWithFallback(threadMessage.actorDisplayName, threadMessage.actorType, true)
	const lastMessage = parseToSimpleMessage(threadMessage.message, threadMessage.messageParameters)

	return t('spreed', '{actor}: {lastMessage}', { actor, lastMessage }, {
		escape: false,
		sanitize: false,
	})
})

const to = computed<RouteLocationAsRelative>(() => {
	return {
		name: 'conversation',
		params: { token: thread.thread.roomToken },
		query: { threadId: thread.thread.id },
	}
})

const active = computed(() => {
	return route.fullPath.startsWith(router.resolve(to.value).fullPath)
})

const timeFormat = computed<Intl.DateTimeFormatOptions>(() => {
	if (new Date().toDateString() === new Date(lastActivity.value).toDateString()) {
		return { timeStyle: 'short' }
	}
	return { dateStyle: 'short' }
})

const threadNotificationLabel = computed(() => notificationLevels.find((l) => l.value === thread.attendee.notificationLevel)?.label)

const isModeratorOrOwner = computed(() => {
	if (thread.first?.actorId === actorStore.actorId && thread.first?.actorType === actorStore.actorType) {
		return true
	}

	const conversation = store.getters.conversation(thread.thread.roomToken)
	return conversation?.participantType === PARTICIPANT.TYPE.OWNER
		|| conversation?.participantType === PARTICIPANT.TYPE.MODERATOR
		|| conversation?.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR
})

/**
 * Renames the thread
 */
async function renameThreadTitle() {
	await chatExtrasStore.renameThread(thread.thread.roomToken, thread.thread.id)
}

/**
 * Resets the submenu when the actions menu is closed
 *
 * @param open - actions menu state
 */
function handleActionsMenuOpen(open: boolean) {
	if (!open) {
		submenu.value = null
	}
}
</script>

<template>
	<NcListItem
		:data-nav-id="`thread_${thread.thread.id}`"
		class="thread"
		:name="thread.thread.title"
		:to="to"
		:active="active"
		force-menu
		@update:menu-open="handleActionsMenuOpen">
		<template #icon>
			<div
				class="thread__icon"
				:style="{ '--color-thread-icon': usernameToColor(thread.thread.title).color }">
				<IconForumOutline :size="20" />
			</div>
		</template>
		<template #name>
			<span>{{ thread.thread.title }}</span>
		</template>
		<template #subname>
			{{ subname }}
		</template>
		<template #actions>
			<template v-if="submenu === null">
				<NcActionButton
					v-if="isModeratorOrOwner"
					key="rename-thread"
					close-after-click
					@click="renameThreadTitle">
					<template #icon>
						<IconPencilOutline :size="20" />
					</template>
					{{ t('spreed', 'Edit thread details') }}
				</NcActionButton>
				<NcActionButton
					key="show-notifications"
					is-menu
					:description="threadNotificationLabel"
					@click="submenu = 'notifications'">
					<template #icon>
						<IconBellOutline :size="20" />
					</template>
					{{ t('spreed', 'Thread notifications') }}
				</NcActionButton>
			</template>
			<template v-else-if="submenu === 'notifications'">
				<NcActionButton
					key="action-back"
					:aria-label="t('spreed', 'Back')"
					@click.stop="submenu = null">
					<template #icon>
						<IconArrowLeft class="bidirectional-icon" :size="20" />
					</template>
					{{ t('spreed', 'Back') }}
				</NcActionButton>

				<NcActionSeparator />

				<NcActionButton
					v-for="level in notificationLevels"
					:key="level.value"
					:model-value="thread.attendee.notificationLevel.toString()"
					:value="level.value.toString()"
					:description="level.description"
					type="radio"
					@click="chatExtrasStore.setThreadNotificationLevel(thread.thread.roomToken, thread.thread.id, level.value)">
					<template #icon>
						<component :is="notificationLevelIcons[level.value]" :size="20" />
					</template>
					{{ level.label }}
				</NcActionButton>
			</template>
		</template>
		<template #details>
			<span class="thread__details">
				<span class="thread__details-replies">
					<IconArrowLeftTop class="bidirectional-icon" :size="16" />
					{{ thread.thread.numReplies }}
				</span>
				<NcDateTime
					:timestamp="lastActivity"
					:format="timeFormat"
					:relative-time="false"
					ignore-seconds />
			</span>
		</template>
	</NcListItem>
</template>

<style lang="scss" scoped>
.thread {
	:deep(.list-item-content__name) {
		font-size: var(--font-size-small);
		font-weight: 400;
		color: var(--color-text-maxcontrast);
	}

	:deep(.list-item-content__subname) {
		color: var(--color-main-text);
	}

	&__icon {
		--mixed-color: color-mix(in srgb, var(--color-thread-icon), var(--color-main-background) 40%);
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		display: flex;
		justify-content: center;
		align-items: center;
		border-radius: 50%;
		color: var(--color-main-text);
		background-color: var(--mixed-color, var(--color-thread-icon));
	}

	&__details {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		font-size: var(--font-size-small);

		&-replies {
			display: flex;
			gap: calc(0.5 * var(--default-grid-baseline));
			padding-inline: calc(2 * var(--default-grid-baseline));
			border-radius: var(--border-radius-pill);
			background-color: var(--color-primary-element-light);
			color: var(--color-main-text);
			font-weight: 600;
		}
	}

	&.list-item__wrapper--active .thread__details-replies {
		color: var(--color-primary-element-text);
		background-color: transparent;
	}
}
</style>
