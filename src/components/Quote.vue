<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { ChatMessage } from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, ref, toRef } from 'vue'
import { useRoute } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import AvatarWrapper from './AvatarWrapper/AvatarWrapper.vue'
import { useMessageInfo } from '../composables/useMessageInfo.ts'
import { AVATAR } from '../constants.ts'
import { EventBus } from '../services/EventBus.ts'
import { useActorStore } from '../stores/actor.ts'
import { useChatExtrasStore } from '../stores/chatExtras.ts'
import { getMessageIcon } from '../utils/getMessageIcon.ts'
import { parseToSimpleMessage } from '../utils/textParse.ts'

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
	isObjectShare,
	remoteServer,
	lastEditor,
	actorDisplayName,
	actorDisplayNameWithFallback,
} = useMessageInfo(isExistingMessage(message) ? toRef(() => message) : undefined)

const actorInfo = computed(() => [actorDisplayNameWithFallback.value, remoteServer.value].filter((value) => value).join(' '))

const hash = computed(() => '#message_' + message.id)

const component = computed(() => canCancel
	? { tag: 'div', link: undefined }
	: { tag: 'router-link', link: { query: route.query, hash: hash.value } })

const isOwnMessageQuoted = computed(() => isExistingMessage(message) ? actorStore.checkIfSelfIsActor(message) : false)

const filePreviewLoading = ref(true)
const filePreviewFailed = ref(false)
const filePreview = computed(() => {
	if (!isExistingMessage(message) || !isFileShare || filePreviewFailed.value) {
		return undefined
	}

	const fileData = Object.values(message.messageParameters).find((param) => param.type === 'file' && param['preview-available'] === 'yes')
	if (fileData) {
		return {
			alt: fileData.name,
			src: generateUrl('/core/preview?fileId={fileId}&x=32&y=32&a=1', { fileId: fileData.id }),
		}
	} else {
		return undefined
	}
})

const simpleQuotedMessageIcon = computed(() => isExistingMessage(message) ? getMessageIcon(message) : null)

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
	} else {
		return parseToSimpleMessage(message.message, message.messageParameters)
	}
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
 *
 * @param message
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
		:class="{ 'quote--own-message': isOwnMessageQuoted }"
		@click="handleQuoteClick">
		<!-- File preview -->
		<span v-if="isFileShare || isObjectShare" class="quote__preview">
			<img
				v-if="filePreview"
				class="quote__preview-image"
				:alt="filePreview.alt"
				:src="filePreview.src"
				@load="filePreviewLoading = false"
				@error="filePreviewLoading = false; filePreviewFailed = true">
			<component
				:is="simpleQuotedMessageIcon"
				v-else-if="simpleQuotedMessageIcon"
				class="quote__preview-image"
				fill-color="var(--color-text-maxcontrast)"
				:size="34" />
			<NcLoadingIcon v-if="filePreview && filePreviewLoading" class="quote__preview--loading" />
		</span>

		<span class="quote__main">
			<span v-if="isExistingMessage(message)"
				class="quote__main-author"
				role="heading"
				aria-level="4">
				<IconPencilOutline v-if="editMessage" :size="16" />
				<AvatarWrapper v-else-if="!(isFileShare || isObjectShare)"
					:id="message.actorId"
					:token="message.token"
					:name="actorDisplayName"
					:source="message.actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					disable-menu />
				<span class="quote__main-author-info">
					{{ actorInfo }}
				</span>
				<span v-if="editMessage || lastEditor" class="quote__main-edit-hint">
					{{ editMessage ? t('spreed', '(editing)') : lastEditor }}
				</span>
			</span>
			<span
				role="blockquote"
				dir="auto"
				class="quote__main-text">
				{{ shortenedQuoteMessage }}
			</span>
		</span>

		<NcButton v-if="canCancel"
			class="quote__button"
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
	padding-block: var(--default-grid-baseline);
	padding-inline: calc(2 * var(--default-grid-baseline)) var(--default-grid-baseline);
	margin-bottom: var(--default-grid-baseline);
	display: flex;
	gap: var(--default-grid-baseline);
	max-width: $messages-text-max-width;
	min-height: var(--default-clickable-area);
	border-radius: var(--border-radius-large);
	border: 2px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	background-color: var(--color-main-background);
	overflow: hidden;

	&:has(.quote__button) {
		padding-inline-end: var(--default-clickable-area);
	}

	&::before {
		content: ' ';
		position: absolute;
		top: 0;
		inset-inline-start: 0;
		height: 100%;
		width: var(--default-grid-baseline);
		background-color: var(--color-border-maxcontrast);
	}

	&.quote--own-message::before {
		background-color: var(--color-primary-element);
	}

	&__preview {
		position: relative;
		flex-shrink: 0;
		height: 2lh;
		width: 2lh;
		overflow: hidden;
		border-radius: var(--border-radius);
		background-color: var(--color-background-dark);

		&-image {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		&--loading {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%
		}
	}

	// Two-line layout for quotes with preview
	&__preview + .quote__main {
		flex-direction: column;
		align-items: flex-start;
		gap: 0;
	}

	&__main {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		min-width: 0;
		overflow: hidden;

		&-author {
			display: flex;
			align-items: center;
			gap: var(--default-grid-baseline);

			&-info {
				flex-shrink: 0;
				font-weight: 600;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}

		&-text {
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
			text-align: start;
		}

		&-edit-hint {
			flex-shrink: 0;
		}
	}

	&__button {
		position: absolute !important;
		top: 0;
		inset-inline-end: 0;
		height: 100%;
		border-radius: 0 !important;
	}
}

</style>
