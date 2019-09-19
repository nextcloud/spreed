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
	<div class="new-message">
		<form class="new-message-form">
			<button class="new-message-form__button icon-clip-add-file" />
			<button class="new-message-form__button icon-emoji-smile" />
			<AdvancedInput v-model="text" @submit="handleSubmit" />
			<button class="new-message-form__button icon-bell-outline" />
			<button type="submit" class="new-message-form__button icon-folder" @click.prevent="handleSubmit" />
		</form>
	</div>
</template>

<script>
import AdvancedInput from './AdvancedInput/AdvancedInput'
import { postNewMessage } from '../../services/messagesService'

export default {
	name: 'NewMessageForm',
	components: {
		AdvancedInput
	},
	data: function() {
		return {
			text: ''
		}
	},
	computed: {
		// the current conversation token
		token() {
			return this.$route.params.token
		}
	},
	methods: {
		// Create a temporary ID that will be used until the
		// actual message object is retrieved from the server
		createTemporaryMessageId() {
			const date = new Date()
			return `temp_${(date.getTime()).toString()}`
		},
		// Create a temporary ID that will be used until the
		// actual message object is retrieved from the server
		createTemporaryMessage() {
			const message = {
				id: this.createTemporaryMessageId(),
				actorDisplayName: OC.getCurrentUser().displayName,
				message: this.text,
				token: this.token
			}
			return message
		},
		// Add the new message to the store and post the new message
		async handleSubmit() {
			const temporaryMessage = this.createTemporaryMessage()
			console.debug(temporaryMessage)
			this.$store.dispatch('addTemporaryMessage', temporaryMessage)
			this.$nextTick(function() {
				document.querySelector('.scroller').scrollTop = document.querySelector('.scroller').scrollHeight
			})
			try {
				const response = await postNewMessage(this.token, this.text)
				console.debug(response.data.ocs.data)
				this.$store.dispatch('deleteMessage', temporaryMessage)
				this.$store.dispatch('processMessage', response.data.ocs.data)
			} catch (error) {
				console.debug(`error while submitting message ${error}`)
			}
			this.text = ''
		}
	}
}
</script>

<style lang="scss" scoped>

.new-message {
    border-top: 1px solid lightgray;
    position: sticky;
    position: -webkit-sticky;
    bottom: 0;
    background-color: white;
    &-form {
        display: flex;
        align-items: center;
        &__input {
            flex-grow: 1;
            border:none;
        }
        &__button {
            width: 44px;
            height: 44px;
            margin: auto;
            background-color: transparent;
            border: none;
        }
	}
}
</style>
