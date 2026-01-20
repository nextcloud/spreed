<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		ref="messageMain"
		:class="{
			'message-main': !isSplitViewEnabled || isSystemMessage,
			'message-main--sided': isSplitViewEnabled && !isSystemMessage,
			'message-main--compressed': isSplitViewEnabled && isShortSimpleMessage,
			'message-main--compressed-system': isSplitViewEnabled && isSystemMessage,
		}">
		<p
			v-if="isThreadStarterMessage"
			class="message-main__thread-title">
			<IconForumOutline :size="16" />
			{{ threadTitle }}
		</p>
		<p
			v-else-if="isScheduledMessage && isThreadReply"
			class="message-main__thread-title">
			<IconArrowLeftTop class="bidirectional-icon" :size="16" />
			{{ t('spreed', 'Reply to thread "{threadTitle}"', { threadTitle }) }}
		</p>
		<!-- System or deleted message body content -->
		<div
			v-if="isSystemMessage || isDeletedMessage"
			class="message-main__text"
			:class="{
				'system-message': isSystemMessage && !showJoinCallButton,
				'deleted-message': isDeletedMessage,
				'message-highlighted': showJoinCallButton,
				'full-view': !isSplitViewEnabled,
			}">
			<!-- Message content / text -->
			<IconCancel v-if="isDeletedMessage" :size="16" />
			<NcRichText
				:text="renderedMessage"
				:arguments="richParameters"
				autolink
				dir="auto"
				:reference-limit="0" />

			<!-- Additional controls -->
			<CallButton v-if="showJoinCallButton" class="call-button" />
			<ConversationActionsShortcut
				v-else-if="showConversationActionsShortcut"
				:token="message.token"
				:object-type="conversation.objectType"
				:is-highlighted="isLastMessage" />
			<PollCard
				v-else-if="showResultsButton"
				:token="message.token"
				show-as-button
				v-bind="message.messageParameters.poll" />
		</div>

		<!-- Normal message body content -->
		<div
			v-else
			class="message-main__text markdown-message"
			:class="{ 'message-highlighted': isNewPollMessage }">
			<!-- Replied parent message -->
			<MessageQuote v-if="showQuote" :message="message.parent" />

			<!-- Message content / text -->
			<NcRichText
				:text="renderedMessage"
				:arguments="richParameters"
				:class="{ 'single-emoji': isSingleEmoji }"
				autolink
				dir="auto"
				:interactive="message.markdown && isEditable"
				:use-extended-markdown="message.markdown"
				:reference-limit="1"
				reference-interactive-opt-in
				@interact-todo="handleInteraction" />
		</div>

		<!-- Additional message info-->
		<div
			v-if="!isDeletedMessage"
			class="message-main__info">
			<span v-if="isSplitViewEnabled && isOwnMessage && message.lastEditTimestamp" class="editor">
				<IconPencilOutline :size="14" />
				<AvatarWrapper
					v-if="isEditorDifferentThenAuthor"
					:id="message.lastEditActorId"
					:token="message.token"
					:name="message.lastEditActorDisplayName"
					:source="message.lastEditActorType"
					:size="14"
					disable-menu
					disable-tooltip />
			</span>
			<span class="date" :class="{ 'date--hidden': hideDate }" :title="messageDate">{{ messageTime }}</span>

			<!-- Message delivery status indicators -->
			<div
				v-if="message.sendingFailure"
				:title="sendingErrorIconTitle"
				class="message-status sending-failed"
				:class="{ 'retry-option': sendingErrorCanRetry }"
				:aria-label="sendingErrorIconTitle"
				tabindex="0"
				@mouseover="showReloadButton = true"
				@focus="showReloadButton = true"
				@mouseleave="showReloadButton = false"
				@blur="showReloadButton = false">
				<NcButton
					v-if="sendingErrorCanRetry && showReloadButton"
					size="small"
					:aria-label="sendingErrorIconTitle"
					@click="handleRetry">
					<template #icon>
						<IconReload :size="iconMessageDeliverySize" />
					</template>
				</NcButton>
				<IconAlertCircleOutline v-else :size="iconMessageDeliverySize" />
			</div>
			<div
				v-else-if="showLoadingIcon"
				:title="loadingIconTitle"
				class="icon-loading-small message-status"
				:aria-label="loadingIconTitle" />
			<div
				v-else-if="!isSplitViewEnabled && isMessagePinned"
				class="message-status highlighted"
				:title="t('spreed', 'Pinned')"
				:aria-label="t('spreed', 'Pinned')">
				<IconPin :size="14" />
			</div>
			<div
				v-else-if="readInfo?.showCommonReadIcon"
				:title="readInfo.commonReadIconTitle"
				class="message-status"
				:aria-label="readInfo.commonReadIconTitle">
				<IconCheckAll :size="iconMessageDeliverySize" />
			</div>
			<div
				v-else-if="readInfo?.showSentIcon"
				:title="readInfo.sentIconTitle"
				class="message-status"
				:aria-label="readInfo.sentIconTitle">
				<IconCheck :size="iconMessageDeliverySize" />
			</div>
			<div
				v-else-if="readInfo?.showSilentIcon"
				:title="readInfo.silentIconTitle"
				class="message-status"
				:aria-label="readInfo.silentIconTitle">
				<IconBellOffOutline :size="iconMessageDeliverySize" />
			</div>
		</div>

		<!-- Actions and reactions slot -->
		<div v-if="!isDeletedMessage" class="message-actions">
			<NcButton
				v-if="isThreadStarterMessage && message.threadId !== -1"
				class="message-actions__thread"
				:class="{ light: isSplitViewEnabled && isOwnMessage }"
				size="small"
				@click="handleThreadClick">
				<template #icon>
					<IconArrowLeftTop class="bidirectional-icon" :size="16" />
				</template>
				{{ threadNumReplies }}
			</NcButton>
			<slot name="default" />
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import emojiRegex from 'emoji-regex'
import { inject, toRefs } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconArrowLeftTop from 'vue-material-design-icons/ArrowLeftTop.vue'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconCancel from 'vue-material-design-icons/Cancel.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconCheckAll from 'vue-material-design-icons/CheckAll.vue'
import IconForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconPin from 'vue-material-design-icons/PinOutline.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import AvatarWrapper from '../../../../AvatarWrapper/AvatarWrapper.vue'
import MessageQuote from '../../../../MessageQuote.vue'
import CallButton from '../../../../TopBar/CallButton.vue'
import ConversationActionsShortcut from '../../../../UIShared/ConversationActionsShortcut.vue'
import PollCard from './PollCard.vue'
import { useGetThreadId } from '../../../../../composables/useGetThreadId.ts'
import { useIsInCall } from '../../../../../composables/useIsInCall.js'
import { useMessageInfo } from '../../../../../composables/useMessageInfo.ts'
import { CONVERSATION, MESSAGE } from '../../../../../constants.ts'
import { hasTalkFeature } from '../../../../../services/CapabilitiesManager.ts'
import { EventBus } from '../../../../../services/EventBus.ts'
import { useActorStore } from '../../../../../stores/actor.ts'
import { useChatExtrasStore } from '../../../../../stores/chatExtras.ts'
import { usePollsStore } from '../../../../../stores/polls.ts'
import { formatDateTime } from '../../../../../utils/formattedTime.ts'
import { parseMentions, parseSpecialSymbols } from '../../../../../utils/textParse.ts'

// Regular expression to check for Unicode emojis in message text
const regex = emojiRegex()
// Regular expressions to check for task lists in message text like: - [ ], * [ ], + [ ],- [x], - [X]
const checkboxRegexp = /^\s*[-+*]\s.*\[[\sxX]\]/
const checkboxCheckedRegexp = /^\s*[-+*]\s.*\[[xX]\]/

export default {
	name: 'MessageBody',

	components: {
		AvatarWrapper,
		CallButton,
		NcButton,
		NcRichText,
		PollCard,
		MessageQuote,
		ConversationActionsShortcut,
		// Icons
		IconAlertCircleOutline,
		IconArrowLeftTop,
		IconBellOffOutline,
		IconCancel,
		IconCheck,
		IconCheckAll,
		IconForumOutline,
		IconPencilOutline,
		IconPin,
		IconReload,
	},

	props: {
		message: {
			type: Object,
			required: true,
		},

		richParameters: {
			type: Object,
			required: true,
		},

		isDeleting: {
			type: Boolean,
			default: false,
		},

		hasCall: {
			type: Boolean,
			default: false,
		},

		readInfo: {
			type: Object,
			default: null,
		},

		isShortSimpleMessage: {
			type: Boolean,
			default: false,
		},

		isSelfActor: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		const { message } = toRefs(props)
		const {
			isEditable,
			isFileShare,
		} = useMessageInfo(message)
		const threadId = useGetThreadId()
		const isSidebar = inject('chatView:isSidebar', false)
		const isSplitViewEnabled = inject('messagesList:isSplitViewEnabled', true)

		return {
			isInCall: useIsInCall(),
			chatExtrasStore: useChatExtrasStore(),
			pollsStore: usePollsStore(),
			threadId,
			isEditable,
			isFileShare,
			isSidebar,
			actorStore: useActorStore(),
			isSplitViewEnabled,
		}
	},

	data() {
		return {
			isEditing: false,
			showReloadButton: false,
		}
	},

	computed: {
		showQuote() {
			return !!this.message.parent && this.message.parent.id !== this.threadId
		},

		renderedMessage() {
			if (this.isFileShare && this.message.message !== '{file}') {
				// Add a new line after file to split content into different paragraphs
				return '{file}\n\n' + this.message.message
			} else {
				return this.message.message
			}
		},

		isSystemMessage() {
			return this.message.systemMessage !== ''
		},

		isDeletedMessage() {
			return this.message.messageType === MESSAGE.TYPE.COMMENT_DELETED
		},

		isNewPollMessage() {
			if (this.message.messageParameters?.object?.type !== 'talk-poll') {
				return false
			}

			return this.isInCall && this.pollsStore.isNewPoll(this.message.messageParameters.object.id)
		},

		isCallEndedMessage() {
			return [MESSAGE.SYSTEM_TYPE.CALL_ENDED, MESSAGE.SYSTEM_TYPE.CALL_ENDED_EVERYONE].includes(this.message.systemMessage)
		},

		isScheduledMessage() {
			return this.message.referenceId?.startsWith('scheduled-')
		},

		isThreadStarterMessage() {
			if (this.threadId || !this.message.isThread) {
				return false
			}

			return this.message.id === this.message.threadId
				|| (this.message.threadTitle && this.message.id.toString().startsWith('temp-'))
				|| (this.isScheduledMessage && this.message.threadTitle && this.message.threadId === -1)
		},

		isThreadReply() {
			return this.message.isThread && this.message.id !== this.message.threadId
		},

		threadInfo() {
			return this.chatExtrasStore.getThread(this.message.token, this.message.threadId)
		},

		threadTitle() {
			return this.threadInfo?.thread.title ?? this.message.threadTitle
		},

		threadNumReplies() {
			const numReplies = this.threadInfo?.thread.numReplies ?? this.message.threadReplies
			return numReplies
				? n('spreed', '%n reply', '%n replies', numReplies)
				: t('spreed', 'Reply')
		},

		conversation() {
			return this.$store.getters.conversation(this.message.token)
		},

		hasRetentionPeriod() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.EVENT
				|| this.conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY
				|| this.conversation.objectType === CONVERSATION.OBJECT_TYPE.INSTANT_MEETING
		},

		supportUnbindConversation() {
			return hasTalkFeature(this.message.token, 'unbind-conversation')
		},

		showConversationActionsShortcut() {
			return this.supportUnbindConversation
				&& !this.isInCall && !this.isSidebar && this.$store.getters.isModeratorOrUser
				&& this.hasRetentionPeriod
				&& this.isCallEndedMessage
				&& this.message.id > this.lastCallStartedMessageId
		},

		isLastMessage() {
			return this.message.id === this.conversation.lastMessage?.id
		},

		isTemporary() {
			return !this.isScheduledMessage && this.message.timestamp === 0
		},

		hideDate() {
			return this.isTemporary || this.isDeleting || !!this.message.sendingFailure
		},

		messageTime() {
			return formatDateTime(this.isTemporary ? Date.now() : this.message.timestamp * 1000, 'shortTime')
		},

		messageDate() {
			return formatDateTime(this.isTemporary ? Date.now() : this.message.timestamp * 1000, 'longDate')
		},

		lastCallStartedMessageId() {
			return this.$store.getters.getLastCallStartedMessageId(this.message.token)
		},

		isLastCallStartedMessage() {
			return this.message.systemMessage === MESSAGE.SYSTEM_TYPE.CALL_STARTED && this.message.id === this.lastCallStartedMessageId
		},

		showJoinCallButton() {
			return this.hasCall && !this.isInCall && this.isLastCallStartedMessage
		},

		showResultsButton() {
			return this.message.systemMessage === MESSAGE.SYSTEM_TYPE.POLL_CLOSED
		},

		isSingleEmoji() {
			if (this.isSystemMessage || this.isDeletedMessage) {
				return
			}

			const trimmedMessage = this.renderedMessage.trim()
			const emojiMatches = trimmedMessage.match(regex)
			return emojiMatches !== null && emojiMatches.length === 1 && emojiMatches[0] === trimmedMessage
		},

		showLoadingIcon() {
			return this.isTemporary || this.isDeleting || this.isEditing
		},

		loadingIconTitle() {
			return t('spreed', 'Sending message')
		},

		sendingErrorCanRetry() {
			return ['timeout', 'other', 'failed-upload'].includes(this.message.sendingFailure)
		},

		sendingErrorIconTitle() {
			if (this.sendingErrorCanRetry) {
				return t('spreed', 'Failed to send the message. Click to try again')
			}
			if (this.message.sendingFailure === 'quota') {
				return t('spreed', 'Not enough free space to upload file')
			}
			if (this.message.sendingFailure === 'failed-share') {
				return t('spreed', 'You are not allowed to share files')
			}
			return t('spreed', 'You cannot send messages to this conversation at the moment')
		},

		iconMessageDeliverySize() {
			return this.isSplitViewEnabled ? 14 : 16
		},

		isOwnMessage() {
			return this.isSelfActor && !this.isSystemMessage
		},

		isEditorDifferentThenAuthor() {
			return this.message.lastEditActorId
				&& this.message.lastEditActorId !== this.message.actorId
				&& this.message.lastEditActorDisplayName !== this.message.actorDisplayName
				&& this.message.lastEditActorType !== this.message.actorType
		},

		isMessagePinned() {
			return !!this.message.metaData?.pinnedAt
		},
	},

	watch: {
		showJoinCallButton() {
			EventBus.emit('scroll-chat-to-bottom', { smooth: true })
		},
	},

	mounted() {
		if (this.isEditable) {
			EventBus.on('editing-message-processing', this.setIsEditing)
		}
	},

	beforeUnmount() {
		EventBus.off('editing-message-processing', this.setIsEditing)
	},

	methods: {
		t,

		handleRetry() {
			if (this.sendingErrorCanRetry) {
				if (this.message.sendingFailure === 'failed-upload') {
					this.$store.dispatch('retryUploadFiles', {
						token: this.message.token,
						uploadId: this.$store.getters.message(this.message.token, this.message.id)?.uploadId,
						caption: this.renderedMessage !== this.message.message ? this.message.message : undefined,
					})
				} else {
					EventBus.emit('retry-message', this.message.id)
					EventBus.emit('focus-chat-input')
				}
			}
		},

		async handleInteraction(id) {
			if (!this.isEditable) {
				return
			}

			const parentId = id.split('-markdown-input-')[0]
			const index = Array.from(this.$refs.messageMain.querySelectorAll(`span[id^="${parentId}-markdown-input-"]`)).findIndex((el) => el.id.includes(id))
			if (index === -1) {
				return
			}
			let checkBoxIndex = 0
			const lines = this.message.message.split('\n')
			for (let i = 0; i < lines.length; i++) {
				if (checkboxRegexp.test(lines[i])) {
					if (checkBoxIndex === index) {
						if (checkboxCheckedRegexp.test(lines[i])) {
							lines[i] = lines[i].replace(/\[[xX]\]/, '[ ]')
						} else {
							lines[i] = lines[i].replace(/\[\s\]/, '[x]')
						}
						break
					}
					checkBoxIndex++
				}
			}
			// Update the message using editing API
			let newMessageText = parseSpecialSymbols(lines.join('\n').trim())
			// also parse mentions
			newMessageText = parseMentions(newMessageText, this.message.messageParameters)
			try {
				await this.$store.dispatch('editMessage', {
					token: this.message.token,
					messageId: this.message.id,
					updatedMessage: newMessageText,
				})
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not update the message'))
			}
		},

		handleThreadClick() {
			this.$router.replace({ query: { threadId: this.message.threadId }, hash: '' })
		},

		setIsEditing({ messageId, value }) {
			if (messageId === this.message.id) {
				this.isEditing = value
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../../../../../assets/markdown' as *;
@use '../../../../../assets/variables' as *;

.message-main {
	display: grid;
	justify-content: space-between;
	align-items: flex-start;
	min-height: var(--clickable-area-small);
	min-width: 100%;
	// Layout 1 (standard view): text and info in two columns
	grid-template-columns: minmax(0, $messages-text-max-width) $messages-info-width;
	grid-row-gap: var(--default-grid-baseline);

	& .message-main__thread-title,
	&--sided .message-main__thread-title {
		grid-column: 1 / -1;
		grid-row: 1;
	}

	// Split view begin
	// Layout 2 (split view short message): text and info side by side without actions
	&--sided.message-main--compressed:has(.message-main__text) {
		display: grid;
		grid-template-columns: 1fr auto;
		grid-template-rows: auto auto;
	}

	// Layout 3 (split view long message): text in full width, info and actions below
	&--sided:has(.message-main__text):not(.message-main--compressed) {
		display: grid;
		grid-template-columns: 1fr auto;
		grid-template-rows: auto auto auto;

		.message-main__info {
			grid-row: 3;
			grid-column: 2;
		}

		.message-actions {
			grid-row: 3;
			grid-column: 1;
		}

		.message-main__text {
			grid-column: 1 / -1;
			grid-row: 2;
		}
	}

	// Layout 4 split view system message: centered text and timestamp
	&--compressed-system {
		display: flex;
		flex-direction: row;
		justify-content: center;
		font-size: var(--font-size-small);

		.system-message {
			padding: 0 !important;
		}

		.message-main__info {
			opacity: 0;
			width: auto;
			font-size: var(--font-size-small);

			&::before {
				content: ' • ';
			}
		}

		&:hover > .message-main__info {
			opacity: 1;
		}
	}

	// common styles for split view
	&--sided {
		.message-main__info {
			padding-inline-end: 0;
			align-items: end;
			font-size: var(--font-size-small);
			width: auto;

			.editor {
				display: inline-flex;
				align-items: center;
				margin-inline-end: var(--default-grid-baseline);
				gap: calc(var(--default-grid-baseline) / 2);
				height: 1lh;
			}
		}

		.date {
			display: inline-flex;
			justify-content: flex-end;
			align-items: flex-end;
			margin-inline-end: 0 !important;
			width: auto !important;
		}

		.message-status {
			height: 1lh;
			width: 1lh;
		}

		.message-actions__thread.light {
			background-color: var(--color-primary-element-extra-light);
			&:hover {
				background-color: var(--color-primary-element-extra-light-hover);
			}
		}

		.message-main__text {
			padding-inline: calc(var(--default-grid-baseline) / 2);
		}
	}

	// Split view end

	&__text {
		color: var(--color-text-light);

		&.full-view {
			width: 100%;
		}

		& > .single-emoji {
			font-size: 250%;
			line-height: 100%;
		}

		&.system-message {
			color: var(--color-text-maxcontrast);
			text-align: center;
			padding: 0 20px;
		}

		&.message-highlighted {
			color: var(--color-text-light);
			background-color: var(--color-primary-element-light);
			padding: 10px;
			border-radius: var(--border-radius-large);
			text-align: center;
		}

		&.deleted-message {
			display: flex;
			align-items: center;
			color: var(--color-text-maxcontrast);
			:deep(.rich-text--wrapper) {
				flex-grow: 1;
				text-align: start;
			}
		}

		&.markdown-message {
			position: relative;

			:deep(.rich-text--wrapper) {
				// NcRichText is used with dir="auto", so internal text direction may vary
				// But we want to keep the alignment consistent with the rest of the UI
				text-align: inherit;

				@include markdown;
			}

			// FIXME: Should it be in the upstream NcRichText component?
			// NcRichText is used with dir="auto", so internal text direction may vary
			// But we want to keep the alignment consistent with the rest of the UI
			// So we need to override the direction for task lists using the current direction, not its own direction
			&:dir(rtl) :deep(ul.contains-task-list) {
				direction: rtl;
			}

			&:dir(ltr) :deep(ul.contains-task-list) {
				direction: ltr;
			}

			// Overriding direction above breaks text direction
			// Restoring it back
			:deep(ul.contains-task-list) .checkbox-content__wrapper {
				unicode-bidi: plaintext;
			}
		}
	}

	&__info {
		position: relative;
		user-select: none;
		display: flex;
		justify-content: flex-end;
		color: var(--color-text-maxcontrast);
		font-size: var(--default-font-size);
		width: $messages-info-width;
		gap: calc(var(--default-grid-baseline) / 2);
		padding-inline: calc(2 * var(--default-grid-baseline));

		.date {
			width: 8ch;
			text-align: end;

			&--hidden {
				pointer-events: none;
				opacity: 0;
			}

			&:last-child {
				margin-inline-end: var(--clickable-area-small, 24px);
			}
		}
	}

	&__thread-title {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		font-weight: 500;
	}
}

.message-status {
	width: var(--clickable-area-small, 24px);
	height: var(--clickable-area-small, 24px);
	display: flex;
	justify-content: center;
	align-items: center;
	position: relative;

	&.retry-option {
		cursor: pointer;
	}

	&.highlighted {
		color: var(--color-main-background);

		&::before {
			content: '';
			position: absolute;
			inset: 10%;
			border-radius: 50%;
			background-color: var(--color-primary-element);
		}

		:deep(svg) {
			z-index: 1;
		}
	}
}

.message-actions {
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline);

	// Overwrite NcButton styles
	:deep(.button-vue__text) {
		font-weight: normal;
	}

	&__thread :deep(.button-vue__text) {
		margin-inline-start: calc(var(--default-grid-baseline) / 2);
	}
}

.call-button {
	margin: 0 auto;
}

// Always render code blocks LTR
:deep(.rich-text--wrapper) pre {
	direction: ltr;
}

// Hardcode to restrict size of message images for the chat
:deep(.widget-default--image) {
	max-width: 240px;
}
</style>
