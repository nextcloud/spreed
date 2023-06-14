<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="wrapper">
		<NcButton type="tertiary"
			class="toggle"
			:aria-label="t('spreed', 'Create a new group conversation')"
			:title="t('spreed', 'Create a new group conversation')"
			@click="showModal">
			<template #icon>
				<Plus :size="20" />
			</template>
		</NcButton>
		<!-- New group form -->
		<NcModal v-if="modal"
			:container="container"
			size="normal"
			@close="closeModal">
			<!-- Wrapper for content & navigation -->
			<div class="new-group-conversation talk-modal">
				<h2>{{ t('spreed', 'Create a new group conversation') }}</h2>
				<!-- Content -->
				<div v-show="page === 0" class="new-group-conversation__content">
					<NcTextField ref="conversationName"
						v-model="conversationName"
						:placeholder="t('spreed', 'Enter a name for this conversation')"
						:label="t('spreed', 'Name')"
						label-visible
						@keydown.enter="handleEnter" />
					<NcTextField v-model="conversationDescription"
						:placeholder="t('spreed', 'Enter a description for this conversation')"
						:label="t('spreed', 'Description')"
						label-visible />

					<template v-if="supportsAvatar">
						<label class="avatar-editor__label">
							{{ t('spreed', 'Picture') }}
						</label>
						<ConversationAvatarEditor ref="conversationAvatar"
							:conversation="newConversation"
							controlled
							editable
							@avatar-edited="setIsAvatarEdited" />
					</template>

					<label class="new-group-conversation__label">
						{{ t('spreed', 'Conversation visibility') }}
					</label>
					<NcCheckboxRadioSwitch :checked.sync="isPublic"
						type="switch">
						{{ t('spreed', 'Allow guests to join via link') }}
					</NcCheckboxRadioSwitch>
					<div class="new-group-conversation__wrapper">
						<NcCheckboxRadioSwitch :checked.sync="passwordProtect"
							type="switch"
							:disabled="!isPublic"
							@checked="handleCheckboxInput">
							{{ t('spreed', 'Password protect') }}
						</NcCheckboxRadioSwitch>
						<NcPasswordField v-if="passwordProtect"
							autocomplete="new-password"
							check-password-strength
							:placeholder="t('spreed', 'Enter password')"
							:aria-label="t('spreed', 'Enter password')"
							:value.sync="password" />
					</div>
					<ListableSettings v-model="listable" />
				</div>

				<!-- Second page -->
				<div v-if="page === 1" class="new-group-conversation__content">
					<SetContacts :conversation-name="conversationNameTrimmed" />
				</div>

				<!-- Third page -->
				<div v-else-if="page === 2" class="new-group-conversation__content">
					<Confirmation :token="newConversation.token"
						:conversation-name="conversationNameTrimmed"
						:error="error"
						:is-loading="isLoading"
						:success="success"
						:is-public="isPublic" />
				</div>
			</div>
			<!-- Navigation: different buttons with different actions and
				placement are rendered depending on the current page -->
			<div class="navigation">
				<!-- First page -->
				<NcButton v-if="page===0 && isPublic"
					:disabled="disabled"
					type="tertiary"
					@click="handleCreateConversation">
					{{ t('spreed', 'Create conversation') }}
				</NcButton>
				<NcButton v-if="page===0"
					type="primary"
					:disabled="disabled"
					class="navigation__button-right"
					@click="handleSetConversationName">
					{{ t('spreed', 'Add participants') }}
				</NcButton>
				<!-- Second page -->
				<NcButton v-if="page===1"
					type="tertiary"
					@click="handleClickBack">
					{{ t('spreed', 'Back') }}
				</NcButton>
				<NcButton v-if="page===1"
					type="primary"
					class="navigation__button-right"
					@click="handleCreateConversation">
					{{ t('spreed', 'Create conversation') }}
				</NcButton>
				<!-- Third page -->
				<NcButton v-if="page===2 && (error || isPublic)"
					type="primary"
					class="navigation__button-right"
					@click="closeModal">
					{{ t('spreed', 'Close') }}
				</NcButton>
			</div>
		</NcModal>
	</div>
</template>

<script>

import Plus from 'vue-material-design-icons/Plus.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import ConversationAvatarEditor from '../../ConversationSettings/ConversationAvatarEditor.vue'
import ListableSettings from '../../ConversationSettings/ListableSettings.vue'
import Confirmation from './Confirmation/Confirmation.vue'
import SetContacts from './SetContacts/SetContacts.vue'

import { useIsInCall } from '../../../composables/useIsInCall.js'
import { CONVERSATION, PRIVACY } from '../../../constants.js'
import participant from '../../../mixins/participant.js'
import {
	createPublicConversation,
	createPrivateConversation,
	setConversationPassword,
} from '../../../services/conversationsService.js'
import { EventBus } from '../../../services/EventBus.js'
import { addParticipant } from '../../../services/participantsService.js'

const NEW_CONVERSATION = {
	token: '',
	displayName: '',
	description: '',
	hasPassword: false,
	type: CONVERSATION.TYPE.GROUP,
	readOnly: CONVERSATION.STATE.READ_ONLY,
}

const supportsAvatar = getCapabilities()?.spreed?.features?.includes('avatar')

export default {

	name: 'NewGroupConversation',

	components: {
		ConversationAvatarEditor,
		Confirmation,
		ListableSettings,
		NcButton,
		NcCheckboxRadioSwitch,
		NcModal,
		NcPasswordField,
		NcTextField,
		Plus,
		SetContacts,
	},

	mixins: [participant],

	setup() {
		const isInCall = useIsInCall()
		return { isInCall, supportsAvatar }
	},

	data() {
		return {
			newConversation: Object.assign({}, NEW_CONVERSATION),
			modal: false,
			page: 0,
			isPublic: false,
			isLoading: true,
			success: false,
			error: false,
			password: '',
			passwordProtect: false,
			listable: CONVERSATION.LISTABLE.NONE,
			isAvatarEdited: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},
		conversationName: {
			get() {
				return this.newConversation.displayName
			},
			set(event) {
				this.newConversation.displayName = event.target.value
			},
		},
		conversationDescription: {
			get() {
				return this.newConversation.description
			},
			set(event) {
				this.newConversation.description = event.target.value
			},
		},
		conversationNameTrimmed() {
			return this.conversationName.trim()
		},
		// Controls the disabled/enabled state of the first page's button.
		disabled() {
			return this.conversationNameTrimmed === '' || (this.passwordProtect && this.password === '')
		},
		selectedParticipants() {
			return this.$store.getters.selectedParticipants
		},
	},

	watch: {
		isPublic(value) {
			if (value) {
				this.newConversation.type = CONVERSATION.TYPE.PUBLIC
			} else {
				this.newConversation.type = CONVERSATION.TYPE.GROUP
				this.passwordProtect = false
			}
		},
	},

	mounted() {
		EventBus.$on('new-group-conversation-dialog', this.showModalForItem)
	},

	destroyed() {
		EventBus.$off('new-group-conversation-dialog', this.showModalForItem)
	},

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
				this.$store.dispatch('updateSelectedParticipants', item)
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
			this.page = 0
			this.isPublic = false
			this.isLoading = true
			this.newConversation = Object.assign({}, NEW_CONVERSATION)
			this.isAvatarEdited = false
			this.success = false
			this.error = false
			this.passwordProtect = false
			this.password = ''
			this.listable = CONVERSATION.LISTABLE.NONE
			this.$store.dispatch('purgeNewGroupConversationStore')
		},
		/** Switch to page 2 */
		handleSetConversationName() {
			this.page = 1
		},
		/** Switch to page 1 from page 2 */
		handleClickBack() {
			this.page = 0
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
					if (this.password && this.passwordProtect) {
						await setConversationPassword(this.newConversation.token, this.password)
					}
				} else {
					await this.createConversation(PRIVACY.PRIVATE)
				}
			} catch (exception) {
				console.debug(exception)
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
				console.debug(exception)
				this.isLoading = false
				this.error = true
				// Stop the execution of the method on exceptions.
				return
			}

			for (const participant of this.selectedParticipants) {
				try {
					await addParticipant(this.newConversation.token, participant.id, participant.source)
				} catch (exception) {
					console.debug(exception)
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exceptions.
					return
				}
			}

			this.success = true

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
			let response
			if (flag === PRIVACY.PRIVATE) {
				response = await createPrivateConversation(this.conversationNameTrimmed)
			} else if (flag === PRIVACY.PUBLIC) {
				response = await createPublicConversation(this.conversationNameTrimmed)
			}
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.newConversation.token = conversation.token
			if (this.isAvatarEdited) {
				this.$refs.conversationAvatar.saveAvatar()
			}
			if (this.newConversation.description) {
				this.handleUpdateDescription()
			}
		},
		pushNewRoute() {
			this.$router.push({ name: 'conversation', params: { token: this.newConversation.token } })
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},
		handleCheckboxInput(event) {
			this.passwordProtect = event.target.checked
			// Reinitialise the password value when unchecking the password-protect option.
			if (this.passwordProtect === false) {
				this.password = ''
			}
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
				this.handleSetConversationName()
				this.page = 1
			}
		},
	},

}

</script>

<style lang="scss" scoped>
.toggle {
	height: 44px;
	width: 44px;
	padding: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 var(--default-grid-baseline);
}

.new-group-conversation {
	/** This next 2 rules are pretty hacky, with the modal component somehow
	the margin applied to the content is added to the total modal width,
	so here we subtract it to the width and height of the content.
	*/
	height: auto;
	padding: 20px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	position: relative;

	&__content {
		/**
		 * Top: 30px line height header + 12px margin
		 * Bottom: 44px buttons + 12 px margin
		 * Total: 98px
		 */
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	&__wrapper {
		display: grid;
		grid-template-columns: 1fr 2fr;
		gap: var(--default-grid-baseline);
		align-items: center;
	}

	&__label {
		display: block;
		margin-top: 10px;
		padding: 4px 0;
	}
}

/** Size full in the modal component doesn't have border radius, this adds
it back */
:deep(.modal-container) {
	border-radius: var(--border-radius-large) !important;
	height: 900px;
}

:deep(.modal-wrapper .modal-container) {
			height: max-content;
}

.navigation {
	position: sticky;
    bottom: -1px;
	display: flex;
	justify-content: space-between;
	flex: 0 0 40px;
	background-color: var(--color-main-background);
	box-shadow: 0 -10px 5px var(--color-main-background);
	z-index: 1;
	padding: 10px 20px;

	&__button-right {
		margin-left: auto;
	}
}

:deep(.app-settings-section__hint) {
	color: var(--color-text-lighter);
	padding: 8px 0;
}

:deep(.app-settings-subsection) {
	&:first-child {
		margin-top: 0;
	}
}

</style>
