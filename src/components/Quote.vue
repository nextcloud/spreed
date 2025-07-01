<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Component } from 'vue'
import type { ChatMessage } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed, toRef } from 'vue'
import { useRoute } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import AvatarWrapper from './AvatarWrapper/AvatarWrapper.vue'
import DefaultParameter from './MessagesList/MessagesGroup/Message/MessagePart/DefaultParameter.vue'
import FilePreview from './MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import { useMessageInfo } from '../composables/useMessageInfo.ts'
import { AVATAR } from '../constants.ts'
import { EventBus } from '../services/EventBus.ts'
import { useActorStore } from '../stores/actor.ts'
import { useChatExtrasStore } from '../stores/chatExtras.js'

type DeletedParentMessage = Pick<ChatMessage, 'id' | 'deleted'>

const { message, canCancel = false, editMessage = false } = defineProps<{
	/** The quoted message object */
	message: ChatMessage | DeletedParentMessage
	/** Whether to show remove / cancel action */
	canCancel?: boolean
	/** Whether to show edit actions */
	editMessage?: boolean
}>()

const route = useRoute()
const actorStore = useActorStore()
const chatExtrasStore = useChatExtrasStore()

const {
	isFileShare,
	isFileShareWithoutCaption,
	remoteServer,
	lastEditor,
	actorDisplayName,
	actorDisplayNameWithFallback,
} = useMessageInfo(isExistingMessage(message) ? toRef(() => message) : undefined)

const actorInfo = computed(() => [actorDisplayNameWithFallback.value, remoteServer.value].filter((value) => value).join(' '))

const hash = computed(() => '#message_' + message.id)

const component = computed(() => canCancel ? { tag: 'div', link: undefined } : { tag: 'router-link', link: { hash: hash.value } })

const isOwnMessageQuoted = computed(() => isExistingMessage(message) ? actorStore.checkIfSelfIsActor(message) : false)

const richParameters = computed(() => {
	const richParameters: Record<string, { component: Component, props: unknown }> = {}
	Object.keys(message.messageParameters).forEach((p: string) => {
		const type = message.messageParameters[p].type
		if (type === 'file') {
			richParameters[p] = {
				component: FilePreview,
				props: {
					token: message.token,
					smallPreview: true,
					rowLayout: !message.messageParameters[p].mimetype!.startsWith('image/'),
					file: message.messageParameters[p],
				},
			}
		} else {
			richParameters[p] = {
				component: DefaultParameter,
				props: message.messageParameters[p],
			}
		}
	})
	return richParameters
})

/**
 * This is a simplified version of the last chat message.
 * Parameters are parsed without markup (just replaced with the name),
 * e.g. no avatars on mentions.
 *
 * @return A simple message to show below the conversation name
 */
const simpleQuotedMessage = computed(() => {
	if (!isExistingMessage(message)) {
		return t('spreed', 'The message has expired or has been deleted')
	}

	if (!Object.keys(message.messageParameters).length) {
		return message.message
	}

	const params = message.messageParameters
	let subtitle = message.message

	// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
	Object.keys(params).forEach((parameterKey) => {
		subtitle = subtitle.replaceAll('{' + parameterKey + '}', params[parameterKey].name)
	})

	return subtitle
})

/**
 * Shorten the message to 250 characters and append three dots to the end of the
 * string. This is needed because on very wide screens, if the 250 characters
 * fit, the css rules won't ellipsize the text-overflow.
 */
const shortenedQuoteMessage = computed(() => {
	return simpleQuotedMessage.value.length >= 250 ? simpleQuotedMessage.value.substring(0, 250) + '…' : simpleQuotedMessage.value
})

/**
 * Check whether message to quote (parent) existing on server
 * Otherwise server returns ['id' => (int)$parentId, 'deleted' => true]
 */
function isExistingMessage(message: ChatMessage | DeletedParentMessage): message is ChatMessage {
	return 'messageType' in message
}

/**
 * Abort replying / editing process
 */
function handleAbort() {
	if (!isExistingMessage(message)) {
		return
	}

	if (editMessage) {
		chatExtrasStore.removeMessageIdToEdit(message.token)
	} else {
		chatExtrasStore.removeParentIdToReply(message.token)
	}
	EventBus.emit('focus-chat-input')
}

/**
 * Focus quoted message
 */
function handleQuoteClick() {
	if (canCancel) {
		return
	}

	if (route.hash === hash.value) {
		// Already on this message route, just trigger highlight
		EventBus.emit('focus-message', message.id)
	}
}
</script>

<template>
	<component :is="component.tag"
		:to="component.link"
		class="quote"
		:class="{ 'quote-own-message': isOwnMessageQuoted }"
		@click="handleQuoteClick">
		<div class="quote__main">
			<div v-if="isExistingMessage(message)"
				class="quote__main__author"
				role="heading"
				aria-level="4">
				<IconPencilOutline v-if="editMessage" :size="16" />
				<AvatarWrapper v-else
					:id="message.actorId"
					:token="message.token"
					:name="actorDisplayName"
					:source="message.actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					disable-menu />
				<span class="quote__main__author-info">{{ actorInfo }}</span>
				<div v-if="editMessage || lastEditor" class="quote__main__edit-hint">
					{{ editMessage ? t('spreed', '(editing)') : lastEditor }}
				</div>
			</div>
			<!-- File preview -->
			<NcRichText v-if="isFileShare"
				text="{file}"
				:arguments="richParameters" />
			<!-- Message text -->
			<blockquote v-if="!isFileShareWithoutCaption" dir="auto" class="quote__main__text">
				{{ shortenedQuoteMessage }}
			</blockquote>
		</div>

		<NcButton v-if="canCancel"
			class="quote__close"
			variant="tertiary"
			:title="t('spreed', 'Cancel quote')"
			:aria-label="t('spreed', 'Cancel quote')"
			@click="handleAbort">
			<template #icon>
				<IconClose :size="20" />
			</template>
		</NcButton>
	</component>
</template>

<style lang="scss" scoped>
@import '../assets/variables';

.quote {
	position: relative;
	margin: 4px 0;
	padding-block: 6px;
	padding-inline-end: 6px;
	padding-inline-start: 24px;
	display: flex;
	max-width: $messages-text-max-width;
	border-radius: var(--border-radius-large);
	border: 2px solid var(--color-border);
	background-color: var(--color-main-background);
	overflow: hidden;

	&::before {
		content: ' ';
		position: absolute;
		top: 8px;
		inset-inline-start: 8px;
		height: calc(100% - 16px);
		width: 8px;
		border-radius: var(--border-radius);
		background-color: var(--color-border);
	}

	&.quote-own-message::before {
		background-color: var(--color-primary-element);
	}

	&__main {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		width: 100%;

		&__author {
			display: flex;
			align-items: center;
			gap: 4px;
			color: var(--color-text-maxcontrast);

			&-info {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		&__text {
			color: var(--color-text-maxcontrast);
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
			text-align: start;
		}

		&__edit-hint {
			flex-shrink: 0;
		}
	}

	&__close {
		position: absolute !important;
		top: 4px;
		inset-inline-end: 4px;
	}
}

</style>
