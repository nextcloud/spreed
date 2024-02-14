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
	<div class="forwarder">
		<NcEmptyContent :description="t('spreed', 'The message has been forwarded to {selectedConversationName}')">
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
		<!-- First step of the flow: selection of the room to which forward the
		message to -->
		<RoomSelector v-if="!showForwardedConfirmation"
			:container="container"
			:show-postable-only="true"
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
import cloneDeep from 'lodash/cloneDeep.js'

import Check from 'vue-material-design-icons/Check.vue'

import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import RoomSelector from '../../../../RoomSelector.vue'

export default {
	name: 'Forwarder',

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

		/**
		 * Object containing all the mentions in the message that will be forwarded
		 *
		 * @return {object} mentions.
		 */
		mentions() {
			const mentions = {}
			for (const key in this.messageObject.messageParameters) {
				if (key.startsWith('mention')) {
					mentions[key] = this.messageObject.messageParameters[key]
				}
			}
			return mentions
		},
	},

	methods: {
		async setSelectedConversationToken(conversation) {
			this.selectedConversationToken = conversation.token
			this.selectedConversationName = conversation.displayName
			const messageToBeForwarded = cloneDeep(this.messageObject)
			// Overwrite the selected conversation token
			messageToBeForwarded.token = conversation.token

			if (messageToBeForwarded.parent) {
				delete messageToBeForwarded.parent
			}

			if (messageToBeForwarded.message === '{object}' && messageToBeForwarded.messageParameters.object) {
				const richObject = messageToBeForwarded.messageParameters.object
				try {
					const response = await this.$store.dispatch('forwardRichObject', {
						token: conversation.token,
						richObject: {
							objectId: richObject.id,
							objectType: richObject.type,
							metaData: JSON.stringify(richObject),
							referenceId: '',
						},
					})
					this.showForwardedConfirmation = true
					this.forwardedMessageID = response.data.ocs.data.id
				} catch (error) {
					console.error('Error while forwarding message', error)
					showError(t('spreed', 'Error while forwarding message'))
				}
				return
			}

			// If there are mentions in the message to be forwarded, replace them in the message
			// text.
			if (this.mentions !== {}) {
				for (const mention in this.mentions) {
					const mentionString = mention.includes('mention-call')
						? `**${this.mentions[mention].name}**`
						: `@"${this.mentions[mention].id}"`
					messageToBeForwarded.message = messageToBeForwarded.message.replace(`{${mention}}`, mentionString)
				}
			}
			try {
				const response = await this.$store.dispatch('forwardMessage', { messageToBeForwarded })
				this.showForwardedConfirmation = true
				this.forwardedMessageID = response.data.ocs.data.id
			} catch (error) {
				console.error('Error while forwarding message', error)
				showError(t('spreed', 'Error while forwarding message'))
			}

		},

		openConversation() {

			this.$router.push({
				name: 'conversation',
				hash: `#message_${this.forwardedMessageID}`,
				params: {
					token: `${this.selectedConversationToken}`,
				},
			})
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			this.showForwardedConfirmation = false
			this.forwardedMessageID = ''
		},

		handleClose() {
			this.$emit('close')
		},
	},
}
</script>

<style lang="scss" scoped>

.forwarder {
	padding: 20px;
}

</style>
