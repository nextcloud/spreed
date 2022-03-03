<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @license GNU AGPL version 3 or any later version
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
	<div class="wrapper"
		:class="{'wrapper--chatScrolledToBottom': isChatScrolledToBottom}">
		<!--native file picker, hidden -->
		<input id="file-upload"
			ref="fileUploadInput"
			multiple
			type="file"
			tabindex="-1"
			aria-hidden="true"
			class="hidden-visually"
			@change="handleFileInput">
		<div class="new-message">
			<form class="new-message-form"
				@submit.prevent>
				<div v-if="canUploadFiles || canShareFiles"
					class="new-message-form__upload-menu">
					<Actions ref="uploadMenu"
						:container="container"
						:boundaries-element="containerElement"
						:disabled="disabled"
						:aria-label="t('spreed', 'Share files to the conversation')"
						:aria-haspopup="true">
						<Paperclip slot="icon"
							:size="16"
							decorative
							title="" />
						<ActionButton v-if="canUploadFiles"
							:close-after-click="true"
							icon="icon-upload"
							@click.prevent="clickImportInput">
							{{ t('spreed', 'Upload new files') }}
						</ActionButton>
						<ActionButton v-if="canShareFiles"
							:close-after-click="true"
							icon="icon-folder"
							@click.prevent="handleFileShare">
							{{ t('spreed', 'Share from Files') }}
						</ActionButton>
					</Actions>
				</div>
				<div class="new-message-form__input">
					<div class="new-message-form__emoji-picker">
						<EmojiPicker v-if="!disabled"
							:container="container"
							:close-on-select="false"
							@select="addEmoji">
							<Button :disabled="disabled"
								:aria-label="t('spreed', 'Add emoji')"
								type="tertiary"
								:aria-haspopup="true">
								<EmoticonOutline :size="16"
									decorative
									title="" />
							</Button>
						</EmojiPicker>
						<!-- Disabled emoji picker placeholder button -->
						<Button v-else
							type="tertiary"
							:disabled="true">
							<EmoticonOutline :size="16"
								decorative
								title="" />
						</Button>
					</div>
					<div v-if="messageToBeReplied" class="new-message-form__quote">
						<Quote :is-new-message-form-quote="true"
							:parent-id="messageToBeReplied.id"
							v-bind="messageToBeReplied" />
					</div>

					<AdvancedInput ref="advancedInput"
						v-model="text"
						:token="token"
						:active-input="!disabled"
						:placeholder-text="placeholderText"
						:aria-label="placeholderText"
						@update:contentEditable="contentEditableToParsed"
						@submit="handleSubmit"
						@files-pasted="handlePastedFiles" />
				</div>

				<AudioRecorder v-if="!hasText && canUploadFiles"
					:disabled="disabled"
					@recording="handleRecording"
					@audio-file="handleAudioFile" />

				<Button v-else
					:disabled="disabled"
					type="tertiary"
					native-type="submit"
					:aria-label="t('spreed', 'Send message')"
					@click.prevent="handleSubmit">
					<Send title=""
						:size="16"
						decorative />
				</Button>
			</form>
		</div>
	</div>
</template>

<script>
import AdvancedInput from './AdvancedInput/AdvancedInput'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { getCapabilities } from '@nextcloud/capabilities'
import Quote from '../Quote'
import Button from '@nextcloud/vue/dist/Components/Button'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import { EventBus } from '../../services/EventBus'
import { shareFile } from '../../services/filesSharingServices'
import { CONVERSATION } from '../../constants'
import Paperclip from 'vue-material-design-icons/Paperclip'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline'
import Send from 'vue-material-design-icons/Send'
import AudioRecorder from './AudioRecorder/AudioRecorder'

const picker = getFilePickerBuilder(t('spreed', 'File to share'))
	.setMultiSelect(false)
	.setModal(true)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'NewMessageForm',
	components: {
		AdvancedInput,
		Quote,
		Actions,
		ActionButton,
		Button,
		Paperclip,
		EmojiPicker,
		EmoticonOutline,
		Send,
		AudioRecorder,
	},

	props: {
		isChatScrolledToBottom: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			text: '',
			parsedText: '',
			conversationIsFirstInList: false,
			// True when the audiorecorder component is recording
			isRecordingAudio: false,
		}
	},

	computed: {
		/**
		 * The current conversation token
		 *
		 * @return {string}
		 */
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || {
				readOnly: CONVERSATION.STATE.READ_WRITE,
			}
		},

		isReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},

		disabled() {
			return this.isReadOnly || !this.currentConversationIsJoined || this.isRecordingAudio
		},

		placeholderText() {
			if (this.isReadOnly) {
				return t('spreed', 'This conversation has been locked')
			} else if (!this.currentConversationIsJoined) {
				return t('spreed', 'Joining conversation …')
			} else {
				return t('spreed', 'Write message, @ to mention someone …')
			}
		},

		messageToBeReplied() {
			return this.$store.getters.getMessageToBeReplied(this.token)
		},

		currentUserIsGuest() {
			return this.$store.getters.getUserId() === null
		},

		canShareFiles() {
			return !this.currentUserIsGuest
		},

		canUploadFiles() {
			const allowed = getCapabilities()?.spreed?.config?.attachments?.allowed
			return allowed
				&& this.attachmentFolderFreeSpace !== 0
				&& this.canShareFiles
		},

		attachmentFolderFreeSpace() {
			return this.$store.getters.getAttachmentFolderFreeSpace()
		},

		currentConversationIsJoined() {
			return this.$store.getters.currentConversationIsJoined
		},

		hasText() {
			return this.parsedText !== ''
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		containerElement() {
			return document.querySelector(this.container)
		},
	},

	watch: {
		currentConversationIsJoined(newValue) {
			this.$refs.advancedInput.focusInput()
		},

		disabled(newValue) {
			// the menu is not always available
			if (!this.$refs.uploadMenu) {
				return
			}
			this.$refs.uploadMenu.$refs.menuButton.disabled = newValue
		},

		text(newValue) {
			this.$store.dispatch('setCurrentMessageInput', { token: this.token, text: newValue })
		},

		token(token) {
			if (token) {
				this.text = this.$store.getters.currentMessageInput(token) || ''
			} else {
				this.text = ''
			}
		},
	},

	mounted() {
		EventBus.$on('upload-start', this.handleUploadStart)
		EventBus.$on('retry-message', this.handleRetryMessage)
		this.text = this.$store.getters.currentMessageInput(this.token) || ''
		// this.startRecording()
	},

	beforeDestroy() {
		EventBus.$off('upload-start', this.handleUploadStart)
		EventBus.$off('retry-message', this.handleRetryMessage)
	},

	methods: {
		handleUploadStart() {
			// refocus on upload start as the user might want to type again
			// while the upload is running
			this.$refs.advancedInput.focusInput()
		},

		contentEditableToParsed(contentEditable) {
			const mentions = contentEditable.querySelectorAll('span[data-at-embedded]')
			mentions.forEach(mention => {
				// FIXME Adding a space after the mention should be improved to
				// do it or not based on the next element instead of always
				// adding it.
				mention.replaceWith(' @' + mention.firstElementChild.attributes['data-mention-id'].value + ' ')
			})

			this.parsedText = this.rawToParsed(contentEditable.innerHTML)
		},
		/**
		 * Returns a parsed version of the given raw text of the content
		 * editable div.
		 *
		 * The given raw text contains a plain text representation of HTML
		 * content (like "first&nbsp;line<br>second&nbsp;line"). The returned
		 * parsed text replaces the (known) HTML content with the format
		 * expected by the server (like "first line\nsecond line").
		 *
		 * The parsed text is also trimmed.
		 *
		 * @param {string} text the raw text
		 * @return {string} the parsed text
		 */
		rawToParsed(text) {
			text = text.replace(/<br>/g, '\n')
			text = text.replace(/<div>/g, '\n')
			text = text.replace(/<\/div>/g, '')
			text = text.replace(/&nbsp;/g, ' ')

			// Since we used innerHTML to get the content of the div.contenteditable
			// it is escaped. With this little trick from https://stackoverflow.com/a/7394787
			// We unescape the code again, so if you write `<strong>` we can display
			// it again instead of `&lt;strong&gt;`
			const temp = document.createElement('textarea')
			temp.innerHTML = text
			text = temp.value

			// Although the text is fully trimmed, at the very least the last
			// "\n" occurrence should be always removed, as browsers add a
			// "<br>" element as soon as some rich text is written in a content
			// editable div (for example, if a new line is added the div content
			// will be "<br><br>").
			return text.trim()
		},

		/**
		 * Sends the new message
		 */
		async handleSubmit() {
			if (OC.debug && this.parsedText.startsWith('/spam ')) {
				const pattern = /^\/spam (\d+) messages$/i
				const match = pattern.exec(this.parsedText)
				// Escape HTML
				if (match) {
					await this.handleSubmitSpam(match[1])
					return
				}
			}

			if (this.parsedText !== '') {
				const temporaryMessage = await this.$store.dispatch('createTemporaryMessage', { text: this.parsedText, token: this.token })
				// FIXME: move "addTemporaryMessage" into "postNewMessage" as it's a pre-requisite anyway ?
				this.$store.dispatch('addTemporaryMessage', temporaryMessage)
				this.text = ''
				this.parsedText = ''
				// Scrolls the message list to the last added message
				EventBus.$emit('smooth-scroll-chat-to-bottom')
				// Also remove the message to be replied for this conversation
				this.$store.dispatch('removeMessageToBeReplied', this.token)
				await this.$store.dispatch('postNewMessage', temporaryMessage)
			}
		},

		async handleSubmitSpam(numberOfMessages) {
			console.debug('Sending ' + numberOfMessages + ' lorem ipsum messages')
			for (let i = 0; i < numberOfMessages; i++) {
				const randomNumber = parseInt(Math.random() * 500, 10)
				console.debug('[' + i + '/' + numberOfMessages + '] Sleeping ' + randomNumber + 'ms')
				await this.sleep(randomNumber)

				const loremIpsum = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.\n\nDuis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi. Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat.'
				this.parsedText = loremIpsum.substr(0, 25 + randomNumber)
				await this.handleSubmit()
			}
		},

		sleep(ms) {
			return new Promise(resolve => setTimeout(resolve, ms))
		},

		handleRetryMessage(temporaryMessageId) {
			if (this.parsedText === '') {
				const temporaryMessage = this.$store.getters.message(this.token, temporaryMessageId)
				if (temporaryMessage) {
					this.text = temporaryMessage.message || this.text
					this.parsedText = temporaryMessage.message || this.parsedText
					this.$store.dispatch('removeTemporaryMessageFromStore', temporaryMessage)
				}
			}
		},

		async handleFileShare() {
			picker.pick()
				.then(async (path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}
					shareFile(path, this.token)
					this.$refs.advancedInput.focusInput()
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
		 * Clicks the hidden file input when clicking the correspondent ActionButton,
		 * thus opening the file-picker
		 */
		clickImportInput() {
			this.$refs.fileUploadInput.click()
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
		 * @param {File[] | FileList} files pasted files list
		 */
		async handlePastedFiles(files) {
			this.handleFiles(files, true)
		},

		/**
		 * Handles file upload
		 *
		 * @param {File[] | FileList} files pasted files list
		 * @param {boolean} rename whether to rename the files
		 * @param {boolean} isVoiceMessage indicates whether the file is a vooicemessage
		 */
		async handleFiles(files, rename = false, isVoiceMessage) {
			// Create a unique id for the upload operation
			const uploadId = new Date().getTime()
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
			const selection = document.getSelection()

			const contentEditable = this.$refs.advancedInput.$refs.contentEditable

			// There is no select, or current selection does not start in the
			// content editable element, so just append the emoji at the end.
			if (!contentEditable.isSameNode(selection.anchorNode) && !contentEditable.contains(selection.anchorNode)) {
				// Browsers add a "<br>" element as soon as some rich text is
				// written in a content editable div (for example, if a new line
				// is added the div content will be "<br><br>"), so the emoji
				// has to be added before the last "<br>" (if any).
				if (this.text.endsWith('<br>')) {
					this.text = this.text.substr(0, this.text.lastIndexOf('<br>')) + emoji + '<br>'
				} else {
					this.text += emoji
				}

				return
			}

			// Although due to legacy reasons the API allows several ranges the
			// specification requires the selection to always have a single
			// range.
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
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/buttons';

.wrapper {
	display: flex;
	justify-content: center;
	padding: 12px 0;
	border-top: 1px solid var(--color-border);
	min-height: 69px;
	&--chatScrolledToBottom {
		border-top: none;
	}
}

.new-message {
	width: 100%;
	display: flex;
	justify-content: center;
	&-form {
		align-items: flex-end;
		display: flex;
		position:relative;
		flex: 0 1 700px;
		margin: 0 4px;
		&__emoji-picker {
			position: absolute;
			left: 5px;
			bottom: 1px;
			z-index: 1;
		}

		&__input {
			flex-grow: 1;
			overflow: hidden;
			position: relative;
		}
		&__quote {
			margin: 0 16px 12px 24px;
			background-color: var(--color-background-hover);
			padding: 8px;
			border-radius: var(--border-radius-large);
		}

		// put a grey round background when popover is opened
		// or hover-focused
		&__icon:hover,
		&__icon:focus,
		&__icon:active {
			opacity: $opacity_full;
			// good looking on dark AND white bg
			background-color: $icon-focus-bg;
		}

	}
}

// Override actions styles TODO: upstream this change
// Targeting two classess for specificity
::v-deep .action-item__menutoggle.action-item__menutoggle--with-icon-slot {
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
