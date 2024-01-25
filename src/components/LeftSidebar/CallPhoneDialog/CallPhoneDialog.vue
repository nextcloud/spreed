<!--
	- @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
	-
	- @author Maksim Sukharev <antreesy.web@gmail.com>
	-
	- @license AGPL-3.0-or-later
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
	<NcModal v-if="modal"
		class="call-phone"
		:container="container"
		@close="closeModal">
		<template v-if="!loading">
			<h2 class="call-phone__header">
				{{ t('spreed', 'Call a phone number') }}
			</h2>

			<div class="call-phone__form">
				<NcTextField ref="textField"
					class="call-phone__form-input"
					:label="t('spreed', 'Search participants or phone numbers')"
					label-visible
					:value.sync="searchText"
					@keydown.enter="createConversation(participantPhoneItem)" />
				<DialpadPanel container=".call-phone__form"
					:value.sync="searchText"
					@submit="createConversation(participantPhoneItem)" />
			</div>

			<SelectPhoneNumber :name="t('spreed', 'Call a phone number')"
				:value="searchText"
				:participant-phone-item.sync="participantPhoneItem"
				@select="createConversation" />
		</template>

		<NcEmptyContent v-else class="call-phone__loading">
			<template #icon>
				<LoadingComponent />
			</template>

			<template #description>
				<p>{{ t('spreed', 'Creating the conversation …') }}</p>
			</template>
		</NcEmptyContent>
	</NcModal>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import DialpadPanel from '../../DialpadPanel.vue'
import LoadingComponent from '../../LoadingComponent.vue'
import SelectPhoneNumber from '../../SelectPhoneNumber.vue'

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import { callSIPDialOut } from '../../../services/callsService.js'
import { createPrivateConversation } from '../../../services/conversationsService.js'
import { addParticipant } from '../../../services/participantsService.js'

export default {
	name: 'CallPhoneDialog',

	components: {
		DialpadPanel,
		LoadingComponent,
		NcEmptyContent,
		NcModal,
		NcTextField,
		SelectPhoneNumber,
	},

	data() {
		return {
			modal: false,
			loading: false,
			searchText: '',
			participantPhoneItem: {},
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},
	},

	expose: ['showModal'],

	watch: {
		modal(value) {
			if (!value) {
				return
			}

			this.$nextTick(() => {
				this.focusInput()
			})
		},
	},

	methods: {
		showModal() {
			this.modal = true
		},

		/**
		 * Reinitialise the component to it's initial state. This is necessary
		 * because once the component is mounted its data would persist even if
		 * the modal closes
		 */
		closeModal() {
			this.modal = false
			this.loading = false
			this.searchText = ''
			this.participantPhoneItem = {}
		},

		focusInput() {
			this.$refs.textField.focus()
		},

		async createConversation() {
			let conversation
			try {
				this.loading = true
				const response = await createPrivateConversation(this.participantPhoneItem.phoneNumber, CONVERSATION.OBJECT_TYPE.PHONE)
				conversation = response.data.ocs.data
				await this.$store.dispatch('addConversation', conversation)

				await addParticipant(conversation.token, this.participantPhoneItem.id, this.participantPhoneItem.source)

				this.$router.push({ name: 'conversation', params: { token: conversation.token } })
				await this.$store.dispatch('joinConversation', { token: conversation.token })

				this.startPhoneCall(conversation.token, this.participantPhoneItem.phoneNumber)

				this.closeModal()
			} catch (exception) {
				console.debug(exception)
				showError(t('spreed', 'An error occurred while calling a phone number'))
				if (conversation) {
					this.$store.dispatch('deleteConversationFromServer', { token: conversation.token })
				}
				this.closeModal()
			}
		},

		async startPhoneCall(token, phoneNumber) {
			let flags = PARTICIPANT.CALL_FLAG.IN_CALL
			flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO

			try {
				const response = await this.$store.dispatch('fetchParticipants', { token })

				// Close navigation
				emit('toggle-navigation', { open: false })
				console.info('Joining call')
				await this.$store.dispatch('joinCall', {
					token,
					participantIdentifier: this.$store.getters.getParticipantIdentifier(),
					flags,
					silent: false,
					recordingConsent: true,
				})

				// request above could be cancelled, if there is parallel request, and return null
				// in that case participants list will be fetched anyway and keeped in the store
				const participantsList = response?.data.ocs.data || this.$store.getters.participantsList(token)
				const attendeeId = participantsList.find(participant => participant.phoneNumber === phoneNumber)?.attendeeId

				await callSIPDialOut(token, attendeeId)
			} catch (error) {
				if (error?.response?.data?.ocs?.data?.message) {
					showError(t('spreed', 'Phone number could not be called: {error}', {
						error: error?.response?.data?.ocs?.data?.message
					}))
				} else {
					console.error(error)
					showError(t('spreed', 'Phone number could not be called'))
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.call-phone {
	&:deep(.modal-wrapper) {
		.modal-container {
			height: 60%;
		}

		.modal-container__content {
			padding: calc(var(--default-grid-baseline) * 5);
		}
	}

	&__form {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);

		&-input {
			margin: 0;
		}
	}

  &__loading {
    margin: 0 !important;
	padding: 0 !important;
    height: 100%;
  }
}
</style>
