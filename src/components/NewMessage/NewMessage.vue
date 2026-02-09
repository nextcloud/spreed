<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="wrapper"
		:class="{ 'wrapper--narrow': isSidebar }">
		<NewMessageTypingIndicator
			v-if="showTypingStatus"
			:token="token" />

		<!--native file picker, hidden -->
		<input
			id="file-upload"
			ref="fileUploadInput"
			multiple
			type="file"
			tabindex="-1"
			aria-hidden="true"
			class="hidden-visually"
			@change="handleFileInput">

		<form
			class="new-message-form"
			@submit.prevent>
			<!-- Attachments menu -->
			<NewMessageAttachments
				v-if="showAttachmentsMenu"
				class="new-message-form__attachments"
				:token="token"
				:disabled="disabled"
				:canUploadFiles="canUploadFiles"
				:canShareFiles="canShareFiles"
				:canCreatePoll="canCreatePoll"
				:canCreateThread="canCreateThread"
				@openFileUpload="openFileUploadWindow"
				@createThread="setCreateThread"
				@handleFileShare="showFilePicker"
				@updateNewFileDialog="updateNewFileDialog" />

			<!-- Input area -->
			<div class="new-message-form__input">
				<NewMessageAbsenceInfo
					v-if="!dialog && userAbsence"
					class="new-message-form__note-content"
					:userAbsence="userAbsence"
					:displayName="conversation.displayName" />

				<NewMessageChatSummary
					v-if="!dialog && showChatSummary"
					class="new-message-form__note-content" />

				<div class="new-message-form__emoji-picker">
					<NcEmojiPicker
						v-if="!disabled"
						keepOpen
						:setReturnFocus="getContenteditable"
						@select="addEmoji">
						<NcButton
							:disabled="disabled"
							variant="tertiary"
							:aria-label="t('spreed', 'Add emoji')"
							:aria-haspopup="true">
							<template #icon>
								<IconEmoticonOutline :size="20" />
							</template>
						</NcButton>
					</NcEmojiPicker>
					<!-- Disabled emoji picker placeholder button -->
					<NcButton
						v-else
						variant="tertiary"
						:aria-label="t('spreed', 'Add emoji')"
						:disabled="true">
						<template #icon>
							<IconEmoticonOutline :size="20" />
						</template>
					</NcButton>
				</div>

				<div v-if="parentMessage || messageToEdit" class="new-message-form__quote">
					<MessageQuote
						:message="messageToEdit ?? parentMessage"
						:canCancel="!!parentMessage"
						:editMessage="!!messageToEdit" />
				</div>

				<!-- scheduling message hint -->
				<NcNoteCard
					v-if="scheduleMessageTime"
					class="new-message-form__hint new-message-form__hint--scheduled"
					type="info">
					<p>{{ scheduleMessageHint }}</p>
					<NcButton
						variant="tertiary"
						size="small"
						:aria-label="t('spreed', 'Cancel')"
						:title="t('spreed', 'Cancel')"
						@click="handleAbortEdit">
						<template #icon>
							<IconClose :size="16" />
						</template>
					</NcButton>
				</NcNoteCard>

				<!-- mention editing hint -->
				<NcNoteCard
					v-if="showMentionEditHint"
					class="new-message-form__hint"
					type="warning"
					:text="t('spreed', 'Adding a mention will only notify users who did not read the message.')" />

				<NcTextField
					v-if="threadCreating"
					ref="threadTitleInputRef"
					v-model="threadTitle"
					class="new-message-form__thread-title"
					:label="t('spreed', 'Thread title')"
					:disabled="disabled"
					:error="!!errorTitle"
					:title="errorTitle"
					showTrailingButton
					@trailingButtonClick="setCreateThread(false)" />

				<NcRichContenteditable
					ref="richContenteditable"
					:key="container"
					v-model="text"
					:class="{ 'new-message-form__input-rich--required': errorMessage }"
					:title="errorMessage"
					:autoComplete="autoComplete"
					:disabled="disabled"
					:userData="userData"
					:menuContainer="containerElement"
					:placeholder="placeholderText"
					:aria-label="placeholderText"
					:dir="text ? 'auto' : undefined"
					@keydown.esc="handleInputEsc"
					@keydown.ctrl.up="handleEditLastMessage"
					@keydown.meta.up="handleEditLastMessage"
					@update:modelValue="handleTyping"
					@paste="handlePastedFiles"
					@focus="restoreSelectionRange"
					@blur="preserveSelectionRange"
					@submit="handleSubmit" />
			</div>

			<!-- Silent chat -->
			<NcActions
				v-if="showSendActions"
				:disabled="disabled"
				forceMenu
				:primary="silentChat"
				:open="isNcActionsOpen"
				@close="submenu = null; isNcActionsOpen = false">
				<template #icon>
					<IconBellOffOutline v-if="silentChat" :size="20" />
				</template>
				<template v-if="submenu === null">
					<NcActionButton
						v-if="supportScheduleMessages && !dialog"
						key="action-schedule"
						isMenu
						@click.stop="submenu = 'schedule'">
						<template #icon>
							<IconClockOutline :size="20" />
						</template>
						{{ t('spreed', 'Send later') }}
					</NcActionButton>
					<NcActionButton
						v-if="isSidebar && showScheduledMessagesToggle"
						type="checkbox"
						:modelValue="showScheduledMessages"
						closeAfterClick
						@click="chatExtrasStore.setShowScheduledMessages(!showScheduledMessages)">
						<template #icon>
							<IconClockOutline :size="20" />
						</template>
						{{ t('spreed', 'Show scheduled messages') }}
					</NcActionButton>

					<NcActionButton
						key="silent-send"
						closeAfterClick
						:modelValue="silentChat"
						:description="silentSendInfo"
						@click="toggleSilentChat">
						{{ silentSendLabel }}
						<template #icon>
							<IconBellOffOutline :size="20" />
						</template>
					</NcActionButton>
				</template>

				<template v-else-if="submenu === 'schedule'">
					<NcActionButton
						key="action-back"
						:aria-label="t('spreed', 'Back')"
						@click.stop="submenu = null">
						<template #icon>
							<IconArrowLeft class="bidirectional-icon" />
						</template>
						{{ t('spreed', 'Back') }}
					</NcActionButton>

					<NcActionSeparator />

					<NcActionButton
						v-for="option in getCustomDateOptions()"
						:key="option.key"
						:aria-label="option.ariaLabel"
						closeAfterClick
						@click.stop="chatExtrasStore.setScheduleMessageTime(option.timestamp)">
						{{ option.label }}
					</NcActionButton>

					<NcActionInput
						v-model="customScheduleTimestamp"
						type="datetime-local"
						:min="new Date()"
						:label="t('spreed', 'Choose a time')"
						:step="300"
						isNativePicker>
						<template #icon>
							<IconCalendarClockOutline :size="20" />
						</template>
					</NcActionInput>

					<NcActionButton
						key="custom-time-submit"
						:disabled="!customScheduleTimestamp"
						closeAfterClick
						@click.stop="chatExtrasStore.setScheduleMessageTime(customScheduleTimestamp.valueOf())">
						<template #icon>
							<IconCheck :size="20" />
						</template>
						{{ t('spreed', 'Send at custom time') }}
					</NcActionButton>
				</template>
			</NcActions>

			<NcButton
				v-if="!isSidebar && showScheduledMessagesToggle"
				:variant="showScheduledMessagesToggleVariant"
				:title="t('spreed', 'Show scheduled messages')"
				@click="chatExtrasStore.setShowScheduledMessages(!showScheduledMessages)">
				<template #icon>
					<IconClockOutline :size="20" />
				</template>
			</NcButton>

			<!-- Audio recorder -->
			<NewMessageAudioRecorder
				v-if="showAudioRecorder"
				:disabled="disabled"
				@recording="handleRecording"
				@audioFile="handleAudioFile" />

			<!-- Edit -->
			<template v-else-if="messageToEdit">
				<NcButton
					variant="tertiary"
					type="submit"
					:title="t('spreed', 'Cancel editing')"
					:aria-label="t('spreed', 'Cancel editing')"
					@click="handleAbortEdit">
					<template #icon>
						<IconClose :size="20" />
					</template>
				</NcButton>
				<NcButton
					:disabled="disabledEdit"
					variant="tertiary"
					type="submit"
					:title="t('spreed', 'Edit message')"
					:aria-label="t('spreed', 'Edit message')"
					@click="handleEdit">
					<template #icon>
						<IconCheck :size="20" />
					</template>
				</NcButton>
			</template>

			<!-- Send buttons -->
			<template v-else>
				<NcButton
					v-if="supportScheduleMessages && scheduleMessageTime"
					:disabled="disabled || !text || isScheduling"
					variant="tertiary"
					type="submit"
					:title="t('spreed', 'Schedule message')"
					:aria-label="t('spreed', 'Schedule message')"
					@click="handleSubmit">
					<template #icon>
						<NcLoadingIcon v-if="isScheduling" :size="20" />
						<IconSendVariantClockOutline v-else :size="20" />
					</template>
				</NcButton>

				<NcButton
					v-else
					:disabled="disabled"
					variant="tertiary"
					type="submit"
					:title="sendMessageLabel"
					:aria-label="sendMessageLabel"
					@click="handleSubmit">
					<template #icon>
						<IconForumOutline v-if="threadCreating" :size="20" />
						<IconSend v-else class="bidirectional-icon" :size="20" />
					</template>
				</NcButton>
			</template>
		</form>

		<!-- New file creation dialog -->
		<NewMessageNewFileDialog
			v-if="showNewFileDialog !== -1"
			:token="token"
			:showNewFileDialog="showNewFileDialog"
			@dismiss="showNewFileDialog = -1" />
	</div>
</template>

<script>
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import debounce from 'debounce'
import { inject, nextTick, toRefs, useTemplateRef } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconCalendarClockOutline from 'vue-material-design-icons/CalendarClockOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import IconForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import IconSend from 'vue-material-design-icons/Send.vue' // Filled for better indication
import IconSendVariantClockOutline from 'vue-material-design-icons/SendVariantClockOutline.vue' // Filled for better indication
import MessageQuote from '../MessageQuote.vue'
import NewMessageAbsenceInfo from './NewMessageAbsenceInfo.vue'
import NewMessageAttachments from './NewMessageAttachments.vue'
import NewMessageAudioRecorder from './NewMessageAudioRecorder.vue'
import NewMessageChatSummary from './NewMessageChatSummary.vue'
import NewMessageNewFileDialog from './NewMessageNewFileDialog.vue'
import NewMessageTypingIndicator from './NewMessageTypingIndicator.vue'
import { useChatMentions } from '../../composables/useChatMentions.ts'
import { useGetThreadId } from '../../composables/useGetThreadId.ts'
import { useTemporaryMessage } from '../../composables/useTemporaryMessage.ts'
import { CONVERSATION, PARTICIPANT, PRIVACY } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useChatStore } from '../../stores/chat.ts'
import { useChatExtrasStore } from '../../stores/chatExtras.ts'
import { useGroupwareStore } from '../../stores/groupware.ts'
import { useSettingsStore } from '../../stores/settings.ts'
import { useTokenStore } from '../../stores/token.ts'
import { fetchClipboardContent } from '../../utils/clipboard.js'
import { convertToUnix, ONE_DAY_IN_MS } from '../../utils/formattedTime.ts'
import { getCustomDateOptions } from '../../utils/getCustomDateOptions.ts'
import {
	getCurrentSelectionRange,
	getRangeAtEnd,
	insertTextInElement,
	selectRange,
} from '../../utils/selectionRange.ts'
import { parseSpecialSymbols } from '../../utils/textParse.ts'

const supportScheduleMessages = hasTalkFeature('local', 'scheduled-messages')

export default {
	name: 'NewMessage',

	components: {
		NcActionButton,
		NcActionInput,
		NcActionSeparator,
		NcActions,
		NcButton,
		NcEmojiPicker,
		NcLoadingIcon,
		NcNoteCard,
		NcRichContenteditable,
		NcTextField,
		NewMessageAbsenceInfo,
		NewMessageAttachments,
		NewMessageAudioRecorder,
		NewMessageChatSummary,
		NewMessageNewFileDialog,
		NewMessageTypingIndicator,
		MessageQuote,
		// Icons
		IconArrowLeft,
		IconCalendarClockOutline,
		IconClockOutline,
		IconBellOffOutline,
		IconCheck,
		IconClose,
		IconEmoticonOutline,
		IconForumOutline,
		IconSend,
		IconSendVariantClockOutline,
	},

	props: {
		/**
		 * The current conversation token or the breakout room token.
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * Selector for popovers and pickers to be rendered inside container properly.
		 */
		container: {
			type: String,
			default: undefined,
		},

		/**
		 * If component is used in a dialog, submit should emit an event to parent
		 * instead of posting the message
		 */
		dialog: {
			type: Boolean,
			default: false,
		},

		/**
		 * Broadcast messages to all breakout rooms of a given conversation.
		 */
		broadcast: {
			type: Boolean,
			default: false,
		},

		/**
		 * Upload files caption.
		 */
		upload: {
			type: Boolean,
			default: false,
		},

		/**
		 * Show an indicator if someone is currently typing a message.
		 */
		hasTypingIndicator: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['submit', 'dismiss'],

	expose: ['focusInput'],

	setup(props) {
		const { token } = toRefs(props)
		const supportTypingStatus = getTalkConfig(token.value, 'chat', 'typing-privacy') !== undefined
		const { autoComplete, userData } = useChatMentions(token)
		const threadId = useGetThreadId()
		const { createTemporaryMessage } = useTemporaryMessage()

		const threadTitleInputRef = useTemplateRef('threadTitleInputRef')

		const isSidebar = inject('chatView:isSidebar', false)

		return {
			actorStore: useActorStore(),
			chatExtrasStore: useChatExtrasStore(),
			groupwareStore: useGroupwareStore(),
			chatStore: useChatStore(),
			settingsStore: useSettingsStore(),
			tokenStore: useTokenStore(),
			supportTypingStatus,
			supportScheduleMessages,
			autoComplete,
			userData,
			threadId,
			threadTitleInputRef,
			createTemporaryMessage,
			convertToUnix,
			isSidebar,
		}
	},

	data() {
		return {
			text: '',
			errorTitle: '',
			errorMessage: '',
			silentChat: false,
			// True when the audio recorder component is recording
			isRecordingAudio: false,
			showNewFileDialog: -1,
			clipboardTimeStamp: null,
			typingInterval: null,
			wasTypingWithinInterval: false,
			debouncedUpdateChatInput: debounce(this.updateChatInput, 200),
			preservedSelectionRange: null,

			/* Schedule messages feature local state */
			isNcActionsOpen: false,
			submenu: null,
			customScheduleTimestamp: null,
			isScheduling: false,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || {
				readOnly: CONVERSATION.STATE.READ_WRITE,
			}
		},

		isReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},

		noChatPermission() {
			return (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT) === 0
		},

		disabled() {
			return this.isReadOnly || this.noChatPermission || !this.currentConversationIsJoined || this.isRecordingAudio
		},

		scheduleMessageTime() {
			return this.chatExtrasStore.scheduleMessageTime
		},

		disabledEdit() {
			if (this.disabled || this.text === '') {
				return true
			}

			if (!this.showScheduledMessages) {
				return this.text === this.messageToEdit.message
			}

			return this.text === this.messageToEdit.message
				&& this.scheduleMessageTime === this.messageToEdit.timestamp * 1_000
				&& this.silentChat === this.messageToEdit.silent
				&& (!this.threadCreating || this.threadTitle === this.messageToEdit.threadTitle)
		},

		placeholderText() {
			if (this.isReadOnly) {
				return t('spreed', 'This conversation has been locked')
			} else if (this.noChatPermission) {
				return t('spreed', 'No permission to post messages in this conversation')
			} else if (!this.currentConversationIsJoined) {
				return t('spreed', 'Joining conversation …')
			} else if (this.silentChat) {
				return t('spreed', 'Write a message without notification')
			} else {
				// Use the default placeholder
				return undefined
			}
		},

		sendMessageLabel() {
			if (this.threadCreating) {
				return this.silentChat ? t('spreed', 'Create a thread silently') : t('spreed', 'Create a thread')
			}
			return this.silentChat ? t('spreed', 'Send message silently') : t('spreed', 'Send message')
		},

		parentMessage() {
			const parentId = this.chatExtrasStore.getParentIdToReply(this.token)
			return parentId && this.$store.getters.message(this.token, parentId)
		},

		messageToEdit() {
			const messageToEditId = this.chatExtrasStore.getMessageIdToEdit(this.token)
			if (!messageToEditId) {
				return undefined
			}
			return (this.showScheduledMessages)
				? this.chatExtrasStore.getScheduledMessage(this.token, messageToEditId)
				: this.$store.getters.message(this.token, messageToEditId)
		},

		canShareFiles() {
			return !this.actorStore.isActorGuest
				&& !this.conversation.remoteServer // no attachments support in federated conversations
				&& !this.scheduleMessageTime && !this.showScheduledMessages
		},

		canUploadFiles() {
			// TODO attachments should be allowed on both instances?
			return getTalkConfig(this.token, 'attachments', 'allowed') && this.canShareFiles
				&& this.settingsStore.attachmentFolderFreeSpace !== 0
				&& !this.scheduleMessageTime && !this.showScheduledMessages
		},

		canCreatePoll() {
			return !this.isOneToOne && !this.noChatPermission
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
				&& !this.scheduleMessageTime && !this.showScheduledMessages
		},

		currentConversationIsJoined() {
			return this.tokenStore.currentConversationIsJoined
		},

		currentUploadId() {
			return this.$store.getters.currentUploadId
		},

		hasText() {
			return this.text.trim() !== ''
		},

		containerElement() {
			return document.querySelector(this.container)
		},

		isOneToOne() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		silentSendLabel() {
			return t('spreed', 'Send without notification')
		},

		silentSendInfo() {
			return this.isOneToOne
				? t('spreed', 'The participant will not be notified about new messages')
				: t('spreed', 'Participants will not be notified about new messages')
		},

		showSendActions() {
			return !this.broadcast && !this.isRecordingAudio && (!this.messageToEdit || this.showScheduledMessages)
		},

		showAttachmentsMenu() {
			return (this.canUploadFiles || this.canShareFiles || this.canCreatePoll || this.canCreateThread)
				&& !this.broadcast && !this.upload && !this.messageToEdit
		},

		showAudioRecorder() {
			return !this.hasText && this.canUploadFiles && !this.broadcast && !this.upload
				&& !this.messageToEdit && !this.threadCreating
				&& !this.scheduleMessageTime && !this.showScheduledMessages
		},

		showTypingStatus() {
			return this.hasTypingIndicator && this.supportTypingStatus
				&& this.settingsStore.typingStatusPrivacy === PRIVACY.PUBLIC
		},

		userAbsence() {
			return this.groupwareStore.absence[this.token]
		},

		showChatSummary() {
			return this.chatExtrasStore.hasChatSummaryTaskRequested(this.token)
		},

		isMobileDevice() {
			return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
		},

		chatInput() {
			return this.chatExtrasStore.getChatInput(this.token)
		},

		chatEditInput() {
			return this.chatExtrasStore.getChatEditInput(this.token)
		},

		showMentionEditHint() {
			if (this.scheduleMessageTime) {
				// Do not show hint for scheduled messages, as no notifications yet created
				return false
			}

			const mentionPattern = /(^|\s)@/
			return mentionPattern.test(this.chatEditInput)
		},

		canEditMessage() {
			return hasTalkFeature(this.token, 'edit-messages')
				&& !this.isReadOnly && !this.noChatPermission
				&& !this.actorStore.isActorGuest
		},

		supportThreads() {
			return hasTalkFeature(this.token, 'threads')
		},

		canCreateThread() {
			return this.supportThreads && !this.isReadOnly && !this.noChatPermission
				&& !this.threadId && !this.broadcast && !this.threadCreating
		},

		threadTitle: {
			get() {
				return this.chatExtrasStore.getThreadTitle(this.token)
			},

			set(value) {
				this.chatExtrasStore.setThreadTitle(this.token, value)
			},
		},

		threadCreating() {
			return this.threadTitle !== undefined
		},

		showScheduledMessagesToggle() {
			return this.conversation.hasScheduledMessages
				&& !this.dialog
				&& !this.isRecordingAudio
				&& !this.messageToEdit
		},

		showScheduledMessagesToggleVariant() {
			if (this.conversation.hasScheduledMessages === -1) {
				return 'error'
			}
			return this.showScheduledMessages ? 'secondary' : 'tertiary'
		},

		showScheduledMessages() {
			return this.chatExtrasStore.showScheduledMessages
		},

		scheduleMessageHint() {
			// FIXME use relative date and time (like in StaticDateTime)
			const datetimeString = new Date(this.scheduleMessageTime).toLocaleString(undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			})

			// TRANSLATORS: "... sent tomorrow, <March 18, 2026>"
			return t('spreed', 'Message will be sent {datetimeString}', { datetimeString })
		},
	},

	watch: {
		currentConversationIsJoined() {
			this.focusInput()
		},

		currentUploadId(value) {
			if (value && !this.upload) {
				this.text = ''
			} else if (!value && !this.upload) {
				// reset or fill main input in chat view from the store
				this.text = this.chatInput
			}
			// update the silent chat state
			this.silentChat = !!BrowserStorage.getItem('silentChat_' + this.token)
		},

		text(newValue) {
			this.errorMessage = ''
			if (this.currentUploadId && !this.upload) {
				return
			} else if (this.dialog && this.broadcast) {
				return
			}
			this.debouncedUpdateChatInput(newValue)
		},

		threadTitle(newValue) {
			this.errorTitle = ''
		},

		messageToEdit(newValue, oldValue) {
			if (newValue?.id === oldValue?.id) {
				// Currently edited message was updated, keep cursor position
				return
			} else if (newValue) {
				// Enter editing mode or editing another message
				this.text = this.chatExtrasStore.getChatEditInput(this.token)

				// Clear thread title when editing a message (unless it's a scheduled thread)
				if (newValue.threadId !== -1) {
					this.chatExtrasStore.removeThreadTitle(this.token)
				}

				if (this.showScheduledMessages) {
					this.chatExtrasStore.setScheduleMessageTime(newValue.timestamp * 1_000)
					this.silentChat = newValue.silent
				}

				if (this.parentMessage) {
					this.chatExtrasStore.removeParentIdToReply(this.token)
				}
			} else {
				// Leaving editing mode
				this.text = this.chatInput
			}

			this.$nextTick(() => {
				// set cursor at the end
				selectRange(getRangeAtEnd(this.getContenteditable()), this.getContenteditable())
			})
		},

		showScheduledMessages() {
			this.handleAbortEdit()
		},

		parentMessage(newValue) {
			if (newValue) {
				this.chatExtrasStore.removeThreadTitle(this.token)
				if (this.messageToEdit) {
					this.chatExtrasStore.removeMessageIdToEdit(this.token)
				}
			}
		},

		threadId(newValue) {
			if (newValue) {
				this.setCreateThread(false)
				this.focusInput()
			}
		},

		chatInput(newValue) {
			if (this.currentUploadId && !this.upload) {
				return
			}

			if (parseSpecialSymbols(this.text) !== newValue) {
				this.text = newValue
			}
		},

		token: {
			immediate: true,
			handler(token) {
				if (token) {
					this.text = this.messageToEdit
						? this.chatEditInput
						: this.chatInput
					this.silentChat = !!BrowserStorage.getItem('silentChat_' + this.token)
				} else {
					this.text = ''
				}
				this.clearTypingInterval()
				this.checkAbsenceStatus()
				this.clearSilentState()
				this.chatExtrasStore.setScheduleMessageTime(null)
			},
		},

		submenu(newValue) {
			if (newValue === 'schedule') {
				if (this.scheduleMessageTime) {
					this.customScheduleTimestamp = new Date(this.scheduleMessageTime)
				} else {
					const dateInFuture = new Date()
					dateInFuture.setHours(dateInFuture.getHours() + 1, 0, 0, 0)
					this.customScheduleTimestamp = dateInFuture
				}
			} else {
				this.customScheduleTimestamp = null
			}
		},
	},

	created() {
		useHotKey('c', this.focusInput, { stop: true, prevent: true })
	},

	mounted() {
		EventBus.on('focus-chat-input', this.focusInput)
		EventBus.on('upload-start', this.handleUploadSideEffects)
		EventBus.on('upload-discard', this.handleUploadSideEffects)
		EventBus.on('retry-message', this.handleRetryMessage)
		EventBus.on('smart-picker-open', this.handleOpenTributeMenu)

		if (!this.$store.getters.areFileTemplatesInitialised) {
			this.$store.dispatch('getFileTemplates')
		}
	},

	beforeUnmount() {
		EventBus.off('focus-chat-input', this.focusInput)
		EventBus.off('upload-start', this.handleUploadSideEffects)
		EventBus.off('upload-discard', this.handleUploadSideEffects)
		EventBus.off('retry-message', this.handleRetryMessage)
		EventBus.off('smart-picker-open', this.handleOpenTributeMenu)
	},

	methods: {
		t,

		getCustomDateOptions,

		getContenteditable() {
			return this.$refs.richContenteditable.$refs.contenteditable
		},

		handleTyping() {
			// Enable signal sending, only if indicator for this input is on
			if (!this.showTypingStatus || this.messageToEdit) {
				return
			}

			if (!this.typingInterval) {
				// Send first signal after first keystroke
				this.$store.dispatch('sendTypingSignal', { typing: true })

				// Continuously send signals with 10s interval if still typing
				this.typingInterval = setInterval(() => {
					if (this.wasTypingWithinInterval) {
						this.$store.dispatch('sendTypingSignal', { typing: true })
						this.wasTypingWithinInterval = false
					} else {
						this.clearTypingInterval()
					}
				}, 10000)
			} else {
				this.wasTypingWithinInterval = true
			}
		},

		clearTypingInterval() {
			clearInterval(this.typingInterval)
			this.typingInterval = null
			this.wasTypingWithinInterval = false
		},

		resetTypingIndicator() {
			this.$store.dispatch('sendTypingSignal', { typing: false })
			if (this.typingInterval) {
				this.clearTypingInterval()
			}
		},

		updateChatInput(text) {
			if (this.messageToEdit) {
				this.chatExtrasStore.setChatEditInput({
					token: this.token,
					text,
					parameters: this.messageToEdit.messageParameters,
				})
			} else if (text && text !== this.chatInput) {
				this.chatExtrasStore.setChatInput({ token: this.token, text })
			} else if (!text && this.chatInput) {
				this.chatExtrasStore.removeChatInput(this.token)
			}
		},

		handleUploadSideEffects() {
			if (this.upload) {
				return
			}
			this.$nextTick(() => {
				// refocus input as the user might want to type further
				this.focusInput()
			})
		},

		async handleSubmit() {
			// Submit event has enter key listener
			// Handle edit here too
			if (this.messageToEdit) {
				if (!this.disabledEdit) {
					this.handleEdit()
				}
				return
			}
			if (OC.debug && this.text.startsWith('/spam ')) {
				const pattern = /^\/spam (\d+) messages$/i
				const match = pattern.exec(this.text)
				// Escape HTML
				if (match) {
					await this.handleSubmitSpam(match[1])
					return
				}
			}

			if (this.hasText) {
				this.text = parseSpecialSymbols(this.text)
			}

			if (this.threadCreating) {
				if (!this.threadTitle) {
					// TRANSLATORS Error indicator: do not allow to create a thread without a thread title
					this.errorTitle = t('spreed', 'Thread title is required')
				}
				if (!this.hasText) {
					// TRANSLATORS Error indicator: do not allow to create a thread without a message text
					this.errorMessage = t('spreed', 'Message text is required')
				}
				if (this.errorTitle || this.errorMessage) {
					return
				}
			}

			if (supportScheduleMessages && this.scheduleMessageTime) {
				await this.handleScheduleMessage()
				return
			}

			if (supportScheduleMessages && !this.scheduleMessageTime && this.showScheduledMessages) {
				// Block sending and prompt user to pick a time for scheduling
				this.submenu = 'schedule'
				this.isNcActionsOpen = true
				return
			}

			// Clear input content from store
			this.debouncedUpdateChatInput.clear()
			this.chatExtrasStore.removeChatInput(this.token)

			this.chatExtrasStore.setShowScheduledMessages(false)

			if (this.hasText || (this.dialog && this.upload)) {
				const temporaryMessagePayload = {
					message: this.text.trim(),
					token: this.token,
					silent: this.silentChat,
				}

				if (this.threadId) {
					temporaryMessagePayload.threadId = this.threadId
					temporaryMessagePayload.isThread = true
				}
				if (this.parentMessage) {
					temporaryMessagePayload.parent = this.parentMessage
				}
				if (this.threadCreating) {
					temporaryMessagePayload.threadTitle = this.threadTitle.trim()
					temporaryMessagePayload.threadReplies = 0
					temporaryMessagePayload.isThread = true
				}

				const temporaryMessage = this.createTemporaryMessage(temporaryMessagePayload)
				this.text = ''
				this.chatExtrasStore.removeThreadTitle(this.token)

				// Reset the hash from focused message id (but keep the thread id)
				this.$router.replace({ query: this.$route.query, hash: '' })
				// Scrolls the message list to the last added message
				EventBus.emit('scroll-chat-to-bottom', { smooth: true, force: true })
				// Also remove the message to be replied for this conversation
				this.chatExtrasStore.removeParentIdToReply(this.token)

				this.dialog
					? await this.submitMessage(this.token, temporaryMessage)
					: await this.postMessage(this.token, temporaryMessage)
				this.resetTypingIndicator()
			}
		},

		// Post message to conversation
		async postMessage(token, temporaryMessage) {
			try {
				await this.$store.dispatch('postNewMessage', { token, temporaryMessage })
			} catch (e) {
				console.error(e)
			}
		},

		// Broadcast message to all breakout rooms
		async submitMessage(token, temporaryMessage) {
			this.$emit('submit', { token, temporaryMessage })
		},

		async handleSubmitSpam(numberOfMessages) {
			console.debug('Sending ' + numberOfMessages + ' lorem ipsum messages')
			for (let i = 0; i < numberOfMessages; i++) {
				const randomNumber = Math.floor(Math.random() * 500)
				console.debug('[' + i + '/' + numberOfMessages + '] Sleeping ' + randomNumber + 'ms')
				await this.sleep(randomNumber)

				const loremIpsum = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.\n\nDuis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.'
				this.text = loremIpsum.slice(0, 25 + randomNumber)
				await this.handleSubmit()
			}
		},

		async handleEdit() {
			try {
				if (this.showScheduledMessages) {
					await this.chatExtrasStore.editScheduledMessage(this.token, this.messageToEdit.id, {
						message: parseSpecialSymbols(this.text.trim()),
						sendAt: convertToUnix(this.scheduleMessageTime),
						silent: this.silentChat,
						threadTitle: this.threadTitle,
					})
				} else {
					await this.$store.dispatch('editMessage', {
						token: this.token,
						messageId: this.messageToEdit.id,
						updatedMessage: parseSpecialSymbols(this.text.trim()),
					})
				}
				EventBus.emit('focus-message', { messageId: this.messageToEdit.id })
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
				this.chatExtrasStore.removeThreadTitle(this.token)
				this.chatExtrasStore.setScheduleMessageTime(null)
				this.silentChat = false
				this.resetTypingIndicator()
				// refocus input as the user might want to type further
				this.focusInput()
			} catch {
				this.$emit('dismiss')
				if (this.showScheduledMessages) {
					showError(t('spreed', 'Error when scheduling the message'))
				} else {
					showError(t('spreed', 'The message could not be edited'))
				}
			}
		},

		sleep(ms) {
			return new Promise((resolve) => setTimeout(resolve, ms))
		},

		handleRetryMessage(id) {
			if (this.text === '') {
				const temporaryMessage = this.$store.getters.message(this.token, id)
				if (temporaryMessage) {
					this.text = temporaryMessage.message || this.text

					// Restore the parent/quote message
					if (temporaryMessage.parent) {
						this.chatExtrasStore.setParentIdToReply({
							token: this.token,
							id: temporaryMessage.parent.id,
						})
					}

					this.$store.dispatch('removeTemporaryMessageFromStore', { token: this.token, id })
				}
			}
		},

		setCreateThread(value) {
			if (value) {
				this.chatExtrasStore.setThreadTitle(this.token, '')
				this.chatExtrasStore.removeParentIdToReply(this.token)
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
				this.$nextTick(() => {
					this.threadTitleInputRef.focus()
				})
			} else {
				this.chatExtrasStore.removeThreadTitle(this.token)
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
				this.chatExtrasStore.setScheduleMessageTime(null)
			}
		},

		async showFilePicker() {
			const filePicker = getFilePickerBuilder(t('spreed', 'File to share'))
				.setMultiSelect(true)
				.allowDirectories(true)
				.addButton({
					label: t('spreed', 'Choose'),
					callback: (nodes) => this.handleFileShare(nodes),
					variant: 'primary',
				})
				.build()
			await filePicker.pickNodes()
		},

		handleFileShare(nodes) {
			nodes.forEach(({ path }) => {
				console.debug(`path ${path} selected for sharing`)
				if (!path.startsWith('/')) {
					throw new Error(t('files', 'Invalid path selected'))
				}
				this.focusInput()

				const talkMetaData = JSON.stringify(Object.assign(
					this.threadId ? { threadId: this.threadId } : {},
					this.parentMessage?.id ? { replyTo: this.parentMessage?.id } : {},
				))
				this.chatExtrasStore.removeParentIdToReply(this.token)

				this.$store.dispatch('shareFile', { token: this.token, path, talkMetaData })
			})
		},

		handleOpenTributeMenu() {
			this.$refs.richContenteditable.showTribute('/')
		},

		/**
		 * Clicks the hidden file input when clicking the correspondent NcActionButton,
		 * thus opening the file-picker
		 */
		openFileUploadWindow() {
			this.$refs.fileUploadInput.click()
		},

		updateNewFileDialog(value) {
			this.showNewFileDialog = value
		},

		handleFileInput(event) {
			const files = Object.values(event.target.files)

			this.handleFiles(files)

			// Clear input to ensure that the change event will be emitted if
			// the same file is picked again.
			event.target.value = ''
		},

		/**
		 * Handles files pasting event
		 *
		 * @param {ClipboardEvent} e native paste event
		 */
		async handlePastedFiles(e) {
			if (this.messageToEdit || this.scheduleMessageTime) {
				return
			}
			e.preventDefault()
			// Prevent a new call of this.handleFiles if already called
			if (this.clipboardTimeStamp === e.timeStamp) {
				return
			}

			this.clipboardTimeStamp = e.timeStamp
			const content = fetchClipboardContent(e)
			if (content.kind === 'file') {
				this.handleFiles(content.files, true)
			} else {
				// FIXME NcRichContenteditable prevents trigger input event on pasting text
				this.handleTyping()
			}
		},

		/**
		 * Handles file upload
		 *
		 * @param {File[] | FileList} files pasted files list
		 * @param {boolean} rename whether to rename the files
		 * @param {boolean} isVoiceMessage indicates whether the file is a voice message
		 */
		handleFiles(files, rename = false, isVoiceMessage = false) {
			if (!this.canUploadFiles) {
				showWarning(t('spreed', 'File upload is not available in this conversation'))
				return
			}
			// Create a unique id for the upload operation
			const uploadId = this.currentUploadId ?? new Date().getTime()
			// Uploads and shares the files
			this.$store.dispatch('initialiseUpload', { files, token: this.token, threadId: this.threadId, uploadId, rename, isVoiceMessage })
		},

		preserveSelectionRange() {
			this.preservedSelectionRange = getCurrentSelectionRange(this.getContenteditable())
		},

		restoreSelectionRange() {
			selectRange(this.preservedSelectionRange, this.getContenteditable())
			this.preservedSelectionRange = null
		},

		/**
		 * Add selected emoji to the cursor position
		 *
		 * @param {string} emoji - Selected emoji
		 */
		addEmoji(emoji) {
			insertTextInElement(emoji, this.getContenteditable(), this.preservedSelectionRange)
			// FIXME: add a method to NcRichContenteditable to handle manual update
			this.$refs.richContenteditable.updateValue(this.getContenteditable().innerHTML)
		},

		handleAudioFile(payload) {
			this.handleFiles([payload], false, true)
		},

		handleRecording(payload) {
			this.isRecordingAudio = payload
		},

		async focusInput() {
			if (this.isMobileDevice) {
				return
			}
			await nextTick()
			this.$refs.richContenteditable.focus()
			this.restoreSelectionRange()
		},

		blurInput() {
			document.activeElement.blur()
		},

		handleInputEsc() {
			if (this.messageToEdit) {
				this.handleAbortEdit()
				this.focusInput()
				return
			}
			this.blurInput()
		},

		handleEditLastMessage(event) {
			if (!this.canEditMessage || this.text || this.dialog || this.isRecordingAudio) {
				return
			}

			// last message within 24 hours
			const lastMessageByCurrentUser = this.chatStore.getMessagesList(this.token, {
				threadId: this.threadId,
			}).findLast((message) => {
				return this.actorStore.checkIfSelfIsActor(message)
					&& !message.isTemporary && !message.systemMessage
					&& (Date.now() - message.timestamp * 1000 < ONE_DAY_IN_MS)
			})

			if (!lastMessageByCurrentUser) {
				return
			}

			event.preventDefault()
			this.chatExtrasStore.initiateEditingMessage({
				token: this.token,
				id: lastMessageByCurrentUser.id,
				message: lastMessageByCurrentUser.message,
				messageParameters: lastMessageByCurrentUser.messageParameters,
			})
		},

		async checkAbsenceStatus() {
			if (!this.isOneToOne) {
				return
			}

			// TODO replace with status message id 'vacationing'
			if (this.conversation.status === 'dnd') {
				// Fetch actual absence status from server
				await this.groupwareStore.getUserAbsence({
					token: this.token,
					userId: this.conversation.name,
				})
			} else {
				// Remove stored absence status
				this.groupwareStore.removeUserAbsence(this.token)
			}
		},

		handleAbortEdit() {
			this.chatExtrasStore.removeMessageIdToEdit(this.token)
			this.chatExtrasStore.removeThreadTitle(this.token)
			this.chatExtrasStore.setScheduleMessageTime(null)
			this.silentChat = false
		},

		toggleSilentChat() {
			this.silentChat = !this.silentChat
			if (this.silentChat) {
				BrowserStorage.setItem('silentChat_' + this.token, 'true')
			} else {
				BrowserStorage.removeItem('silentChat_' + this.token)
			}
		},

		clearSilentState() {
			// FIXME text that is only one line should be cleared in upstream
			if ((this.text === '' || this.text === '\n') && this.silentChat && !this.upload) {
				this.toggleSilentChat()
			}
		},

		async handleScheduleMessage() {
			if (this.dialog && this.upload) {
				// FIXME handle file upload in scheduled messages
				return
			}

			if (this.hasText) {
				const scheduleMessagePayload = {
					message: this.text.trim(),
					sendAt: convertToUnix(this.scheduleMessageTime),
				}

				if (this.silentChat) {
					scheduleMessagePayload.silent = true
				}
				if (this.threadId) {
					scheduleMessagePayload.threadId = this.threadId
				}
				if (this.parentMessage) {
					scheduleMessagePayload.replyTo = this.parentMessage.id
				}
				if (this.threadCreating) {
					scheduleMessagePayload.threadTitle = this.threadTitle.trim()
				}

				try {
					this.isScheduling = true
					await this.chatExtrasStore.scheduleMessage(this.token, scheduleMessagePayload)

					// Clear input content from store
					this.text = ''
					this.chatExtrasStore.removeThreadTitle(this.token)
					this.chatExtrasStore.setScheduleMessageTime(null)
					this.silentChat = false
					this.chatExtrasStore.removeParentIdToReply(this.token)
					this.debouncedUpdateChatInput.clear()
					this.chatExtrasStore.removeChatInput(this.token)
					this.resetTypingIndicator()
					showSuccess(t('spreed', 'Message was successfully scheduled'))
					this.isScheduling = false
				} catch (error) {
					showError(t('spreed', 'Error when scheduling the message'))
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use '../../assets/variables.scss' as *;

.wrapper {
	padding: calc(var(--default-grid-baseline) * 2);
	min-height: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 2);
}

.wrapper--narrow {
	padding: var(--default-grid-baseline);

	.new-message-form__input > .new-message-form__hint,
	.new-message-form__input > .new-message-form__quote,
	.new-message-form__input > .new-message-form__thread-title {
		width: calc(var(--app-sidebar-width) - 2 * var(--default-grid-baseline));
	}

	.new-message-form__input > .new-message-form__note-content {
		width: calc(var(--app-sidebar-width) - 2 * var(--default-grid-baseline));
		margin-inline: 0 !important;
	}

	.new-message-form__attachments + .new-message-form__input > .new-message-form__hint,
	.new-message-form__attachments + .new-message-form__input > .new-message-form__quote,
	.new-message-form__attachments + .new-message-form__input > .new-message-form__thread-title {
		margin-inline-start: calc(-1 * var(--default-clickable-area) - var(--default-grid-baseline));
	}

	.new-message-form__attachments + .new-message-form__input > .new-message-form__note-content {
		margin-inline-start: calc(-1 * var(--default-clickable-area) - var(--default-grid-baseline)) !important;
	}
}

.new-message-form {
	--emoji-button-size: calc(var(--default-clickable-area) - var(--border-width-input-focused, 2px) * 2);
	--emoji-button-radius: calc(var(--border-radius-element, calc(var(--button-size) / 2)) - var(--border-width-input-focused, 2px));
	align-items: flex-end;
	display: flex;
	gap: var(--default-grid-baseline);
	position: relative;
	max-width: $messages-input-max-width;
	margin: 0 auto;

	&__emoji-picker {
		position: absolute;
		bottom: var(--border-width-input-focused, 2px);
		inset-inline-start: var(--border-width-input-focused, 2px);
		z-index: 1;

		:deep(.button-vue) {
			// Overwrite NcButton styles to fit inside NcRichContenteditable
			--button-size: var(--emoji-button-size) !important;
			--button-radius: var(--emoji-button-radius) !important;
		}
	}

	&__input {
		flex-grow: 1;
		position: relative;
		min-width: 0;
	}

	// Override NcRichContenteditable styles
	:deep(.rich-contenteditable__input) {
		--contenteditable-space: calc((var(--default-clickable-area) - 1lh - 4px) / 2);
		--contenteditable-block-offset: var(--contenteditable-space);
		--contenteditable-inline-end-offset: var(--contenteditable-space);
		--contenteditable-inline-start-offset: calc(var(--emoji-button-size) + var(--contenteditable-space));
	}

	&__quote {
		margin-block-end: var(--default-grid-baseline);
		background-color: var(--color-background-hover);
		border-radius: var(--border-radius-large);
	}

	&__hint {
		// Overwrite NcNoteCard styles
		margin-block: var(--default-grid-baseline) !important;

		&--scheduled > :deep(div) {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: calc(var(--default-grid-baseline) / 2);
			width: 100%;
		}
	}

	&__thread-title {
		margin-bottom: var(--default-grid-baseline);

		& + :deep(.rich-contenteditable > .rich-contenteditable__input) {
			min-height: calc(2lh + 2 * var(--contenteditable-block-offset) + 4px);
		}
	}

	&__input-rich {
		&--required :deep(.rich-contenteditable__input) {
			border-color: var(--color-border-error) !important;
		}
	}

	// put a grey round background when popover is opened or hover-focused
	&__icon:hover,
	&__icon:focus,
	&__icon:active {
		opacity: 1;
		// good looking on dark AND white bg
		background-color: rgba(127, 127, 127, .25);
	}
}

// Override actions styles TODO: upstream this change
:deep(.action-item__menutoggle) {
	opacity: 1 !important;

	&:hover,
	&:focus {
		background-color: var(--color-background-hover) !important;
	}

}

</style>
