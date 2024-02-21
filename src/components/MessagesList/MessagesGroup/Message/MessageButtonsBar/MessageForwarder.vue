<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
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

import Check from 'vue-material-design-icons/Check.vue'

import { showError } from '@nextcloud/dialogs'
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
		/**
		 * The message to be forwarded
		 */
		messageObject: {
			type: Object,
			required: true,
		},
	},

	emits: ['close'],

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
					messageToBeForwarded: this.messageObject,
				 })
				this.forwardedMessageID = response.data.ocs.data.id
				this.showForwardedConfirmation = true
			} catch (error) {
				console.error('Error while forwarding message', error)
				showError(t('spreed', 'Error while forwarding message'))
			}
		},

		openConversation() {
			const isTalkApp = IS_DESKTOP || window.location.pathname.includes('/apps/spreed') || window.location.pathname.includes('/call/')

			if (!isTalkApp) {
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
