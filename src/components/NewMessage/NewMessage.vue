<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="wrapper">
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
				:token="token"
				:disabled="disabled"
				:can-upload-files="canUploadFiles"
				:can-share-files="canShareFiles"
				:can-create-poll="canCreatePoll"
				:can-create-thread="canCreateThread"
				@open-file-upload="openFileUploadWindow"
				@create-thread="setCreateThread"
				@handle-file-share="showFilePicker"
				@update-new-file-dialog="updateNewFileDialog" />

			<!-- Input area -->
			<div class="new-message-form__input">
				<NewMessageAbsenceInfo
					v-if="!dialog && userAbsence"
					:user-absence="userAbsence"
					:display-name="conversation.displayName" />

				<NewMessageChatSummary v-if="!dialog && showChatSummary" />

				<div class="new-message-form__emoji-picker">
					<NcEmojiPicker
						v-if="!disabled"
						keep-open
						:set-return-focus="getContenteditable"
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
						:can-cancel="!!parentMessage"
						:edit-message="!!messageToEdit" />
				</div>
				<!-- mention editing hint -->
				<NcNoteCard
					v-if="showMentionEditHint"
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
					show-trailing-button
					@trailing-button-click="setCreateThread(false)" />
				<NcRichContenteditable
					ref="richContenteditable"
					:key="container"
					v-model="text"
					:class="{ 'new-message-form__input-rich--required': errorMessage }"
					:title="errorMessage"
					:auto-complete="autoComplete"
					:disabled="disabled"
					:user-data="userData"
					:menu-container="containerElement"
					:placeholder="placeholderText"
					:aria-label="placeholderText"
					:dir="text ? 'auto' : undefined"
					@keydown.esc="handleInputEsc"
					@keydown.ctrl.up="handleEditLastMessage"
					@keydown.meta.up="handleEditLastMessage"
					@update:model-value="handleTyping"
					@paste="handlePastedFiles"
					@focus="restoreSelectionRange"
					@blur="preserveSelectionRange"
					@submit="handleSubmit" />
			</div>

			<!-- Silent chat -->
			<NcActions
				v-if="showSilentToggle"
				force-menu
				:primary="silentChat">
				<template #icon>
					<IconBellOffOutline v-if="silentChat" :size="20" />
				</template>
				<NcActionButton
					close-after-click
					:model-value="silentChat"
					:description="silentSendInfo"
					@click="toggleSilentChat">
					{{ silentSendLabel }}
					<template #icon>
						<IconBellOffOutline :size="20" />
					</template>
				</NcActionButton>
			</NcActions>

			<!-- Audio recorder -->
			<NewMessageAudioRecorder
				v-if="showAudioRecorder"
				:disabled="disabled"
				@recording="handleRecording"
				@audio-file="handleAudioFile" />

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
			:show-new-file-dialog="showNewFileDialog"
			@dismiss="showNewFileDialog = -1" />
	</div>
</template>

<script>
import { showError, showWarning } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import debounce from 'debounce'
import { nextTick, toRefs, useTemplateRef } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcRichContenteditable from '@nextcloud/vue/components/NcRichContenteditable'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import IconForumOutline from 'vue-material-design-icons/ForumOutline.vue'
import IconSend from 'vue-material-design-icons/Send.vue' // Filled for better indication
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
import { useSettingsStore } from '../../stores/settings.js'
import { useTokenStore } from '../../stores/token.ts'
import { fetchClipboardContent } from '../../utils/clipboard.js'
import { ONE_DAY_IN_MS } from '../../utils/formattedTime.ts'
import { getCurrentSelectionRange, insertTextInElement, selectRange } from '../../utils/selectionRange.ts'
import { parseSpecialSymbols } from '../../utils/textParse.ts'

export default {
	name: 'NewMessage',

	components: {
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
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
		IconBellOffOutline,
		IconCheck,
		IconClose,
		IconEmoticonOutline,
		IconForumOutline,
		IconSend,
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

		return {
			actorStore: useActorStore(),
			chatExtrasStore: useChatExtrasStore(),
			groupwareStore: useGroupwareStore(),
			chatStore: useChatStore(),
			settingsStore: useSettingsStore(),
			tokenStore: useTokenStore(),
			supportTypingStatus,
			autoComplete,
			userData,
			threadId,
			threadTitleInputRef,
			createTemporaryMessage,
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

		disabledEdit() {
			return this.disabled || this.text === this.messageToEdit.message || this.text === ''
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
			return messageToEditId && this.$store.getters.message(this.token, messageToEditId)
		},

		canShareFiles() {
			return !this.actorStore.isActorGuest
				&& !this.conversation.remoteServer // no attachments support in federated conversations
		},

		canUploadFiles() {
			// TODO attachments should be allowed on both instances?
			return getTalkConfig(this.token, 'attachments', 'allowed') && this.canShareFiles
				&& this.$store.getters.getAttachmentFolderFreeSpace() !== 0
		},

		canCreatePoll() {
			return !this.isOneToOne && !this.noChatPermission
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
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

		showSilentToggle() {
			return !this.broadcast && !this.isRecordingAudio && !this.messageToEdit
		},

		showAttachmentsMenu() {
			return (this.canUploadFiles || this.canShareFiles || this.canCreatePoll) && !this.broadcast && !this.upload && !this.messageToEdit
		},

		showAudioRecorder() {
			return !this.hasText && this.canUploadFiles && !this.broadcast && !this.upload && !this.messageToEdit && !this.threadCreating
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
			const mentionPattern = /(^|\s)@/
			return mentionPattern.test(this.chatEditInput)
		},

		canEditMessage() {
			return hasTalkFeature(this.token, 'edit-messages')
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

		messageToEdit(newValue) {
			if (newValue) {
				this.text = this.chatExtrasStore.getChatEditInput(this.token)
				this.chatExtrasStore.removeThreadTitle(this.token)
				if (this.parentMessage) {
					this.chatExtrasStore.removeParentIdToReply(this.token)
				}
			} else {
				this.text = this.chatInput
			}
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
			},
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

		getContenteditable() {
			return this.$refs.richContenteditable.$refs.contenteditable
		},

		handleTyping() {
			// Enable signal sending, only if indicator for this input is on
			if (!this.showTypingStatus) {
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

			// Clear input content from store
			this.debouncedUpdateChatInput.clear()
			this.chatExtrasStore.removeChatInput(this.token)

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
					// Substitute thread title with message text, if missing
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
				await this.$store.dispatch('editMessage', {
					token: this.token,
					messageId: this.messageToEdit.id,
					updatedMessage: parseSpecialSymbols(this.text.trim()),
				})
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
				this.resetTypingIndicator()
				// refocus input as the user might want to type further
				this.focusInput()
			} catch {
				this.$emit('dismiss')
				showError(t('spreed', 'The message could not be edited'))
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
				this.$store.dispatch('shareFile', { token: this.token, path })
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
			if (this.messageToEdit) {
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
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.wrapper {
	padding: calc(var(--default-grid-baseline) * 2);
	min-height: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 2);
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
// Targeting two classes for specificity
:deep(.action-item__menutoggle.action-item__menutoggle--with-icon-slot) {
	opacity: 1 !important;

	&:hover,
	&:focus {
		background-color: var(--color-background-hover) !important;
	}

	&:disabled {
		opacity: .5 !important;
	}
}

</style>
