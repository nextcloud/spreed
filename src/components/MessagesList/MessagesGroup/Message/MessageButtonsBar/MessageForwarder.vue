<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="message-forwarder">
		<!-- First step of the flow: selection of the room to which forward the
		message to -->
		<RoomSelector v-if="!showForwardedConfirmation"
			:container="container"
			show-postable-only
			:dialog-title="dialogTitle"
			:dialog-subtitle="dialogSubtitle"
			@select="setSelectedConversationToken"
			@close="handleClose" />

		<!-- Second step of the flow: confirmation modal that gives the user
		the possibility to directly route to the conversation to which the
		message has been forwarded -->
		<NcModal v-else
			:container="container"
			@close="handleClose">
			<NcEmptyContent :description="t('spreed', 'The message has been forwarded to {selectedConversationName}', { selectedConversationName })">
				<template #icon>
					<Check :size="64" />
				</template>
				<template #action>
					<NcButton type="tertiary" @click="handleClose">
						{{ t('spreed', 'Dismiss') }}
					</NcButton>
					<NcButton type="primary" @click="openConversation">
						{{ t('spreed', 'Go to conversation') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</NcModal>
	</div>
</template>

<script>

import { inject } from 'vue'

import Check from 'vue-material-design-icons/Check.vue'

// eslint-disable-next-line
// import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import RoomSelector from '../../../../RoomSelector.vue'

export default {
	name: 'MessageForwarder',

	components: {
		RoomSelector,
		NcEmptyContent,
		NcModal,
		NcButton,
		Check,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
		id: {
			type: [String, Number],
			required: true,
		},
	},

	emits: ['close'],

	setup() {
		return {
			isTalkMainApp: inject('Talk:isMainApp', false)
		}
	},

	data() {
		return {
			selectedConversationToken: null,
			selectedConversationName: null,
			showForwardedConfirmation: false,
			forwardedMessageID: '',
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		dialogTitle() {
			return t('spreed', 'Forward message')
		},

		dialogSubtitle() {
			return t('spreed', 'Choose a conversation to forward the selected message.')
		},
	},

	methods: {
		async setSelectedConversationToken(conversation) {
			this.selectedConversationToken = conversation.token
			this.selectedConversationName = conversation.displayName
			try {
				const response = await this.$store.dispatch('forwardMessage', {
					targetToken: this.selectedConversationToken,
					messageToBeForwarded: this.$store.getters.message(this.token, this.id),
				 })
				this.forwardedMessageID = response.data.ocs.data.id
				this.showForwardedConfirmation = true
			} catch (error) {
				console.error('Error while forwarding message', error)
				window.OCP.Toast.error(t('spreed', 'Error while forwarding message'))
			}
		},

		openConversation() {
			if (!this.isTalkMainApp) {
				// Native redirect to Talk from Files sidebar
				const url = generateUrl('/call/{token}#message_{messageId}', {
					token: this.selectedConversationToken,
					messageId: this.forwardedMessageID,
				})
				window.open(url, '_blank').focus()
			} else {
				this.$router.push({
					name: 'conversation',
					hash: `#message_${this.forwardedMessageID}`,
					params: {
						token: `${this.selectedConversationToken}`,
					},
				}).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			}

			this.showForwardedConfirmation = false
			this.forwardedMessageID = ''
			this.$emit('close')
		},

		handleClose() {
			this.$emit('close')
		},
	},
}
</script>

<style lang="scss" scoped>

:deep(.empty-content) {
	padding: 20px;
}
:deep(.empty-content__action) {
	gap: 10px;
}
</style>
