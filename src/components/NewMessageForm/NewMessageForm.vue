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
				<button
					class="new-message-form__button icon-clip-add-file" />
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
import AdvancedInput from './AdvancedInput/AdvancedInput'
import { postNewMessage } from '../../services/messagesService'
import Quote from '../Quote'
import CancelableRequest from '../../utils/cancelableRequest'
import pTimeout from 'p-timeout'

export default {
	name: 'NewMessageForm',
	components: {
		AdvancedInput,
		Quote,
	},
	data: function() {
		return {
			text: '',
			/**
			 * Stores the cancel function
			 */
			cancelPostNewMessage: () => {},
		}
	},
	computed: {
		/**
		 * The current conversation token
		 *
		 * @returns {String}
		 */
		token() {
			return this.$route.params.token
		},
		messageToBeReplied() {
			return this.$store.getters.getMessageToBeReplied(this.token)
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
				unsent: false,
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
				// Get a new cancelable request function and cancel function pair
				const { request, cancel } = CancelableRequest(postNewMessage)
				// Make the request
				try {
					// Posts the message to the server
					const response = await pTimeout(request(temporaryMessage), 10, () => {
						cancel('canceled')
						throw new Error('timeout')
					})
					// If successful, deletes the temporary message from the store
					this.$store.dispatch('deleteMessage', temporaryMessage)
					// Also remove the message to be replied for this conversation
					this.$store.dispatch('removeMessageToBeReplied', this.token)
					// Add the complete version of the message receive by the server
					this.$store.dispatch('processMessage', response.data.ocs.data)
				} catch (error) {
					if (error.message === 'timeout') {
						// If successful, deletes the temporary message from the store
						this.$store.dispatch('setUnsentState', temporaryMessage)
						// Also remove the message to be replied for this conversation
						this.$store.dispatch('removeMessageToBeReplied', this.token)
					}
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
