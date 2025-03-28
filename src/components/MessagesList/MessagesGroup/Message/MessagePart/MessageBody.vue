<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="messageMain"
		v-element-size="[onResize, { width: 0, height: 22.5 }]"
		v-intersection-observer="onIntersectionObserver"
		class="message-main">
		<!-- System or deleted message body content -->
		<div v-if="isSystemMessage || isDeletedMessage"
			class="message-main__text"
			:class="{
				'system-message': isSystemMessage && !showJoinCallButton,
				'deleted-message': isDeletedMessage,
				'message-highlighted': showJoinCallButton,
			}">
			<!-- Message content / text -->
			<CancelIcon v-if="isDeletedMessage" :size="16" />
			<NcRichText :text="renderedMessage"
				:arguments="richParameters"
				autolink
				dir="auto"
				:reference-limit="0" />

			<!-- Additional controls -->
			<CallButton v-if="showJoinCallButton" />
			<Poll v-if="showResultsButton"
				:token="message.token"
				show-as-button
				v-bind="message.messageParameters.poll" />
		</div>

		<!-- Normal message body content -->
		<div v-else
			class="message-main__text markdown-message"
			:class="{'message-highlighted': isNewPollMessage }"
			@mouseover="handleMarkdownMouseOver"
			@mouseleave="handleMarkdownMouseLeave">
			<!-- Replied parent message -->
			<Quote v-if="message.parent" :message="message.parent" />

			<!-- Message content / text -->
			<NcRichText :text="renderedMessage"
				:arguments="richParameters"
				:class="{'single-emoji': isSingleEmoji}"
				autolink
				dir="auto"
				:interactive="message.markdown && isEditable"
				:use-extended-markdown="message.markdown"
				:reference-limit="1"
				reference-interactive-opt-in
				@interact:todo="handleInteraction" />

			<!-- Additional controls -->
			<NcButton v-if="containsCodeBlocks"
				v-show="currentCodeBlock !== null"
				class="message-copy-code"
				type="tertiary"
				:aria-label="t('spreed', 'Copy code block')"
				:title="t('spreed', 'Copy code block')"
				:style="{top: copyButtonOffset}"
				@click="copyCodeBlock">
				<template #icon>
					<ContentCopyIcon :size="16" />
				</template>
			</NcButton>
		</div>

		<!-- Additional message info-->
		<div v-if="!isDeletedMessage" class="message-main__info">
			<span class="date" :class="{ 'date--hidden': hideDate }" :title="messageDate">{{ messageTime }}</span>

			<!-- Message delivery status indicators -->
			<div v-if="message.sendingFailure"
				:title="sendingErrorIconTitle"
				class="message-status sending-failed"
				:class="{'retry-option': sendingErrorCanRetry}"
				:aria-label="sendingErrorIconTitle"
				tabindex="0"
				@mouseover="showReloadButton = true"
				@focus="showReloadButton = true"
				@mouseleave="showReloadButton = false"
				@blur="showReloadButton = false">
				<NcButton v-if="sendingErrorCanRetry && showReloadButton"
					size="small"
					:aria-label="sendingErrorIconTitle"
					@click="handleRetry">
					<template #icon>
						<ReloadIcon :size="16" />
					</template>
				</NcButton>
				<AlertCircleIcon v-else :size="16" />
			</div>
			<div v-else-if="showLoadingIcon"
				:title="loadingIconTitle"
				class="icon-loading-small message-status"
				:aria-label="loadingIconTitle" />
			<div v-else-if="readInfo.showCommonReadIcon"
				:title="readInfo.commonReadIconTitle"
				class="message-status"
				:aria-label="readInfo.commonReadIconTitle">
				<CheckAllIcon :size="16" />
			</div>
			<div v-else-if="readInfo.showSentIcon"
				:title="readInfo.sentIconTitle"
				class="message-status"
				:aria-label="readInfo.sentIconTitle">
				<CheckIcon :size="16" />
			</div>
			<div v-else-if="readInfo.showSilentIcon"
				:title="readInfo.silentIconTitle"
				class="message-status"
				:aria-label="readInfo.silentIconTitle">
				<IconBellOff :size="16" />
			</div>
		</div>

		<!-- Reactions slot -->
		<slot v-if="!isDeletedMessage" />
	</div>
</template>

<script>
import { vIntersectionObserver as IntersectionObserver, vElementSize as ElementSize } from '@vueuse/components'
import emojiRegex from 'emoji-regex'
import { toRefs } from 'vue'

import AlertCircleIcon from 'vue-material-design-icons/AlertCircle.vue'
import IconBellOff from 'vue-material-design-icons/BellOff.vue'
import CancelIcon from 'vue-material-design-icons/Cancel.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CheckAllIcon from 'vue-material-design-icons/CheckAll.vue'
import ContentCopyIcon from 'vue-material-design-icons/ContentCopy.vue'
import ReloadIcon from 'vue-material-design-icons/Reload.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcRichText from '@nextcloud/vue/components/NcRichText'

import Poll from './Poll.vue'
import Quote from '../../../../Quote.vue'
import CallButton from '../../../../TopBar/CallButton.vue'

import { useIsInCall } from '../../../../../composables/useIsInCall.js'
import { useMessageInfo } from '../../../../../composables/useMessageInfo.js'
import { EventBus } from '../../../../../services/EventBus.ts'
import { usePollsStore } from '../../../../../stores/polls.ts'
import { formatDateTime } from '../../../../../utils/formattedTime.ts'
import { parseSpecialSymbols, parseMentions } from '../../../../../utils/textParse.ts'

// Regular expression to check for Unicode emojis in message text
const regex = emojiRegex()

export default {
	name: 'MessageBody',

	components: {
		CallButton,
		NcButton,
		NcRichText,
		Poll,
		Quote,
		// Icons
		AlertCircleIcon,
		IconBellOff,
		CancelIcon,
		CheckIcon,
		CheckAllIcon,
		ContentCopyIcon,
		ReloadIcon,
	},

	directives: {
		IntersectionObserver,
		ElementSize,
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
			required: true,
		},
	},

	setup(props) {
		const { message } = toRefs(props)
		const {
			isEditable,
			isFileShare,
		} = useMessageInfo(message)

		return {
			isInCall: useIsInCall(),
			pollsStore: usePollsStore(),
			isEditable,
			isFileShare,
		}
	},

	data() {
		return {
			isEditing: false,
			showReloadButton: false,
			currentCodeBlock: null,
			copyButtonOffset: 0,
			isVisible: false,
			previousSize: { width: 0, height: 22.5 }, // default height of one-line message body without widgets
		}
	},

	computed: {
		renderedMessage() {
			if (this.isFileShare && this.message.message !== '{file}') {
				// Add a new line after file to split content into different paragraphs
				return '{file}' + '\n\n' + this.message.message
			} else {
				return this.message.message
			}
		},

		isSystemMessage() {
			return this.message.systemMessage !== ''
		},

		isDeletedMessage() {
			return this.message.messageType === 'comment_deleted'
		},

		isNewPollMessage() {
			if (this.message.messageParameters?.object?.type !== 'talk-poll') {
				return false
			}

			return this.isInCall && this.pollsStore.isNewPoll(this.message.messageParameters.object.id)
		},

		isTemporary() {
			return this.message.timestamp === 0
		},

		hideDate() {
			return this.isTemporary || this.isDeleting || !!this.message.sendingFailure
		},

		messageTime() {
			return formatDateTime(this.isTemporary ? Date.now() : this.message.timestamp * 1000, 'LT')
		},

		messageDate() {
			return formatDateTime(this.isTemporary ? Date.now() : this.message.timestamp * 1000, 'LL')
		},

		isLastCallStartedMessage() {
			return this.message.systemMessage === 'call_started' && this.message.id === this.$store.getters.getLastCallStartedMessageId(this.message.token)
		},

		showJoinCallButton() {
			return this.hasCall && !this.isInCall && this.isLastCallStartedMessage
		},

		showResultsButton() {
			return this.message.systemMessage === 'poll_closed'
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

		containsCodeBlocks() {
			return this.message.message.includes('```')
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

	beforeDestroy() {
		EventBus.off('editing-message-processing', this.setIsEditing)
	},

	methods: {
		t,

		getCodeBlocks() {
			if (!this.containsCodeBlocks) {
				return null
			}

			return Array.from(this.$refs.messageMain?.querySelectorAll('pre'))
		},

		handleMarkdownMouseOver(event) {
			const codeBlocks = this.getCodeBlocks()
			if (!codeBlocks) {
				return
			}
			const index = codeBlocks.findIndex(item => item.contains(event.target))
			if (index !== -1) {
				this.currentCodeBlock = index
				const el = codeBlocks[index]
				this.copyButtonOffset = `${el.offsetTop}px`
			}
		},

		handleMarkdownMouseLeave() {
			this.currentCodeBlock = null
			this.copyButtonOffset = 0
		},

		async copyCodeBlock() {
			try {
				const code = this.getCodeBlocks()[this.currentCodeBlock].textContent
				await navigator.clipboard.writeText(code)
				showSuccess(t('spreed', 'Code block copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Code block could not be copied'))
			}
		},

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
				if (lines[i].trim().match(/^- {1,4}\[\s\]/) || lines[i].trim().match(/^- {1,4}\[x\]/)) {
					if (checkBoxIndex === index) {
						const isChecked = lines[i].includes('[x]')
						if (isChecked) {
							lines[i] = lines[i].replace('[x]', '[ ]')
						} else {
							lines[i] = lines[i].replace('[ ]', '[x]')
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

		setIsEditing({ messageId, value }) {
			if (messageId === this.message.id) {
				this.isEditing = value
			}
		},

		onIntersectionObserver([{ isIntersecting }]) {
			this.isVisible = isIntersecting
		},

		onResize({ width, height }) {
			const oldWidth = this.previousSize?.width
			const oldHeight = this.previousSize?.height
			this.previousSize = { width, height }

			if (!this.isVisible) {
				return
			}
			if (oldWidth && oldWidth !== width) {
				// Resizing messages list
				return
			}
			if (height === 0) {
				// component is unmounted
				return
			}
			EventBus.emit('message-height-changed', { heightDiff: height - oldHeight })
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../../../assets/markdown';
@import '../../../../../assets/variables';

.message-main {
	display: grid;
	grid-template-columns: minmax(0, $messages-text-max-width) $messages-info-width;
	grid-row-gap: var(--default-grid-baseline);
	justify-content: space-between;
	align-items: flex-start;
	min-width: 100%;

	&__text {
		width: 100%;
		color: var(--color-text-light);

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

			.message-copy-code {
				position: absolute;
				top: 0;
				inset-inline-end: calc(4px + var(--default-clickable-area));
				margin-top: 4px;
				background-color: var(--color-background-dark);
			}

			:deep(.rich-text--wrapper) {
				text-align: start;
				@include markdown;
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
}

.message-status {
	width: var(--clickable-area-small, 24px);
	height: var(--clickable-area-small, 24px);
	display: flex;
	justify-content: center;
	align-items: center;

	&.retry-option {
		cursor: pointer;
	}
}

:deep(.rich-text--wrapper) {
	direction: inherit;
}

// Hardcode to restrict size of message images for the chat
:deep(.widget-default--image) {
	max-width: 240px;
}
</style>
