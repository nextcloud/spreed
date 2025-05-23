<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="message-forwarder">
		<!-- First step of the flow: selection of the room to which forward the
		message to -->
		<RoomSelector v-if="!showForwardedConfirmation"
			show-postable-only
			allow-federation
			:dialog-title="dialogTitle"
			:dialog-subtitle="dialogSubtitle"
			@select="setSelectedConversationToken"
			@close="handleClose" />

		<!-- Second step of the flow: confirmation modal that gives the user
		the possibility to directly route to the conversation to which the
		message has been forwarded -->
		<NcDialog v-else
			:name="dialogTitle"
			close-on-click-outside
			@update:open="handleClose">
			<NcEmptyContent :description="t('spreed', 'The message has been forwarded to {selectedConversationName}', { selectedConversationName })">
				<template #icon>
					<Check :size="64" />
				</template>
			</NcEmptyContent>
			<template #actions>
				<NcButton type="tertiary" @click="handleClose">
					{{ t('spreed', 'Dismiss') }}
				</NcButton>
				<NcButton type="primary" @click="openConversation">
					{{ t('spreed', 'Go to conversation') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>

import { inject, ref } from 'vue'

import Check from 'vue-material-design-icons/Check.vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

import RoomSelector from '../../../../RoomSelector.vue'

export default {
	name: 'MessageForwarder',

	components: {
		Check,
		NcButton,
		NcDialog,
		NcEmptyContent,
		RoomSelector,
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
		const selectedConversationToken = ref(null)
		const selectedConversationName = ref(null)
		const showForwardedConfirmation = ref(false)
		const forwardedMessageID = ref('')

		return {
			isTalkMainApp: inject('Talk:isMainApp', false),
			selectedConversationToken,
			selectedConversationName,
			showForwardedConfirmation,
			forwardedMessageID,
		}
	},

	computed: {
		dialogTitle() {
			return t('spreed', 'Forward message')
		},

		dialogSubtitle() {
			return t('spreed', 'Choose a conversation to forward the selected message.')
		},
	},

	methods: {
		t,
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
				showError(t('spreed', 'Error while forwarding message'))
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
				}).catch((err) => console.debug(`Error while pushing the new conversation's route: ${err}`))
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
