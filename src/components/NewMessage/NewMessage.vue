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
				@handle-file-share="handleFileShare"
				@toggle-poll-editor="togglePollEditor"
				@update-new-file-dialog="updateNewFileDialog" />

			<!-- Input area -->
			<div class="new-message-form__input">
				<NewMessageAbsenceInfo v-if="userAbsence"
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
				<div v-if="messageToBeReplied" class="new-message-form__quote">
					<Quote is-new-message-quote v-bind="messageToBeReplied" />
				</div>
				<NcRichContenteditable ref="richContenteditable"
					v-shortkey.once="$options.disableKeyboardShortcuts ? null : ['c']"
					class="new-message-form__richContenteditable"
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
					@tribute-active-true.native="isTributePickerActive = true"
					@tribute-active-false.native="isTributePickerActive = false"
					@input="handleTyping"
					@paste="handlePastedFiles"
					@submit="handleSubmit({ silent: false })" />
			</div>

			<!-- Audio recorder -->
			<NewMessageAudioRecorder v-if="showAudioRecorder"
				:disabled="disabled"
				@recording="handleRecording"
				@audio-file="handleAudioFile" />

			<!-- Send buttons -->
			<template v-else>
				<NcActions v-if="!broadcast"
					:container="container"
					force-menu>
					<!-- Silent send -->
					<NcActionButton close-after-click
						icon="icon-upload"
						:name="t('spreed', 'Send without notification')"
						@click="handleSubmit({ silent: true })">
						{{ silentSendInfo }}
						<template #icon>
							<BellOff :size="16" />
						</template>
					</NcActionButton>
				</NcActions>
				<!-- Send -->
				<NcButton :disabled="disabled"
					type="tertiary"
					native-type="submit"
					:title="t('spreed', 'Send message')"
					:aria-label="t('spreed', 'Send message')"
					@click="handleSubmit({ silent: false })">
					<template #icon>
						<Send :size="16" />
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
	</div>
</template>

<script>
import BellOff from 'vue-material-design-icons/BellOff.vue'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import Send from 'vue-material-design-icons/Send.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcRichContenteditable from '@nextcloud/vue/dist/Components/NcRichContenteditable.js'

import NewMessageAbsenceInfo from './NewMessageAbsenceInfo.vue'
import NewMessageAttachments from './NewMessageAttachments.vue'
import NewMessageAudioRecorder from './NewMessageAudioRecorder.vue'
import NewMessageNewFileDialog from './NewMessageNewFileDialog.vue'
import NewMessagePollEditor from './NewMessagePollEditor.vue'
import NewMessageTypingIndicator from './NewMessageTypingIndicator.vue'
import Quote from '../Quote.vue'

import { CONVERSATION, PARTICIPANT, PRIVACY } from '../../constants.js'
import { EventBus } from '../../services/EventBus.js'
import { shareFile } from '../../services/filesSharingServices.js'
import { searchPossibleMentions } from '../../services/mentionsService.js'
import { useChatExtrasStore } from '../../stores/chatExtras.js'
import { useSettingsStore } from '../../stores/settings.js'
import { fetchClipboardContent } from '../../utils/clipboard.js'
import { isDarkTheme } from '../../utils/isDarkTheme.js'

const picker = getFilePickerBuilder(t('spreed', 'File to share'))
	.setMultiSelect(false)
	.setType(1)
	.allowDirectories()
	.build()

const disableKeyboardShortcuts = OCP.Accessibility.disableKeyboardShortcuts()
const supportTypingStatus = getCapabilities()?.spreed?.config?.chat?.['typing-privacy'] !== undefined

export default {
	name: 'NewMessage',

	disableKeyboardShortcuts,

	components: {
		NcActionButton,
		NcActions,
		NcButton,
		NcEmojiPicker,
		NcRichContenteditable,
		NewMessageAbsenceInfo,
		NewMessageAttachments,
		NewMessageAudioRecorder,
		NewMessageNewFileDialog,
		NewMessagePollEditor,
		NewMessageTypingIndicator,
		Quote,
		// Icons
		BellOff,
		EmoticonOutline,
		Send,
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
			conversationIsFirstInList: false,
			// True when the audio recorder component is recording
			isRecordingAudio: false,
			showPollEditor: false,
			showNewFileDialog: -1,
			isTributePickerActive: false,
			// Check empty template by default
			userData: {},
			clipboardTimeStamp: null,
			typingInterval: null,
			wasTypingWithinInterval: false,
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

		messageToBeReplied() {
			const parentId = this.$store.getters.getMessageToBeReplied(this.token)
			return parentId && this.$store.getters.message(this.token, parentId)
		},

		currentUserIsGuest() {
			return this.$store.getters.getUserId() === null
		},

		canShareFiles() {
			return !this.currentUserIsGuest
		},

		canUploadFiles() {
			return getCapabilities()?.spreed?.config?.attachments?.allowed
				&& this.$store.getters.getAttachmentFolderFreeSpace() !== 0
				&& this.canShareFiles
		},

		canCreatePoll() {
			return !this.isOneToOne && !this.noChatPermission
		},

		currentConversationIsJoined() {
			return this.$store.getters.currentConversationIsJoined
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

		silentSendInfo() {
			if (this.isOneToOne) {
				return t('spreed', 'The participant will not be notified about this message')
			} else {
				return t('spreed', 'The participants will not be notified about this message')
			}
		},

		showAttachmentsMenu() {
			return this.canShareFiles && !this.broadcast && !this.upload
		},

		showAudioRecorder() {
			return !this.hasText && this.canUploadFiles && !this.broadcast && !this.upload
		},

		showTypingStatus() {
			return this.hasTypingIndicator && this.supportTypingStatus
				&& this.settingsStore.typingStatusPrivacy === PRIVACY.PUBLIC
		},

		userAbsence() {
			return this.chatExtrasStore.absence[this.token]
		},
	},

	watch: {
		currentConversationIsJoined() {
			this.focusInput()
		},

		text(newValue) {
			this.$store.dispatch('setCurrentMessageInput', { token: this.token, text: newValue })
		},

		token: {
			immediate: true,
			handler(token) {
				if (token) {
					this.text = this.$store.getters.currentMessageInput(token)
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
		this.text = this.$store.getters.currentMessageInput(this.token)

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

		handleUploadSideEffects() {
			if (this.upload) {
				return
			}
			this.$nextTick(() => {
				// reset or fill main input in chat view from the store
				this.text = this.$store.getters.currentMessageInput(this.token)
				// refocus input as the user might want to type further
				this.focusInput()
			})
		},

		/**
		 * Sends the new message
		 *
		 * @param {object} options the submit options
		 */
		async handleSubmit(options) {
			if (OC.debug && this.text.startsWith('/spam ')) {
				const pattern = /^\/spam (\d+) messages$/i
				const match = pattern.exec(this.text)
				// Escape HTML
				if (match) {
					await this.handleSubmitSpam(match[1])
					return
				}
			}

			// FIXME upstream: https://github.com/nextcloud-libraries/nextcloud-vue/issues/4492
			if (this.hasText) {
				const temp = document.createElement('textarea')
				temp.innerHTML = this.text.replace(/&/gmi, '&amp;')
				this.text = temp.value.replace(/&amp;/gmi, '&').replace(/&lt;/gmi, '<')
					.replace(/&gt;/gmi, '>').replace(/&sect;/gmi, '§')
			}

			if (this.upload) {
				// Clear input content from store
				this.$store.dispatch('setCurrentMessageInput', { token: this.token, text: '' })

				if (this.$store.getters.getInitialisedUploads(this.$store.getters.currentUploadId).length) {
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
				this.resetTypingIndicator()
				this.userData = {}
				// Scrolls the message list to the last added message
				EventBus.$emit('smooth-scroll-chat-to-bottom')
				// Also remove the message to be replied for this conversation
				await this.$store.dispatch('removeMessageToBeReplied', this.token)

				this.broadcast
					? await this.broadcastMessage(temporaryMessage, options)
					: await this.postMessage(temporaryMessage, options)
			}
		},

		// Post message to conversation
		async postMessage(temporaryMessage, options) {
			try {
				await this.$store.dispatch('postNewMessage', { temporaryMessage, options })
				this.$emit('sent')
			} catch {
				this.$emit('failure')
			}
		},

		// Broadcast message to all breakout rooms
		async broadcastMessage(temporaryMessage, options) {
			try {
				await this.$store.dispatch('broadcastMessageToBreakoutRoomsAction', { temporaryMessage, options })
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
				await this.handleSubmit({ silent: false })
			}
		},

		sleep(ms) {
			return new Promise(resolve => setTimeout(resolve, ms))
		},

		handleRetryMessage(temporaryMessageId) {
			if (this.text === '') {
				const temporaryMessage = this.$store.getters.message(this.token, temporaryMessageId)
				if (temporaryMessage) {
					this.text = temporaryMessage.message || this.text

					// Restore the parent/quote message
					if (temporaryMessage.parent) {
						this.$store.dispatch('addMessageToBeReplied', {
							token: this.token,
							id: temporaryMessage.parent.id,
						})
					}

					this.$store.dispatch('removeTemporaryMessageFromStore', temporaryMessage)
				}
			}
		},

		handleFileShare() {
			picker.pick()
				.then((path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}
					this.focusInput()
					return shareFile(path, this.token)
				})

			// FIXME Remove this hack once it is possible to set the parent
			// element of the file picker.
			// By default the file picker is a sibling of the fullscreen
			// element, so it is not visible when in fullscreen mode. It is not
			// possible to specify the parent nor to know when the file picker
			// was actually opened, so for the time being it is reparented if
			// needed shortly after calling it.
			setTimeout(() => {
				if (this.$store.getters.isFullscreen()) {
					document.getElementById('content-vue').appendChild(document.querySelector('.oc-dialog'))
				}
			}, 1000)
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
			const uploadId = this.$store.getters.currentUploadId ?? new Date().getTime()
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
				// TODO fix backend for userMention
				if (!possibleMention.title && possibleMention.label) {
					possibleMention.title = possibleMention.label
				}

				// Set icon for candidate mentions that are not for users.
				if (possibleMention.source === 'calls') {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.iconUrl = generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar' + (isDarkTheme ? '/dark' : ''), {
						token: this.token,
					})
					possibleMention.subline = t('spreed', 'Everyone')
				} else if (possibleMention.source === 'groups') {
					possibleMention.icon = 'icon-group-forced-white'
					possibleMention.subline = t('spreed', 'Group')
				} else if (possibleMention.source === 'guests') {
					possibleMention.icon = 'icon-user-forced-white'
					possibleMention.subline = t('spreed', 'Guest')
				} else {
					// The avatar is automatically shown for users, but an icon
					// is nevertheless required as fallback.
					possibleMention.icon = 'icon-user-forced-white'

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
				this.userData[possibleMention.id] = possibleMention
			})

			callback(possibleMentions)
		},

		focusInput() {
			if (this.isMobile()) {
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
			// When the tribute picker (e.g. emoji picker or mentions) is open
			// ESC should only close the picker but not blur
			if (!this.isTributePickerActive) {
				this.blurInput()
			}
		},

		isMobile() {
			return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
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
		}
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
	position: relative;
	max-width: 700px;
	margin: 0 auto;

	&__emoji-picker {
		position: absolute;
		bottom: 1px;
		z-index: 1;
	}

	&__input {
		flex-grow: 1;
		overflow: hidden;
		position: relative;
	}

	// Override NcRichContenteditable styles
	& &__richContenteditable {
		border: 2px solid var(--color-border-dark);
		border-radius: calc(var(--default-clickable-area) / 2);
		padding: 8px 16px 8px 44px;
		max-height: 180px;

		&:hover,
		&:focus,
		&:active {
			border: 2px solid var(--color-main-text);
		}
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
