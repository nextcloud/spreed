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
	<div
		class="wrapper">
		<!--native file picker, hidden -->
		<input id="file-upload"
			ref="file-upload-input"
			multiple
			type="file"
			class="hidden-visually"
			@input="processFiles">
		<div
			class="new-message">
			<form
				class="new-message-form">
				<div
					class="new-message-form__button">
					<Actions
						default-icon="icon-clip-add-file"
						class="new-message-form__button">
						<ActionButton
							v-if="!currentUserIsGuest"
							:close-after-click="true"
							icon="icon-upload"
							@click.prevent="clickImportInput">
							{{ t('spreed', 'Upload new files') }}
						</ActionButton>
						<ActionButton
							v-if="!currentUserIsGuest"
							:close-after-click="true"
							icon="icon-folder"
							@click.prevent="handleFileShare">
							{{ t('spreed', 'Share from Files') }}
						</ActionButton>
					</Actions>
				</div>
				<div class="new-message-form__input">
					<Quote
						v-if="messageToBeReplied"
						:is-new-message-form-quote="true"
						v-bind="messageToBeReplied" />
					<AdvancedInput
						v-model="text"
						:token="token"
						@update:contentEditable="contentEditableToParsed"
						@submit="handleSubmit" />
				</div>
				<button
					type="submit"
					class="new-message-form__button submit icon-confirm-fade"
					@click.prevent="handleSubmit" />
			</form>
		</div>
	</div>
</template>

<script>
import AdvancedInput from './AdvancedInput/AdvancedInput'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { postNewMessage } from '../../services/messagesService'
import Quote from '../Quote'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import client from '../../services/DavClient'
import { shareFile } from '../../services/filesSharingServices'

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
	},
	data: function() {
		return {
			text: '',
			parsedText: '',
		}
	},
	computed: {
		/**
		 * The current conversation token
		 *
		 * @returns {String}
		 */
		token() {
			return this.$store.getters.getToken()
		},
		messageToBeReplied() {
			return this.$store.getters.getMessageToBeReplied(this.token)
		},
		currentUserIsGuest() {
			return this.$store.getters.getUserId() === null
		},
	},
	methods: {
		contentEditableToParsed(contentEditable) {
			const mentions = contentEditable.querySelectorAll('span[data-at-embedded]')
			mentions.forEach(mention => {
				// FIXME Adding a space after the mention should be improved to
				// do it or not based on the next element instead of always
				// adding it.
				mention.replaceWith('@' + mention.firstElementChild.attributes['data-mention-id'].value + ' ')
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
		 * @param {String} text the raw text
		 * @returns {String} the parsed text
		 */
		rawToParsed(text) {
			text = text.replace(/<br>/g, '\n')
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
		 * Create a temporary message that will be used until the
		 * actual message object is retrieved from the server
		 *
		 * @returns {Object}
		 */
		createTemporaryMessage() {
			const message = Object.assign({}, {
				id: this.createTemporaryMessageId(),
				actorId: this.$store.getters.getActorId(),
				actorType: this.$store.getters.getActorType(),
				actorDisplayName: this.$store.getters.getDisplayName(),
				timestamp: 0,
				systemMessage: '',
				messageType: '',
				message: this.parsedText,
				messageParameters: {},
				token: this.token,
				isReplyable: false,
			})

			if (this.$store.getters.getActorType() === 'guests') {
				// Strip off "guests/" from the sessionHash
				message.actorId = this.$store.getters.getActorId().substring(6)
			}

			/**
			 * If the current message is a quote-reply messag, add the parent key to the
			 * temporary message object.
			 */
			if (this.messageToBeReplied) {
				message.parent = this.messageToBeReplied.id
			}
			return message
		},
		/**
		 * Create a temporary ID that will be used until the actual
		 * message object is received from the server.
		 *
		 * @returns {String}
		 */
		createTemporaryMessageId() {
			const date = new Date()
			return 'temp-' + date.getTime()
		},

		/**
		 * Sends the new message
		 */
		async handleSubmit() {
			if (this.parsedText !== '') {
				const temporaryMessage = this.createTemporaryMessage()
				this.$store.dispatch('addTemporaryMessage', temporaryMessage)
				this.text = ''
				this.parsedText = ''
				// Scrolls the message list to the last added message
				this.$nextTick(function() {
					document.querySelector('.scroller').scrollTop = document.querySelector('.scroller').scrollHeight
				})
				// Also remove the message to be replied for this conversation
				this.$store.dispatch('removeMessageToBeReplied', this.token)
				try {
					// Posts the message to the server
					const response = await postNewMessage(temporaryMessage)
					// If successful, deletes the temporary message from the store
					this.$store.dispatch('deleteMessage', temporaryMessage)
					// And adds the complete version of the message received
					// by the server
					this.$store.dispatch('processMessage', response.data.ocs.data)
				} catch (error) {
					console.debug(`error while submitting message ${error}`)
				}

			}
		},

		async handleFileShare() {
			picker.pick()
				.then(async(path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}
					shareFile(path, this.token)
				})
		},

		/**
		 * Clicks the hidden file input when clicking the correspondent ActionButton,
		 * thus opening the file-picker
		 */
		clickImportInput() {
			this.$refs['file-upload-input'].click()
		},

		/**
		 * Uploads the files to the root files directory
		 * @param {object} event the file input event object
		 */
		async processFiles(event) {
			// The selected files array
			const files = Object.values(event.target.files)
			// process each file in the array
			for (let i = 0; i < files.length; i++) {
				console.log(files[i])
				const userId = this.$store.getters.getUserId()
				const path = `/files/${userId}/` + files[i].name
				try {
					// Upload the file
					await client.putFileContents(path, files[i])
					// Share the file to the talk room
					shareFile('/' + files[i].name, this.token)
				} catch (exception) {
					console.debug('Error while uploading file:' + exception)
					showError(t('spreed', 'Error while uploading file'))
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.wrapper {
	border-top: 1px solid var(--color-border-dark);
	padding: 4px 0;
}

.new-message {
	margin: auto;
	max-width: $messages-list-max-width;
	position: sticky;
	position: -webkit-sticky;
	bottom: 0;
	background-color: var(--color-main-background);
	&-form {
		display: flex;
		align-items: center;
		&__input {
			flex-grow: 1;
			max-height: $message-form-max-height;
			overflow-y: auto;
			overflow-x: hidden;
			max-width: $message-max-width;
		}
		&__button {
			width: 44px;
			height: 44px;
			margin-top: auto;
			background-color: transparent;
			border: none;
		}
	}
}
</style>
