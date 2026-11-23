<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { CallReference } from '../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import { computed, inject } from 'vue'
import { routerKey, RouterLink } from 'vue-router'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcDateTime from '@nextcloud/vue/components/NcDateTime'
import AvatarWrapper from '../../components/AvatarWrapper/AvatarWrapper.vue'
import { AVATAR } from '../../constants.ts'
import { getConversationAvatarOcsUrl } from '../../services/avatarService.ts'
import { getDisplayNameWithFallback } from '../../utils/getDisplayName.ts'

const props = defineProps<{
	richObject: CallReference | null
	accessible: boolean
}>()

const conversationIcons = {
	public: {
		dark: new URL('../../../img/icon-conversation-public-dark.svg', import.meta.url).href,
		bright: new URL('../../../img/icon-conversation-public-bright.svg', import.meta.url).href,
	},
	group: {
		dark: new URL('../../../img/icon-conversation-group-dark.svg', import.meta.url).href,
		bright: new URL('../../../img/icon-conversation-group-bright.svg', import.meta.url).href,
	},
}
const talkIconUrl = new URL('../../../img/app-dark.svg', import.meta.url).href

const isDarkTheme = useIsDarkTheme()

const isMessage = computed(() => !!props.richObject?.['message-id'])
const hasAuthor = computed(() => !!props.richObject?.['actor-type'])
const hash = computed(() => isMessage.value ? '#message_' + props.richObject!['message-id'] : '')
const hasRouter = !!inject(routerKey, null)
const linkProps = computed(() => hasRouter
	? { to: { name: 'conversation', params: { token: props.richObject!.id }, hash: hash.value } }
	: { href: props.richObject!.link + hash.value })
const conversationAvatarUrl = computed(() => {
	if (props.accessible && props.richObject!['avatar-version']) {
		return getConversationAvatarOcsUrl(props.richObject!.id, isDarkTheme.value, props.richObject!['avatar-version'])
	}
	const icon = props.accessible && props.richObject!['call-type'] === 'public' ? 'public' : 'group'
	return conversationIcons[icon][isDarkTheme.value ? 'dark' : 'bright']
})
const conversationName = computed(() => {
	if (props.accessible) {
		return props.richObject!.name
	}
	return props.richObject!.link.includes('#message_')
		? t('spreed', 'Message in private conversation')
		: t('spreed', 'Private conversation')
})
const authorName = computed(() => getDisplayNameWithFallback(props.richObject!['actor-display-name'] ?? '', props.richObject!['actor-type'] ?? ''))
const authorLineParts = computed(() => props.richObject!['call-type'] === 'one2one'
	? ['{actor}']
	: t('spreed', '{actor} in {conversation}').split(/(\{actor\}|\{conversation\})/).map((part) => part.trim()).filter(Boolean))
</script>

<template>
	<component
		:is="hasRouter ? RouterLink : 'a'"
		v-if="richObject"
		class="talk-reference"
		v-bind="linkProps">
		<div v-if="!hasAuthor" class="talk-reference__row">
			<NcAvatar
				v-if="accessible && richObject['user-id']"
				class="talk-reference__conversation-avatar"
				:user="richObject['user-id']"
				:displayName="richObject.name"
				:size="AVATAR.SIZE.DEFAULT"
				disableMenu
				disableTooltip
				hideStatus />
			<img
				v-else
				class="talk-reference__conversation-avatar"
				:src="conversationAvatarUrl"
				alt="">
			<div class="talk-reference__content">
				<strong class="talk-reference__ellipsis">{{ conversationName }}</strong>
				<span v-if="richObject.description" class="talk-reference__text">
					{{ richObject.description }}
				</span>
			</div>
			<img class="talk-reference__app-icon" :src="talkIconUrl" alt="">
		</div>

		<div v-else class="talk-reference__row">
			<AvatarWrapper
				:id="richObject['actor-id']"
				:token="richObject.id"
				:name="authorName"
				:source="richObject['actor-type']"
				:size="AVATAR.SIZE.SMALL"
				disableMenu
				disableTooltip />
			<div class="talk-reference__content">
				<div class="talk-reference__author-line">
					<template v-for="(part, index) in authorLineParts" :key="index">
						<strong v-if="part === '{actor}'" class="talk-reference__ellipsis">{{ authorName }}</strong>
						<template v-else-if="part === '{conversation}'">
							<img
								class="talk-reference__conversation-avatar talk-reference__conversation-avatar--inline"
								:src="conversationAvatarUrl"
								alt="">
							<strong class="talk-reference__ellipsis">{{ conversationName }}</strong>
						</template>
						<span v-else class="talk-reference__nowrap">{{ part }}</span>
					</template>
					<NcDateTime
						v-if="Number(richObject.timestamp)"
						class="talk-reference__message-time"
						:timestamp="Number(richObject.timestamp) * 1000"
						relativeTime="short" />
				</div>
				<span v-if="richObject.description" class="talk-reference__text talk-reference__text--message">{{ richObject.description }}</span>
			</div>
			<img class="talk-reference__app-icon" :src="talkIconUrl" alt="">
		</div>
	</component>
</template>

<style lang="scss" scoped>
.talk-reference {
	display: flex;
	padding: calc(3 * var(--default-grid-baseline));
	color: var(--color-main-text);
	white-space: normal;

	&__row {
		display: flex;
		flex: 1 1 auto;
		align-items: flex-start;
		gap: calc(2 * var(--default-grid-baseline));
		min-width: 0;
	}

	&__conversation-avatar {
		flex-shrink: 0;
		width: 40px;
		height: 40px;
		border-radius: 50%;

		&--inline {
			width: 20px;
			height: 20px;
			align-self: center;
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		min-width: 0;
	}

	&__ellipsis {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__nowrap {
		flex-shrink: 0;
		white-space: nowrap;
	}

	&__app-icon {
		flex-shrink: 0;
		width: 16px;
		height: 16px;
		opacity: 0.7;
		filter: var(--background-invert-if-dark);
	}

	&__author-line {
		display: flex;
		align-items: baseline;
		gap: var(--default-grid-baseline);
		min-width: 0;
	}

	&__message-time {
		flex-shrink: 0;
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}

	&__text {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 4;
		overflow: hidden;
		color: var(--color-text-maxcontrast);
		white-space: pre-wrap;
		overflow-wrap: anywhere;

		&--message {
			color: var(--color-main-text);
		}
	}
}
</style>
