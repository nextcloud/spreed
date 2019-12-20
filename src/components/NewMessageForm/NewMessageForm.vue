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
		<div
			class="new-message">
			<form
				class="new-message-form">
				<div
					class="new-message-form__button">
					<button
						v-if="!currentUserIsGuest"
						class="new-message-form__button icon-clip-add-file"
						@click.prevent="handleFileShare" />
				</div>
				<div class="new-message-form__input">
					<Quote
						v-if="messageToBeReplied"
						:is-new-message-form-quote="true"
						v-bind="messageToBeReplied" />
					<AdvancedInput
						v-model="text"
						@submit="handleSubmit" />
				</div>
				<button
					type="submit"
					class="new-message-form__button icon-confirm-fade"
					@click.prevent="handleSubmit" />
			</form>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import AdvancedInput from './AdvancedInput/AdvancedInput'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import { postNewMessage } from '../../services/messagesService'
import Quote from '../Quote'

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
	},
	data: function() {
		return {
			text: '',
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
				message: this.text,
				messageParameters: {},
				token: this.token,
			})
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

		async handleFileShare() {
			picker.pick()
				.then(path => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}

					axios.post(
						generateOcsUrl('apps/files_sharing/api/v1', 2) + 'shares',
						{
							shareType: 10, // OC.Share.SHARE_TYPE_ROOM,
							path: path,
							shareWith: this.token,
						}
					)
				}).catch(error => {
					console.error(`Error while sharing file: ${error.message || 'Unknown error'}`, { error })

					OCP.Toast.error(error.message || t('files', 'Error while sharing file'))
				})
		},

		/**
		 * Sends the new message
		 */
		async handleSubmit() {
			if (this.text !== '') {
				const temporaryMessage = this.createTemporaryMessage()
				this.$store.dispatch('addTemporaryMessage', temporaryMessage)
				this.text = ''
				// Scrolls the message list to the last added message
				this.$nextTick(function() {
					document.querySelector('.scroller').scrollTop = document.querySelector('.scroller').scrollHeight
				})
				try {
					// Posts the message to the server
					const response = await postNewMessage(temporaryMessage)
					// If successful, deletes the temporary message from the store
					this.$store.dispatch('deleteMessage', temporaryMessage)
					// Also remove the message to be replied for this conversation
					this.$store.dispatch('removeMessageToBeReplied', this.token)
					// And adds the complete version of the message received
					// by the server
					this.$store.dispatch('processMessage', response.data.ocs.data)
				} catch (error) {
					console.debug(`error while submitting message ${error}`)
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
	max-width: $message-max-width + 88px;
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
			overflow-y: scroll;
			overflow-x: hidden;
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
