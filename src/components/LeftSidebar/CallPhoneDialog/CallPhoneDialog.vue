<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :open="modal"
		:name="t('spreed', 'Call a phone number')"
		class="call-phone"
		size="normal"
		close-on-click-outside
		@update:open="closeModal">
		<template v-if="!loading">
			<div class="call-phone__form">
				<NcTextField ref="textField"
					v-model="searchText"
					class="call-phone__form-input"
					:label="t('spreed', 'Search participants or phone numbers')"
					label-visible
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
	</NcDialog>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import LoadingComponent from '../../LoadingComponent.vue'
import SelectPhoneNumber from '../../SelectPhoneNumber.vue'
import DialpadPanel from '../../UIShared/DialpadPanel.vue'
import { CONVERSATION, PARTICIPANT } from '../../../constants.ts'
import { callSIPDialOut } from '../../../services/callsService.js'
import { hasTalkFeature } from '../../../services/CapabilitiesManager.ts'
import { createLegacyConversation } from '../../../services/conversationsService.ts'
import { addParticipant } from '../../../services/participantsService.js'
import { useActorStore } from '../../../stores/actor.ts'

export default {
	name: 'CallPhoneDialog',

	components: {
		DialpadPanel,
		LoadingComponent,
		NcDialog,
		NcEmptyContent,
		NcTextField,
		SelectPhoneNumber,
	},

	expose: ['showModal'],

	setup() {
		return {
			actorStore: useActorStore(),
		}
	},

	data() {
		return {
			modal: false,
			loading: false,
			searchText: '',
			participantPhoneItem: {},
		}
	},

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
		t,
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
				const response = await createLegacyConversation({
					roomType: CONVERSATION.TYPE.GROUP,
					roomName: this.participantPhoneItem.phoneNumber,
					objectType: hasTalkFeature('local', 'sip-direct-dialin') ? CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY : CONVERSATION.OBJECT_TYPE.PHONE_LEGACY,
				})
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
					participantIdentifier: this.actorStore.participantIdentifier,
					flags,
					silent: false,
					recordingConsent: true,
				})

				// request above could be cancelled, if there is parallel request, and return null
				// in that case participants list will be fetched anyway and keeped in the store
				const participantsList = response?.data.ocs.data || this.$store.getters.participantsList(token)
				const attendeeId = participantsList.find((participant) => participant.phoneNumber === phoneNumber)?.attendeeId

				await callSIPDialOut(token, attendeeId)
			} catch (error) {
				if (error?.response?.data?.ocs?.data?.message) {
					showError(t('spreed', 'Phone number could not be called: {error}', {
						error: error?.response?.data?.ocs?.data?.message,
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
	:deep(.modal-wrapper) {
		.modal-container {
			height: 60%;
		}

		.dialog__content {
			padding-bottom: calc(var(--default-grid-baseline) * 3);
		}
	}

	&__form {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);
	}

	&__loading {
		margin: 0 !important;
		padding: 0 !important;
		height: 100%;
	}
}
</style>
