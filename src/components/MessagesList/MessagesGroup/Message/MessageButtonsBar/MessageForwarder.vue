<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="message-forwarder">
		<!-- First step of the flow: selection of the room to which forward the
		message to -->
		<RoomSelector
			v-if="!showForwardedConfirmation"
			showPostableOnly
			allowFederation
			multiple
			:dialogTitle="dialogTitle"
			:dialogSubtitle="dialogSubtitle"
			@select="forwardToConversations"
			@close="handleClose" />

		<!-- Second step of the flow: confirmation modal that gives the user
		the possibility to directly route to the conversation to which the
		message has been forwarded -->
		<NcDialog
			v-else
			:name="dialogTitle"
			closeOnClickOutside
			@update:open="handleClose">
			<NcEmptyContent :description="t('spreed', 'The message has been forwarded to {selectedConversationName}', { selectedConversationName })">
				<template #icon>
					<IconCheck :size="64" />
				</template>
			</NcEmptyContent>
			<template #actions>
				<NcButton variant="tertiary" @click="handleClose">
					{{ t('spreed', 'Dismiss') }}
				</NcButton>
				<NcButton variant="primary" @click="openConversation">
					{{ t('spreed', 'Go to conversation') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>

import { showError, showSuccess } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { inject, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import IconCheck from 'vue-material-design-icons/Check.vue'
import RoomSelector from '../../../../RoomSelector.vue'

export default {
	name: 'MessageForwarder',

	components: {
		IconCheck,
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
			return t('spreed', 'Choose one or more conversations to forward the selected message.')
		},

		message() {
			return this.$store.getters.message(this.token, this.id)
		},
	},

	methods: {
		t,
		/**
		 * Forward the message to each of the selected conversations
		 *
		 * @param {Array<object>} conversations the selected conversations
		 */
		async forwardToConversations(conversations) {
			const results = await Promise.allSettled(conversations.map((conversation) => {
				return this.$store.dispatch('forwardMessage', {
					targetToken: conversation.token,
					messageToBeForwarded: this.message,
				})
			}))

			const failedConversations = conversations.filter((conversation, index) => results[index].status === 'rejected')
			results.filter((result) => result.status === 'rejected')
				.forEach((result) => console.error('Error while forwarding message', result.reason))

			if (failedConversations.length === conversations.length) {
				showError(t('spreed', 'Error while forwarding message'))
				return
			}

			if (failedConversations.length > 0) {
				showError(t('spreed', 'The message could not be forwarded to {conversationNames}', {
					conversationNames: failedConversations.map((conversation) => conversation.displayName).join(', '),
				}))
			}

			if (conversations.length > 1) {
				const forwardedCount = conversations.length - failedConversations.length
				showSuccess(n('spreed', 'The message has been forwarded to %n conversation', 'The message has been forwarded to %n conversations', forwardedCount))
				this.handleClose()
				return
			}

			// Only one conversation: offer to directly open it
			this.selectedConversationToken = conversations[0].token
			this.selectedConversationName = conversations[0].displayName
			this.forwardedMessageID = results[0].value.data.ocs.data.id
			this.showForwardedConfirmation = true
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
