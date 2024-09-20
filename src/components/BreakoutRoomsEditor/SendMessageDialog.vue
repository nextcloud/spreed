<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog ref="dialog"
		:name="dialogTitle"
		close-on-click-outside
		size="normal"
		v-on="$listeners"
		@update:open="$emit('close')">
		<NewMessage ref="newMessage"
			role="region"
			class="send-message-dialog"
			:token="token"
			:container="modalContainerId"
			:aria-label="t('spreed', 'Post message')"
			:broadcast="broadcast"
			@sent="handleMessageSent"
			@failure="handleMessageFailure" />
	</NcDialog>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

import NewMessage from '../NewMessage/NewMessage.vue'

export default {
	name: 'SendMessageDialog',

	components: {
		NcDialog,
		NewMessage,
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

	emits: ['close'],

	data() {
		return {
			modalContainerId: null,
		}
	},

	computed: {
		dialogTitle() {
			return this.broadcast
				? t('spreed', 'Send a message to all breakout rooms')
				: t('spreed', 'Send a message to "{roomName}"', { roomName: this.displayName })
		},
	},

	mounted() {
		// Postpone render of NewMessage until modal container is mounted
		this.modalContainerId = '#' + this.$refs.dialog.$el.querySelector('.modal-container')?.id
		this.$nextTick(() => {
			this.$refs.newMessage.focusInput()
		})
	},

	methods: {
		t,
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
	padding-bottom: calc(3 * var(--default-grid-baseline));
}
</style>
