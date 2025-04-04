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
			:label-id="dialogHeaderPrepId"
			@close="closeModal">
			<h2 :id="dialogHeaderPrepId" class="new-group-conversation__header nc-dialog-alike-header">
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
					@avatar-edited="setIsAvatarEdited"
					@is-password-valid="setIsPasswordValid" />

				<!-- Second page -->
				<NewConversationContactsPage v-if="page === 1"
					class="new-group-conversation__content"
					:selected-participants.sync="selectedParticipants"
					:can-moderate-sip-dial-out="canModerateSipDialOut" />
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
			:label-id="dialogHeaderResId"
			@close="closeModal">
			<NcEmptyContent>
				<template #icon>
					<LoadingComponent v-if="isLoading" />
					<AlertCircle v-else-if="error" :size="64" />
					<Check v-else-if="success && isPublic" :size="64" />
				</template>

				<template #description>
					<p :id="dialogHeaderResId">
						{{ creatingConversationDescription }}
					</p>
				</template>

				<template #action>
					<NcButton v-if="!error && success && isPublic"
						id="copy-link"
						ref="copyLink"
						type="secondary"
						@click="onClickCopyLink">
						{{ t('spreed', 'Copy link') }}
					</NcButton>
					<NcButton v-if="!error && success && isPublic && newConversation.hasPassword"
						id="copy-password"
						type="secondary"
						@click="onClickCopyPassword">
						{{ t('spreed', 'Copy password') }}
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

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcModal from '@nextcloud/vue/components/NcModal'

import NewConversationContactsPage from './NewConversationContactsPage.vue'
import NewConversationSetupPage from './NewConversationSetupPage.vue'
import LoadingComponent from '../LoadingComponent.vue'

import { useId } from '../../composables/useId.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { CONVERSATION } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { copyConversationLinkToClipboard } from '../../utils/handleUrl.ts'

const NEW_CONVERSATION = {
	token: '',
	displayName: '',
	description: '',
	hasPassword: false,
	type: CONVERSATION.TYPE.GROUP,
	isDummyConversation: true,
}
const maxDescriptionLength = getTalkConfig('local', 'conversations', 'description-length') || 500
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
		const lockedParticipants = ref([])
		provide('lockedParticipants', lockedParticipants)

		// Add a visual bulk selection state for SelectableParticipant component
		provide('bulkParticipantsSelection', true)

		const dialogHeaderPrepId = `new-conversation-prepare-${useId()}`
		const dialogHeaderResId = `new-conversation-result-${useId()}`

		return {
			isInCall,
			selectedParticipants,
			lockedParticipants,
			dialogHeaderPrepId,
			dialogHeaderResId,
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
			errorReason: '',
			password: '',
			listable: CONVERSATION.LISTABLE.NONE,
			isAvatarEdited: false,
			isPasswordValid: true,
		}
	},

	computed: {
		isPublic() {
			return this.newConversation.type === CONVERSATION.TYPE.PUBLIC
		},

		conversationName() {
			return this.newConversation.displayName.trim()
		},

		// Controls the disabled/enabled state of the first page's button.
		disabled() {
			return this.conversationName === '' || (this.newConversation.hasPassword && (this.password === '' || !this.isPasswordValid))
				|| this.conversationName.length > CONVERSATION.MAX_NAME_LENGTH
				|| this.newConversation.description.length > maxDescriptionLength
		},

		isFilled() {
			return JSON.stringify(this.newConversation) !== JSON.stringify(NEW_CONVERSATION)
				|| this.listable !== CONVERSATION.LISTABLE.NONE || this.isAvatarEdited
		},

		creatingConversationDescription() {
			if (this.isLoading) {
				return t('spreed', 'Creating the conversation …')
			} else if (this.error) {
				if (this.errorReason === 'password_required') {
					return t('spreed', 'Error: A password is required to create the conversation.')
				}
				return t('spreed', 'Error while creating the conversation')
			} else if (this.success && this.isPublic) {
				return t('spreed', 'All set, the conversation "{conversationName}" was created.', { conversationName: this.conversationName })
			}
			return ''
		}
	},

	watch: {
		success(value) {
			if (!value || !this.isPublic) {
				return
			}
			this.$nextTick(() => {
				this.$refs.copyLink.$el.focus()
			})
		},
	},

	expose: ['showModalForItem', 'showModal'],

	methods: {
		t,
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
				this.lockedParticipants.push(item)
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
			this.lockedParticipants = []
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

			try {
				const avatar = {}
				if (this.isAvatarEdited) {
					if (this.$refs.setupPage.$refs.conversationAvatar.emojiAvatar) {
						avatar.emoji = this.$refs.setupPage.$refs.conversationAvatar.emojiAvatar
						avatar.color = this.$refs.setupPage.$refs.conversationAvatar.backgroundColor
							? this.$refs.setupPage.$refs.conversationAvatar.backgroundColor.slice(1)
							: null
					} else {
						avatar.file = await this.$refs.setupPage.$refs.conversationAvatar.getPictureFormData()
					}
				}

				const conversation = await this.$store.dispatch('createGroupConversation', {
					roomName: this.conversationName,
					roomType: this.isPublic ? CONVERSATION.TYPE.PUBLIC : CONVERSATION.TYPE.GROUP,
					password: this.password,
					description: this.newConversation.description,
					listable: this.listable,
					participants: this.selectedParticipants,
					avatar,
				})
				this.newConversation.token = conversation.token
			} catch (exception) {
				console.error('Error creating new conversation: ', exception)
				this.isLoading = false
				this.error = true
				this.errorReason = exception.message
				// Stop the execution of the method on exceptions.
				return
			}

			this.success = true
			this.isLoading = false

			if (!this.isInCall) {
				// Push the newly created conversation's route.
				this.$router.push({ name: 'conversation', params: { token: this.newConversation.token } })
					.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))

				// Get complete participant list in advance
				this.$store.dispatch('fetchParticipants', { token: this.newConversation.token })
			}

			// Close the modal right away if the conversation is public.
			if (!this.isPublic) {
				this.closeModal()
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

		async onClickCopyPassword() {
			try {
				await navigator.clipboard.writeText(this.password)
				showSuccess(t('spreed', 'Password copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Password could not be copied'))
			}
		},

		setIsPasswordValid(value) {
			this.isPasswordValid = value
		},
	},

}

</script>

<style lang="scss" scoped>

.new-group-conversation {
	&__header {
		flex-shrink: 0;
		padding-top: calc(3 * var(--default-grid-baseline));
		padding-inline: var(--default-clickable-area);
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
		margin-inline-start: auto;
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
