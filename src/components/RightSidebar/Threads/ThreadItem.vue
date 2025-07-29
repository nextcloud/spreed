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
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import IconArrowLeftTop from 'vue-material-design-icons/ArrowLeftTop.vue'
import IconBellOutline from 'vue-material-design-icons/BellOutline.vue'
import IconCommentAlertOutline from 'vue-material-design-icons/CommentAlertOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'
import { parseToSimpleMessage } from '../../../utils/textParse.ts'

const { thread } = defineProps<{ thread: ThreadInfo }>()

const router = useRouter()
const route = useRoute()

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
</script>

<template>
	<NcListItem :data-nav-id="`thread_${thread.thread.id}`"
		class="thread"
		:name="thread.thread.title"
		:to="to"
		:active="active"
		force-menu>
		<template #icon>
			<AvatarWrapper
				v-if="thread.first"
				:id="thread.first.actorId"
				:name="thread.first.actorDisplayName"
				:source="thread.first.actorType"
				disable-menu
				:token="thread.thread.roomToken" />
			<IconCommentAlertOutline
				v-else
				:size="20" />
		</template>
		<template #name>
			<span>{{ thread.thread.title }}</span>
		</template>
		<template #subname>
			{{ subname }}
		</template>
		<template #actions>
			<NcActionButton close-after-click
				@click.stop="() => { console.log('Subscribe') }">
				<template #icon>
					<IconBellOutline :size="20" />
				</template>
				{{ t('spreed', 'Subscribe to thread') }}
			</NcActionButton>
		</template>
		<template #details>
			<span class="thread__details">
				<span class="thread__details-replies">
					<IconArrowLeftTop :size="16" />
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
