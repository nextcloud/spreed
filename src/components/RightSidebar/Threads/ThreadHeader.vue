<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="thread-header"
		:class="{ 'thread-header--standalone': standalone }">
		<NcButton
			v-if="standalone"
			:title="t('spreed', 'Back')"
			:aria-label="t('spreed', 'Back')"
			@click="threadId = 0">
			<template #icon>
				<IconArrowLeft class="bidirectional-icon" :size="20" />
			</template>
		</NcButton>
		<IconChevronRight
			v-else
			class="bidirectional-icon"
			:size="20" />

		<div v-if="currentThread" class="conversation-header">
			<div
				class="conversation-header__thread-icon"
				:style="{ '--color-thread-icon': usernameToColor(currentThread.thread.title).color }">
				<IconForumOutline :size="20" />
			</div>
			<div class="conversation-header__text">
				<p class="title">
					{{ currentThread.thread.title }}
				</p>
				<p class="description">
					{{ n('spreed', '%n reply', '%n replies', currentThread.thread.numReplies) }}
				</p>
			</div>
		</div>

		<NcActions
			:aria-label="t('spreed', 'Thread notifications')"
			:title="t('spreed', 'Thread notifications')"
			:variant="threadNotificationVariant">
			<template #icon>
				<component :is="notificationLevelIcons[threadNotification]" :size="20" />
			</template>
			<NcActionButton
				v-for="level in notificationLevels"
				:key="level.value"
				:model-value="threadNotification.toString()"
				:value="level.value.toString()"
				:description="level.description"
				type="radio"
				close-after-click
				@click="chatExtrasStore.setThreadNotificationLevel(token, threadId, level.value)">
				<template #icon>
					<component :is="notificationLevelIcons[level.value]" :size="20" />
				</template>
				{{ level.label }}
			</NcActionButton>
		</NcActions>

		<NcActions
			v-if="isModeratorOrOwner"
			:aria-label="t('spreed', 'Thread actions')"
			:title="t('spreed', 'Thread actions')"
			force-menu>
			<NcActionButton
				key="rename-thread"
				close-after-click
				@click="renameThreadTitle">
				<template #icon>
					<IconPencilOutline :size="20" />
				</template>
				{{ t('spreed', 'Edit thread details') }}
			</NcActionButton>
		</NcActions>
	</div>
</template>

<script setup lang="ts">
import { n, t } from '@nextcloud/l10n'
import { usernameToColor } from '@nextcloud/vue/functions/usernameToColor'
import { computed, watch } from 'vue'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import IconForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import { useGetThreadId } from '../../../composables/useGetThreadId.ts'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { PARTICIPANT } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useChatExtrasStore } from '../../../stores/chatExtras.ts'
import { notificationLevelIcons, notificationLevels } from './threadsConstants.ts'

const props = defineProps<{
	/** Whether component is used outside TopBar */
	standalone?: boolean
}>()

const actorStore = useActorStore()
const chatExtrasStore = useChatExtrasStore()
const threadId = useGetThreadId()
const token = useGetToken()
const store = useStore()

const currentThread = computed(() => chatExtrasStore.getThread(token.value, threadId.value))

const threadNotification = computed(() => currentThread.value?.attendee.notificationLevel ?? PARTICIPANT.NOTIFY.DEFAULT)

const threadNotificationVariant = computed(() => {
	return ([PARTICIPANT.NOTIFY.ALWAYS, PARTICIPANT.NOTIFY.MENTION].includes(threadNotification.value))
		? 'secondary'
		: 'tertiary'
})

const isModeratorOrOwner = computed(() => {
	return store.getters.isModerator
		|| (currentThread.value?.first?.actorId === actorStore.actorId && currentThread.value?.first?.actorType === actorStore.actorType)
})

watch(currentThread, (value) => {
	if (threadId.value && value === undefined) {
		chatExtrasStore.fetchSingleThread(token.value, threadId.value)
	}
}, { immediate: true })

/**
 * Rename a thread title on server
 */
async function renameThreadTitle() {
	await chatExtrasStore.renameThread(token.value, threadId.value)
}
</script>

<style lang="scss" scoped>
.thread-header {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	width: 100%;
	gap: var(--default-grid-baseline);

	&--standalone {
		padding: var(--default-grid-baseline);
		border-bottom: 1px solid var(--color-border);
	}
}

.conversation-header {
	position: relative;
	display: flex;
	align-items: center;
	overflow-x: hidden;
	overflow-y: clip;
	white-space: nowrap;
	width: 0;
	flex-grow: 1;
	cursor: pointer;
	&__text {
		display: flex;
		flex-direction:column;
		flex-grow: 1;
		margin-inline-start: 8px;
		justify-content: center;
		width: 100%;
		overflow: hidden;
		// Text is guaranteed to be one line. Make line-height 20px to fit top bar
		line-height: 20px;
		&--offline {
			color: var(--color-text-maxcontrast);
		}
	}
	.title {
		font-weight: 500;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.description {
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: fit-content;
		&__in-chat {
			color: var(--color-text-maxcontrast);
		}
	}

	&__thread-icon {
		--mixed-color: color-mix(in srgb, var(--color-thread-icon) 10%, var(--color-main-background));
		flex-shrink: 0;
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
		display: flex;
		justify-content: center;
		align-items: center;
		border-radius: 50%;
		color: var(--color-thread-icon);
		background-color: var(--mixed-color, var(--color-background-dark));
	}
}
</style>
