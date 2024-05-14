<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal ref="modal"
		:container="container"
		v-on="$listeners">
		<div class="send-message-dialog">
			<h2 class="send-message-dialog__title">
				{{ dialogTitle }}
			</h2>
			<NewMessage v-if="modalContainerId"
				:key="modalContainerId"
				ref="newMessage"
				role="region"
				:token="token"
				:container="modalContainerId"
				:aria-label="t('spreed', 'Post message')"
				:broadcast="broadcast"
				@sent="handleMessageSent"
				@failure="handleMessageFailure" />
		</div>
	</NcModal>
</template>

<script>
// eslint-disable-next-line
// import { showError, showSuccess } from '@nextcloud/dialogs'

import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import NewMessage from '../NewMessage/NewMessage.vue'

export default {
	name: 'SendMessageDialog',

	components: {
		NcModal,
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
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		dialogTitle() {
			return this.broadcast
				? t('spreed', 'Send a message to all breakout rooms')
				: t('spreed', 'Send a message to "{roomName}"', { roomName: this.displayName })
		},
	},

	mounted() {
		// Postpone render of NewMessage until modal container is mounted
		this.modalContainerId = `#modal-description-${this.$refs.modal.randId}`
		this.$nextTick(() => {
			this.$refs.newMessage.focusInput()
		})
	},

	methods: {
		handleMessageSent() {
			showSuccess(this.broadcast
				? t('spreed', 'The message was sent to all breakout rooms')
				: t('spreed', 'The message was sent to "{roomName}"', { roomName: this.displayName }))
			this.$emit('close')
		},

		handleMessageFailure() {
			window.OCP.Toast.error(t('spreed', 'The message could not be sent'))
			this.$emit('close')
		},
	},

}
</script>

<style lang="scss" scoped>
.send-message-dialog {
	padding: 20px 20px 8px;

	&__title {
		margin-bottom: 8px;
	}
}
</style>
