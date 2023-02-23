<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcModal v-on="$listeners">
		<div class="send-message-dialog">
			<h2 class="send-message-dialog__title">
				{{ dialogTitle }}
			</h2>
			<NewMessageForm role="region"
				:token="token"
				:breakout-room="true"
				:aria-label="t('spreed', 'Post message')"
				:broadcast="broadcast"
				@sent="handleMessageSent"
				@failure="handleMessageFailure" />
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import NewMessageForm from '../NewMessageForm/NewMessageForm.vue'

export default {
	name: 'SendMessageDialog',

	components: {
		NcModal,
		NewMessageForm,
	},

	props: {
		/**
		 * The Breakout room token.
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * The conversation display name
		 */
		displayName: {
			type: String,
			default: '',
		},

		/**
		 * Broadcast messages to all breakout rooms of a given conversation. In
		 * case this is true, the token needs to be from a conversation that
		 * has breakout rooms configured.
		 */
		broadcast: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		dialogTitle() {
			return this.broadcast
				? t('spreed', 'Send message to all breakout rooms')
				: t('spreed', 'Send a message to "{roomName}"', { roomName: this.displayName })
		},
	},

	methods: {
		handleMessageSent() {
			showSuccess(this.broadcast
				? t('spreed', 'The message was sent to all breakout rooms')
				: t('spreed', 'The message was sent to "{roomName}"', { roomName: this.displayName }))
			this.$emit('close')
		},

		handleMessageFailure() {
			showError(t('spreed', 'The message could not be sent'))
			this.$emit('close')
		},
	},

}
</script>

<style lang="scss" scoped>
.send-message-dialog {
	&__title {
		padding: 20px 20px 0 20px;
		margin-bottom: 0;
	}
}
</style>
