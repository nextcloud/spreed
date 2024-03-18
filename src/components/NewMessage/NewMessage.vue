<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Grigorii Shartsev <me@shgk.me>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="wrapper">
		<NewMessageTypingIndicator v-if="showTypingStatus"
			:token="token" />

		<!--native file picker, hidden -->
		<input id="file-upload"
			ref="fileUploadInput"
			multiple
			type="file"
			tabindex="-1"
			aria-hidden="true"
			class="hidden-visually"
			@change="handleFileInput">

		<form class="new-message-form"
			@submit.prevent>
			<!-- Attachments menu -->
			<NewMessageAttachments v-if="showAttachmentsMenu"
				:token="token"
				:container="container"
				:boundaries-element="containerElement"
				:disabled="disabled"
				:can-upload-files="canUploadFiles"
				:can-share-files="canShareFiles"
				:can-create-poll="canCreatePoll"
				@open-file-upload="openFileUploadWindow"
				@handle-file-share="showFilePicker = true"
				@toggle-poll-editor="togglePollEditor"
				@update-new-file-dialog="updateNewFileDialog" />

			<!-- Input area -->
			<div class="new-message-form__input">
				<NewMessageAbsenceInfo v-if="!upload && userAbsence"
					:user-absence="userAbsence"
					:display-name="conversation.displayName" />

				<div class="new-message-form__emoji-picker">
					<NcEmojiPicker v-if="!disabled"
						:container="container"
						:close-on-select="false"
						@select="addEmoji">
						<NcButton :disabled="disabled"
							:aria-label="t('spreed', 'Add emoji')"
							type="tertiary-no-background"
							:aria-haspopup="true">
							<template #icon>
								<EmoticonOutline :size="16" />
							</template>
						</NcButton>
					</NcEmojiPicker>
					<!-- Disabled emoji picker placeholder button -->
					<NcButton v-else
						type="tertiary"
						:aria-label="t('spreed', 'Add emoji')"
						:disabled="true">
						<template #icon>
							<EmoticonOutline :size="16" />
						</template>
					</NcButton>
				</div>
				<div v-if="parentMessage || messageToEdit" class="new-message-form__quote">
					<Quote v-bind="messageToEdit ?? parentMessage"
						:can-cancel="!!parentMessage"
						:edit-message="!!messageToEdit" />
				</div>
				<!-- mention editing hint -->
				<NcNoteCard v-if="showMentionEditHint" type="warning">
					<p>{{ t('spreed','Adding a mention will only notify users who did not read the message.') }}</p>
				</NcNoteCard>
				<NcRichContenteditable ref="richContenteditable"
					v-shortkey.once="$options.disableKeyboardShortcuts ? null : ['c']"
					:value.sync="text"
					:auto-complete="autoComplete"
					:disabled="disabled"
					:user-data="userData"
					:menu-container="containerElement"
					:placeholder="placeholderText"
					:aria-label="placeholderText"
					dir="auto"
					@shortkey="focusInput"
					@keydown.esc="handleInputEsc"
					@keydown.ctrl.up="handleEditLastMessage"
					@input="handleTyping"
					@paste="handlePastedFiles"
					@submit="handleSubmit" />
			</div>

			<!-- Audio recorder -->
			<NewMessageAudioRecorder v-if="showAudioRecorder"
				:disabled="disabled"
				@recording="handleRecording"
				@audio-file="handleAudioFile" />

			<!-- Edit -->
			<template v-else-if="messageToEdit">
				<NcButton type="tertiary"
					native-type="submit"
					:title="t('spreed', 'Cancel editing')"
					:aria-label="t('spreed', 'Cancel editing')"
					@click="handleAbortEdit">
					<template #icon>
						<CloseIcon :size="20" />
					</template>
				</NcButton>
				<NcButton :disabled="disabledEdit"
					type="tertiary"
					native-type="submit"
					:title="t('spreed', 'Edit message')"
					:aria-label="t('spreed', 'Edit message')"
					@click="handleEdit">
					<template #icon>
						<CheckIcon :size="20" />
					</template>
				</NcButton>
			</template>

			<!-- Send buttons -->
			<template v-else>
				<NcActions v-if="!broadcast" :container="container" force-menu>
					<NcActionButton close-after-click
						:name="silentSendLabel"
						@click="toggleSilentChat">
						{{ silentSendInfo }}
						<template #icon>
							<BellIcon v-if="silentChat" :size="16" />
							<BellOffIcon v-else :size="16" />
						</template>
					</NcActionButton>
				</NcActions>

				<NcButton :disabled="disabled"
					type="tertiary"
					native-type="submit"
					:title="sendMessageLabel"
					:aria-label="sendMessageLabel"
					@click="handleSubmit">
					<template #icon>
						<SendVariantOutlineIcon v-if="silentChat" :size="18" />
						<SendIcon v-else :size="16" />
					</template>
				</NcButton>
			</template>
		</form>

		<!-- Poll creation dialog -->
		<NewMessagePollEditor v-if="showPollEditor"
			:token="token"
			@close="togglePollEditor" />

		<!-- New file creation dialog -->
		<NewMessageNewFileDialog v-if="showNewFileDialog !== -1"
			:token="token"
			:container="container"
			:show-new-file-dialog="showNewFileDialog"
			@dismiss="showNewFileDialog = -1" />

		<FilePickerVue v-if="showFilePicker"
			:name="t('spreed', 'File to share')"
			:container="container"
			:buttons="filePickerButtons"
			allow-pick-directory
			@close="showFilePicker = false" />
	</div>
</template>

<script>
import debounce from 'debounce'

import BellIcon from 'vue-material-design-icons/Bell.vue'
import BellOffIcon from 'vue-material-design-icons/BellOff.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import SendIcon from 'vue-material-design-icons/Send.vue'
import SendVariantOutlineIcon from 'vue-material-design-icons/SendVariantOutline.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'
import { FilePickerVue } from '@nextcloud/dialogs/filepicker.js'
import { generateUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'

import NewMessageAbsenceInfo from './NewMessageAbsenceInfo.vue'
import NewMessageAttachments from './NewMessageAttachments.vue'
import NewMessageAudioRecorder from './NewMessageAudioRecorder.vue'
import NewMessageNewFileDialog from './NewMessageNewFileDialog.vue'
import NewMessagePollEditor from './NewMessagePollEditor.vue'
import NewMessageTypingIndicator from './NewMessageTypingIndicator.vue'
import Quote from '../Quote.vue'

import { ATTENDEE, CONVERSATION, PARTICIPANT, PRIVACY } from '../../constants.js'
import { getConversationAvatarOcsUrl, getUserProxyAvatarOcsUrl } from '../../services/avatarService.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { EventBus } from '../../services/EventBus.js'
import { shareFile } from '../../services/filesSharingServices.js'
import { searchPossibleMentions } from '../../services/mentionsService.js'
import { useChatExtrasStore } from '../../stores/chatExtras.js'
import { useSettingsStore } from '../../stores/settings.js'
import { fetchClipboardContent } from '../../utils/clipboard.js'
import { isDarkTheme } from '../../utils/isDarkTheme.js'
import { parseSpecialSymbols } from '../../utils/textParse.ts'

const disableKeyboardShortcuts = OCP.Accessibility.disableKeyboardShortcuts()
const supportTypingStatus = getCapabilities()?.spreed?.config?.chat?.['typing-privacy'] !== undefined
const canEditMessage = getCapabilities()?.spreed?.features?.includes('edit-messages')
const attachmentsAllowed = getCapabilities()?.spreed?.config?.attachments?.allowed
const supportFederationV1 = getCapabilities()?.spreed?.features?.includes('federation-v1')

export default {
	name: 'NewMessage',

	disableKeyboardShortcuts,

	components: {
		FilePickerVue,
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
		NcNoteCard,
		NcRichContenteditable,
		NewMessageAbsenceInfo,
		NewMessageAttachments,
		NewMessageAudioRecorder,
		NewMessageNewFileDialog,
		NewMessagePollEditor,
		NewMessageTypingIndicator,
		Quote,
		// Icons
		BellIcon,
		BellOffIcon,
		CheckIcon,
		CloseIcon,
		EmoticonOutline,
		SendIcon,
		SendVariantOutlineIcon,
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
		 * Container must be mounted before passing its ID as a prop
		 */
		container: {
			type: String,
			required: true,
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

	emits: ['sent', 'failure', 'upload'],

	expose: ['focusInput'],

	setup() {
		const chatExtrasStore = useChatExtrasStore()
		const settingsStore = useSettingsStore()

		return {
			chatExtrasStore,
			settingsStore,
			supportTypingStatus,
		}
	},

	data() {
		return {
			text: '',
			silentChat: false,
			// True when the audio recorder component is recording
			isRecordingAudio: false,
			showPollEditor: false,
			showNewFileDialog: -1,
			showFilePicker: false,
			// Check empty template by default
			userData: {},
			clipboardTimeStamp: null,
			typingInterval: null,
			wasTypingWithinInterval: false,
			debouncedUpdateChatInput: debounce(this.updateChatInput, 200)
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

		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
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
			} else {
				// Use the default placeholder
				return undefined
			}
		},

		sendMessageLabel() {
			if (this.silentChat) {
				return t('spreed', 'Send message silently')
			} else {
				return t('spreed', 'Send message')
			}
		},

		parentMessage() {
			const parentId = this.chatExtrasStore.getParentIdToReply(this.token)
			return parentId && this.$store.getters.message(this.token, parentId)
		},

		messageToEdit() {
			const messageToEditId = this.chatExtrasStore.getMessageIdToEdit(this.token)
			return messageToEditId && this.$store.getters.message(this.token, messageToEditId)
		},

		currentUserIsGuest() {
			return this.$store.getters.getUserId() === null
		},

		canShareFiles() {
			return !this.currentUserIsGuest
				&& (!supportFederationV1 || !this.conversation.remoteServer)
		},

		canUploadFiles() {
			return attachmentsAllowed && this.canShareFiles
				&& this.$store.getters.getAttachmentFolderFreeSpace() !== 0
		},

		canCreatePoll() {
			return !this.isOneToOne && !this.noChatPermission
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
		},

		currentConversationIsJoined() {
			return this.$store.getters.currentConversationIsJoined
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
			return this.silentChat
				? t('spreed', 'Send with notification')
				: t('spreed', 'Send without notification')
		},

		silentSendInfo() {
			if (this.isOneToOne) {
				return this.silentChat
					? t('spreed', 'The participant will be notified about new messages')
					: t('spreed', 'The participant will not be notified about new messages')
			} else {
				return this.silentChat
					? t('spreed', 'Participants will be notified about new messages')
					: t('spreed', 'Participants will not be notified about new messages')
			}
		},

		showAttachmentsMenu() {
			return (this.canUploadFiles || this.canShareFiles || this.canCreatePoll) && !this.broadcast && !this.upload && !this.messageToEdit
		},

		showAudioRecorder() {
			return !this.hasText && this.canUploadFiles && !this.broadcast && !this.upload && !this.messageToEdit
		},

		showTypingStatus() {
			return this.hasTypingIndicator && this.supportTypingStatus
				&& this.settingsStore.typingStatusPrivacy === PRIVACY.PUBLIC
		},

		filePickerButtons() {
			return [{
				label: t('spreed', 'Choose'),
				callback: (nodes) => this.handleFileShare(nodes),
				type: 'primary'
			}]
		},

		userAbsence() {
			return this.chatExtrasStore.absence[this.token]
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
		},

		text(newValue) {
			if (this.currentUploadId && !this.upload) {
				return
			}
			this.debouncedUpdateChatInput(newValue)
		},

		messageToEdit(newValue) {
			if (newValue) {
				this.text = this.chatExtrasStore.getChatEditInput(this.token)
				this.chatExtrasStore.removeParentIdToReply(this.token)
			} else {
				this.text = this.chatInput
			}
		},

		parentMessage(newValue) {
			if (newValue && this.messageToEdit) {
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
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
			}
		},
	},

	mounted() {
		EventBus.$on('focus-chat-input', this.focusInput)
		EventBus.$on('upload-start', this.handleUploadSideEffects)
		EventBus.$on('upload-discard', this.handleUploadSideEffects)
		EventBus.$on('retry-message', this.handleRetryMessage)

		if (!this.$store.getters.areFileTemplatesInitialised) {
			this.$store.dispatch('getFileTemplates')
		}
	},

	beforeDestroy() {
		EventBus.$off('focus-chat-input', this.focusInput)
		EventBus.$off('upload-start', this.handleUploadSideEffects)
		EventBus.$off('upload-discard', this.handleUploadSideEffects)
		EventBus.$off('retry-message', this.handleRetryMessage)
	},

	methods: {
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
					parameters: this.messageToEdit.messageParameters
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

			const options = { silent: this.silentChat }

			if (this.hasText) {
				this.text = parseSpecialSymbols(this.text)
			}

			// Clear input content from store
			this.chatExtrasStore.removeChatInput(this.token)

			if (this.upload) {
				// remove Quote component
				this.chatExtrasStore.removeParentIdToReply(this.token)

				if (this.$store.getters.getInitialisedUploads(this.currentUploadId).length) {
					// If dialog contains files to upload, delegate sending
					this.$emit('upload', { caption: this.text, options })
					return
				} else {
					// Dismiss dialog, process as normal message sending otherwise
					this.$emit('sent')
				}
			}

			if (this.hasText) {
				const temporaryMessage = await this.$store.dispatch('createTemporaryMessage', {
					text: this.text.trim(),
					token: this.token,
				})
				this.text = ''
				this.userData = {}
				// Scrolls the message list to the last added message
				EventBus.$emit('smooth-scroll-chat-to-bottom')
				// Also remove the message to be replied for this conversation
				this.chatExtrasStore.removeParentIdToReply(this.token)

				this.broadcast
					? await this.broadcastMessage(this.token, temporaryMessage.message)
					: await this.postMessage(this.token, temporaryMessage, options)
				this.resetTypingIndicator()
			}
		},

		// Post message to conversation
		async postMessage(token, temporaryMessage, options) {
			try {
				await this.$store.dispatch('postNewMessage', { token, temporaryMessage, options })
				this.$emit('sent')
			} catch {
				this.$emit('failure')
			}
		},

		// Broadcast message to all breakout rooms
		async broadcastMessage(token, message) {
			try {
				await this.$store.dispatch('broadcastMessageToBreakoutRoomsAction', { token, message })
				this.$emit('sent')
			} catch {
				this.$emit('failure')
			}
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
					updatedMessage: this.text.trim(),
				})
				this.chatExtrasStore.removeMessageIdToEdit(this.token)
				this.resetTypingIndicator()
			} catch {
				this.$emit('failure')
				showError(t('spreed', 'The message could not be edited'))
			}
		},

		sleep(ms) {
			return new Promise(resolve => setTimeout(resolve, ms))
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

		handleFileShare(nodes) {
			nodes.forEach(({ path }) => {
				console.debug(`path ${path} selected for sharing`)
				if (!path.startsWith('/')) {
					throw new Error(t('files', 'Invalid path selected'))
				}
				this.focusInput()
				return shareFile(path, this.token)
			})
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
		async handleFiles(files, rename = false, isVoiceMessage = false) {
			// Create a unique id for the upload operation
			const uploadId = this.currentUploadId ?? new Date().getTime()
			// Uploads and shares the files
			await this.$store.dispatch('initialiseUpload', { files, token: this.token, uploadId, rename, isVoiceMessage })
		},

		/**
		 * Add selected emoji to text input area
		 *
		 * The emoji will be added at the current caret position, and any text
		 * currently selected will be replaced by the emoji. If the input area
		 * does not have the focus there will be no caret or selection; in that
		 * case the emoji will be added at the end.
		 *
		 * @param {string} emoji Emoji object
		 */
		addEmoji(emoji) {
			// FIXME: remove after issue is resolved: https://github.com/nextcloud/nextcloud-vue/issues/3264
			const temp = document.createElement('textarea')

			const selection = document.getSelection()

			const contentEditable = this.$refs.richContenteditable.$refs.contenteditable

			// There is no select, or current selection does not start in the
			// content editable element, so just append the emoji at the end.
			if (!contentEditable.isSameNode(selection.anchorNode) && !contentEditable.contains(selection.anchorNode)) {
				// Browsers add a "<br>" element as soon as some rich text is
				// written in a content editable div (for example, if a new line
				// is added the div content will be "<br><br>"), so the emoji
				// has to be added before the last "<br>" (if any).
				if (this.text.endsWith('<br>')) {
					temp.innerHTML = this.text.slice(0, this.text.lastIndexOf('<br>')) + emoji + '<br>'
				} else {
					temp.innerHTML = this.text + emoji
				}
				this.text = temp.value
				return
			}

			// Although due to legacy reasons the API allows several ranges the
			// specification requires the selection to always have a single range.
			// https://developer.mozilla.org/en-US/docs/Web/API/Selection#Multiple_ranges_in_a_selection
			const range = selection.getRangeAt(0)

			// Deleting the contents also collapses the range to the start.
			range.deleteContents()

			const emojiTextNode = document.createTextNode(emoji)
			range.insertNode(emojiTextNode)

			this.text = contentEditable.innerHTML

			range.setStartAfter(emojiTextNode)
		},

		handleAudioFile(payload) {
			this.handleFiles([payload], false, true)
		},

		handleRecording(payload) {
			this.isRecordingAudio = payload
		},

		togglePollEditor() {
			this.showPollEditor = !this.showPollEditor
		},

		async autoComplete(search, callback) {
			const response = await searchPossibleMentions(this.token, search)
			if (!response) {
				// It was not possible to get the candidate mentions, so just keep the previous ones.
				return
			}

			const possibleMentions = response.data.ocs.data

			possibleMentions.forEach(possibleMention => {
				// Set icon for candidate mentions that are not for users.
				if (possibleMention.source === 'calls') {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.iconUrl = getConversationAvatarOcsUrl(this.token, isDarkTheme)
					possibleMention.subline = t('spreed', 'Everyone')
				} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.GROUPS) {
					possibleMention.icon = 'icon-group-forced-white'
					possibleMention.subline = t('spreed', 'Group')
				} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.GUESTS) {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.subline = t('spreed', 'Guest')
				} else if (possibleMention.source === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.iconUrl = getUserProxyAvatarOcsUrl(this.token, possibleMention.id, isDarkTheme, 64)
				} else {
					// The avatar is automatically shown for users, but an icon
					// is nevertheless required as fallback.
					possibleMention.icon = 'icon-user-forced-white'
					if (possibleMention.source === ATTENDEE.ACTOR_TYPE.USERS && possibleMention.id !== possibleMention.mentionId) {
						// Prevent local users avatars in federated room to be overwritten
						possibleMention.iconUrl = generateUrl('avatar/{userId}/64' + (isDarkTheme ? '/dark' : '') + '?v=0', { userId: possibleMention.id })
					}
					// Convert status properties to an object.
					if (possibleMention.status) {
						possibleMention.status = {
							status: possibleMention.status,
							icon: possibleMention.statusIcon,
						}
						possibleMention.subline = possibleMention.statusMessage
					}
				}

				// Caching the user id data for each possible mention
				// mentionId should be the default match since 'federation-v1'
				possibleMention.id = possibleMention.mentionId ?? possibleMention.id
				this.userData[possibleMention.id] = possibleMention
			})

			callback(possibleMentions)
		},

		focusInput() {
			if (this.isMobileDevice) {
				return
			}
			this.$nextTick().then(() => {
				this.$refs.richContenteditable.focus()
			})
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

		handleEditLastMessage() {
			if (!canEditMessage || this.upload || this.broadcast || this.isRecordingAudio) {
				return
			}
			const lastMessageByCurrentUser = this.$store.getters.messagesList(this.token).findLast(message => {
				return message.actorId === this.$store.getters.getUserId()
					&& message.actorType === this.$store.getters.getActorType()
					&& !message.isTemporary && !message.systemMessage
			})

			if (!lastMessageByCurrentUser) {
				return
			}

			this.chatExtrasStore.initiateEditingMessage({
				token: this.token,
				id: lastMessageByCurrentUser.id,
				message: lastMessageByCurrentUser.message,
				messageParameters: lastMessageByCurrentUser.messageParameters,
			})
		},

		async checkAbsenceStatus() {
			if (!this.isOneToOneConversation) {
				return
			}

			// TODO replace with status message id 'vacationing'
			if (this.conversation.status === 'dnd') {
				// Fetch actual absence status from server
				await this.chatExtrasStore.getUserAbsence({
					token: this.token,
					userId: this.conversation.name,
				})
			} else {
				// Remove stored absence status
				this.chatExtrasStore.removeUserAbsence(this.token)
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
	},
}
</script>

<style lang="scss">
// FIXME upstream: Enforce NcAutoCompleteResult to have proper box-sizing
.tribute-container {
	position: absolute;
	box-sizing: content-box !important;

	& *,
	& *::before,
	& *::after {
		box-sizing: inherit !important;
	}
}
</style>

<style lang="scss" scoped>
.wrapper {
	padding: 12px 12px 12px 0;
	min-height: 69px;
}

.new-message-form {
	align-items: flex-end;
	display: flex;
	gap: 4px;
	position: relative;
	max-width: 700px;
	margin: 0 auto;

	&__emoji-picker {
		position: absolute;
		bottom: 0;
		z-index: 1;
	}

	&__input {
		flex-grow: 1;
		position: relative;
	}

	// Override NcRichContenteditable styles
	:deep(.rich-contenteditable__input) {
		border-radius: calc(var(--default-clickable-area) / 2);
		padding: 8px 16px 8px 44px;
		max-height: 180px;
	}

	&__quote {
		margin: 0 16px 12px;
		background-color: var(--color-background-hover);
		padding: 8px;
		border-radius: var(--border-radius-large);
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

// Hardcode to prevent RTL affecting on user mentions
:deep(.mention-bubble) {
	direction: ltr;
}
</style>
