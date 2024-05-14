<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="modal">
		<!-- New group form -->
		<NcModal v-show="page !== 2"
			class="new-group-conversation"
			:close-on-click-outside="!isFilled"
			:container="container"
			@close="closeModal">
			<h2 class="new-group-conversation__header">
				{{ t('spreed', 'Create a new group conversation') }}
			</h2>

			<div class="new-group-conversation__main">
				<!-- First page -->
				<NewConversationSetupPage v-show="page === 0"
					ref="setupPage"
					:new-conversation.sync="newConversation"
					:password.sync="password"
					:listable.sync="listable"
					class="new-group-conversation__content"
					@handle-enter="handleEnter"
					@avatar-edited="setIsAvatarEdited" />

				<!-- Second page -->
				<NewConversationContactsPage v-if="page === 1"
					class="new-group-conversation__content"
					:selected-participants.sync="selectedParticipants"
					:can-moderate-sip-dial-out="canModerateSipDialOut"
					:conversation-name="conversationName" />
			</div>

			<!-- Navigation: different buttons with different actions and
				placement are rendered depending on the current page -->
			<div class="new-group-conversation__footer">
				<!-- First page -->
				<NcButton v-if="page === 0 && conversationName"
					:disabled="disabled"
					type="tertiary"
					@click="handleCreateConversation">
					{{ t('spreed', 'Create conversation') }}
				</NcButton>
				<NcButton v-if="page === 0"
					type="primary"
					:disabled="disabled"
					class="new-group-conversation__button"
					@click="switchToPage(1)">
					{{ t('spreed', 'Add participants') }}
				</NcButton>
				<!-- Second page -->
				<NcButton v-if="page === 1"
					type="tertiary"
					@click="switchToPage(0)">
					{{ t('spreed', 'Back') }}
				</NcButton>
				<NcButton v-if="page === 1"
					type="primary"
					class="new-group-conversation__button"
					@click="handleCreateConversation">
					{{ t('spreed', 'Create conversation') }}
				</NcButton>
			</div>
		</NcModal>

		<!-- Third page : this is the confirmation page-->
		<NcModal v-if="page === 2"
			:container="container"
			@close="closeModal">
			<NcEmptyContent>
				<template #icon>
					<LoadingComponent v-if="isLoading" />
					<AlertCircle v-else-if="error" :size="64" />
					<Check v-else-if="success && isPublic" :size="64" />
				</template>

				<template #description>
					<p v-if="isLoading">
						{{ t('spreed', 'Creating the conversation …') }}
					</p>
					<p v-else-if="error">
						{{ t('spreed', 'Error while creating the conversation') }}
					</p>
					<p v-else-if="success && isPublic">
						{{ t('spreed', 'All set, the conversation "{conversationName}" was created.', { conversationName }) }}
					</p>
				</template>

				<template #action>
					<NcButton v-if="(error || isPublic) && !isLoading"
						ref="closeButton"
						type="tertiary"
						@click="closeModal">
						{{ t('spreed', 'Close') }}
					</NcButton>
					<NcButton v-if="!error && success && isPublic"
						id="copy-link"
						ref="copyLink"
						type="secondary"
						@click="onClickCopyLink">
						{{ t('spreed', 'Copy conversation link') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</NcModal>
	</div>
</template>

<script>
import { provide, ref } from 'vue'

import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Check from 'vue-material-design-icons/Check.vue'

// eslint-disable-next-line
// import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import NewConversationContactsPage from './NewConversationContactsPage.vue'
import NewConversationSetupPage from './NewConversationSetupPage.vue'
import LoadingComponent from '../LoadingComponent.vue'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { CONVERSATION, PRIVACY } from '../../constants.js'
import {
	createPublicConversation,
	createPrivateConversation,
	setConversationPassword,
} from '../../services/conversationsService.js'
import { addParticipant } from '../../services/participantsService.js'
import { copyConversationLinkToClipboard } from '../../utils/handleUrl.ts'

const NEW_CONVERSATION = {
	token: '',
	displayName: '',
	description: '',
	hasPassword: false,
	type: CONVERSATION.TYPE.GROUP,
	isDummyConversation: true,
}

export default {
	name: 'NewConversationDialog',

	components: {
		NewConversationSetupPage,
		LoadingComponent,
		NcButton,
		NcEmptyContent,
		NcModal,
		NewConversationContactsPage,
		Check,
		AlertCircle,
	},

	props: {
		canModerateSipDialOut: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const isInCall = useIsInCall()
		const selectedParticipants = ref([])
		provide('selectedParticipants', selectedParticipants)

		// Add a visual bulk selection state for Participant component
		provide('bulkParticipantsSelection', true)

		return {
			isInCall,
			selectedParticipants,
		}
	},

	data() {
		return {
			modal: false,
			newConversation: Object.assign({}, NEW_CONVERSATION),
			page: 0,
			isLoading: true,
			success: false,
			error: false,
			password: '',
			listable: CONVERSATION.LISTABLE.NONE,
			isAvatarEdited: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		isPublic() {
			return this.newConversation.type === CONVERSATION.TYPE.PUBLIC
		},

		conversationName() {
			return this.newConversation.displayName.trim()
		},

		// Controls the disabled/enabled state of the first page's button.
		disabled() {
			return this.conversationName === '' || (this.newConversation.hasPassword && this.password === '')
				|| this.conversationName.length > CONVERSATION.MAX_NAME_LENGTH
				|| this.newConversation.description.length > CONVERSATION.MAX_DESCRIPTION_LENGTH
		},

		isFilled() {
			return JSON.stringify(this.newConversation) !== JSON.stringify(NEW_CONVERSATION)
				|| this.listable !== CONVERSATION.LISTABLE.NONE || this.isAvatarEdited
		},
	},

	watch: {
		success(value) {
			if (!value) {
				return
			}
			this.$nextTick(() => {
				this.$refs.copyLink.$el.focus()
			})
		},

		error(value) {
			if (!value) {
				return
			}
			this.$nextTick(() => {
				this.$refs.closeButton.$el.focus()
			})
		},
	},

	expose: ['showModalForItem', 'showModal'],

	methods: {
		showModal() {
			this.modal = true
		},

		setIsAvatarEdited(value) {
			this.isAvatarEdited = value
		},

		showModalForItem(item) {
			if (item) {
				// Preload the conversation name from group selection
				this.newConversation.displayName = item.label
				this.selectedParticipants.push(item)
			}

			this.showModal()
		},
		/**
		 * Reinitialise the component to it's initial state. This is necessary
		 * because once the component is mounted its data would persist even if
		 * the modal closes
		 */
		closeModal() {
			this.modal = false
			this.newConversation = Object.assign({}, NEW_CONVERSATION)
			this.page = 0
			this.isLoading = true
			this.success = false
			this.error = false
			this.password = ''
			this.listable = CONVERSATION.LISTABLE.NONE
			this.isAvatarEdited = false
			this.selectedParticipants = []
		},

		switchToPage(value) {
			this.page = value
		},

		/**
		 * Handles the creation of the group conversation, adds the selected
		 * participants to it and routes to it
		 */
		async handleCreateConversation() {
			this.page = 2

			// TODO: move all operations to a single store action
			// and commit + addConversation only once at the very end
			try {
				if (this.isPublic) {
					await this.createConversation(PRIVACY.PUBLIC)
					if (this.password && this.newConversation.hasPassword) {
						await setConversationPassword(this.newConversation.token, this.password)
					}
				} else {
					await this.createConversation(PRIVACY.PRIVATE)
				}
			} catch (exception) {
				console.error(exception)
				this.isLoading = false
				this.error = true
				// Stop the execution of the method on exceptions.
				return
			}

			try {
				await this.$store.dispatch('setListable', {
					token: this.newConversation.token,
					listable: this.listable,
				})
			} catch (exception) {
				console.error(exception)
				this.isLoading = false
				this.error = true
				// Stop the execution of the method on exceptions.
				return
			}

			for (const participant of this.selectedParticipants) {
				try {
					await addParticipant(this.newConversation.token, participant.id, participant.source)
				} catch (exception) {
					console.error(exception)
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exceptions.
					return
				}
			}

			this.success = true
			this.isLoading = false

			if (!this.isInCall) {
				// Push the newly created conversation's route.
				this.pushNewRoute()
			}

			// Close the modal right away if the conversation is public.
			if (!this.isPublic) {
				this.closeModal()
			}
		},
		/**
		 * Creates a new private or public conversation, adds it to the store and sets
		 * the local token value to the newly created conversation's token
		 *
		 * @param {number} flag choose to send a request with private or public flag
		 */
		async createConversation(flag) {
			try {
				let response
				if (flag === PRIVACY.PRIVATE) {
					response = await createPrivateConversation(this.conversationName)
				} else if (flag === PRIVACY.PUBLIC) {
					response = await createPublicConversation(this.conversationName)
				}
				const conversation = response.data.ocs.data
				this.$store.dispatch('addConversation', conversation)
				this.newConversation.token = conversation.token
				if (this.isAvatarEdited) {
					this.$refs.setupPage.$refs.conversationAvatar.saveAvatar()
				}
				if (this.newConversation.description) {
					this.handleUpdateDescription()
				}
			} catch (error) {
				console.error('Error creating new conversation: ', error)
			}
		},
		pushNewRoute() {
			this.$router.push({ name: 'conversation', params: { token: this.newConversation.token } })
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		async handleUpdateDescription() {
			try {
				await this.$store.dispatch('setConversationDescription', {
					token: this.newConversation.token,
					description: this.newConversation.description,
				})
			} catch (error) {
				console.error('Error while setting conversation description', error)
				showError(t('spreed', 'Error while updating conversation description'))
			}
		},

		/** Handles the press of the enter key */
		handleEnter() {
			if (!this.disabled) {
				this.switchToPage(1)
			}
		},

		onClickCopyLink() {
			copyConversationLinkToClipboard(this.newConversation.token)
		},
	},

}

</script>

<style lang="scss" scoped>

.new-group-conversation {
	&__header {
		flex-shrink: 0;
		margin: 0;
		padding: 10px 20px;
	}

	&__main {
		flex-grow: 1;
		overflow: auto;
	}

	&__content {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
		padding: 10px 20px;
	}

	&__footer {
		flex-shrink: 0;
		display: flex;
		justify-content: space-between;
		padding: 10px 20px;
		box-shadow: 0 -10px 5px var(--color-main-background);
	}

	&__button {
		margin-left: auto;
	}

	:deep(.modal-wrapper) {
		.modal-container {
			height: 90%;
		}

		.modal-container__content {
			display: flex !important;
			flex-direction: column;
			height: 100%;
			overflow: hidden !important;
		}
	}
}

:deep(.empty-content) {
	padding: 20px;
}

:deep(.empty-content__action) {
	gap: 10px;
}

</style>
