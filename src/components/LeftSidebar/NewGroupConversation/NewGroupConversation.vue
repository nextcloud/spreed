<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div>
		<Popover trigger="hover" placement="bottom">
			<Actions slot="trigger">
				<ActionButton
					class="toggle"
					icon="icon-add"
					@click="showModal" />
			</Actions>
			<p>{{ t('spreed','Create a new group conversation') }}</p>
		</Popover>
		<Modal
			v-if="modal"
			size="full"
			@close="closeModal">
			<div
				class="new-group-conversation talk-modal">
				<div
					class="new-group-conversation__content">
					<template
						v-if="page === 0">
						<SetConversationName
							v-model="conversationNameInput" />
						<SetConversationType
							v-model="isPublic"
							:conversation-name="conversationName" />
						<template v-if="isPublic">
							<input
								id="password-checkbox"
								type="checkbox"
								class="checkbox"
								:checked="checked"
								@input="handleCheckboxInput">
							<label for="password-checkbox">{{ t('spreed', 'Password protect') }}</label>
							<PasswordProtect
								v-if="checked"
								v-model="password" />
						</template>
					</template>
					<template v-if="page === 1">
						<SetContacts
							:conversation-name="conversationName"
							@updateSelectedParticipants="handleUpdateSelectedParticipants" />
					</template>
					<template v-if="page === 2">
						<Confirmation
							:conversation-name="conversationName"
							:error="error"
							:is-loading="isLoading"
							:success="success"
							:is-public="isPublic"
							:link-to-conversation="linkToConversation" />
					</template>
				</div>
				<div
					class="navigation">
					<button
						v-if="page===1"
						class="navigation__button-left"
						@click="handleClickBack">
						{{ t('spreed', 'Back') }}
					</button>
					<button
						v-if="page===0"
						class="navigation__button-right primary"
						:disabled="disabled"
						@click="handleClickForward">
						{{ t('spreed', 'Add participants') }}
					</button>
					<button
						v-if="page===1"
						class="navigation__button-right primary"
						@click="handleCreateConversation">
						{{ t('spreed', 'Create conversation') }}
					</button>
					<button
						v-if="page===2 && (error || isPublic)"
						class="navigation__button-right primary"
						@click="closeModal">
						{{ t('spreed', 'Close') }}
					</button>
				</div>
			</div>
		</modal>
	</div>
</template>

<script>

import Modal from '@nextcloud/vue/dist/Components/Modal'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Popover from '@nextcloud/vue/dist/Components/Popover'
import SetContacts from './SetContacts/SetContacts'
import SetConversationName from './SetConversationName/SetConversationName'
import SetConversationType from './SetConversationType/SetConversationType'
import Confirmation from './Confirmation/Confirmation'
import { addParticipant } from '../../../services/participantsService'
import {
	createPublicConversation,
	createPrivateConversation,
} from '../../../services/conversationsService'
import { generateUrl } from '@nextcloud/router'
import PasswordProtect from './PasswordProtect/PasswordProtect'

export default {

	name: 'NewGroupConversation',

	components: {
		Modal,
		Actions,
		ActionButton,
		SetContacts,
		SetConversationName,
		SetConversationType,
		Confirmation,
		Popover,
		PasswordProtect,
	},

	data() {
		return {
			modal: false,
			page: 0,
			conversationNameInput: '',
			isPublic: false,
			isLoading: true,
			token: '',
			selectedParticipants: [],
			success: false,
			error: false,
			password: '',
			checked: false,
		}
	},

	computed: {
		conversationName() {
			return this.conversationNameInput.trim()
		},
		linkToConversation() {
			if (this.token !== '') {
				return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.token)
			} else return ''
		},
		disabled() {
			return this.conversationName === '' || (this.checked && this.password === '')
		},
	},

	methods: {
		showModal() {
			this.modal = true
		},
		// Resets to the base state of the component
		closeModal() {
			this.modal = false
			this.page = 0
			this.conversationNameInput = ''
			this.isPublic = false
			this.isLoading = true
			this.token = ''
			this.selectedParticipants = []
			this.success = false
			this.error = false
			this.password = ''
		},
		handleSetConversationName(event) {
			this.page = 1
		},

		handleSetConversationType(event) {
			this.isPublic = event
		},

		handleClickForward() {
			if (this.page === 0) {
				if (this.conversationName !== '') {
					this.page = 1
				}
			}
		},
		handleClickBack() {
			this.page = 0
		},

		handleUpdateSelectedParticipants(e) {
			console.debug(e)
			this.selectedParticipants = e
		},

		async handleCreateConversation() {
			this.page = 2
			if (this.isPublic) {
				try {
					await this.createPublicConversation()
				} catch (exception) {
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exceptions.
					return
				}
			} else {
				try {
					await this.createPrivateConversation()
				} catch (exception) {
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exceptions.
					return
				}
			}
			for (const participant of this.selectedParticipants) {
				try {
					await addParticipant(this.token, participant.id, participant.source)
				} catch (exception) {
					console.debug(exception)
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exceptions.
					return
				}
			}
			this.success = true
			// Push the newly created conversation's route.
			this.pushNewRoute()
			// Close the modal right away if the conversation is public.
			if (!this.isPublic) {
				this.closeModal()
			}
		},

		async createPrivateConversation() {
			const response = await createPrivateConversation(this.conversationName)
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.token = conversation.token
		},

		async createPublicConversation() {
			const response = await createPublicConversation(this.conversationName)
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.token = conversation.token
		},
		pushNewRoute() {
			this.$router.push({ name: 'conversation', params: { token: this.token } })
				.catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},
		handleCheckboxInput(event) {
			this.checked = event.target.checked
			// Reinitialise the password value when unchecking the password-protect option.
			if (this.checked === false) {
				this.password = ''
			}
		},
	},

}

</script>

<style lang="scss" scoped>

$dialog-margin: 20px;
$dialog-width: 300px;
$dialog-height: 440px;

.toggle {
	margin-left: 5px !important;
}

.new-group-conversation {
	/** This next 2 rules are pretty hacky, with the modal component somehow
	the margin applied to the content is added to the total modal width,
	so here we subtract it to the width and height of the content.
	*/
	width: $dialog-width - $dialog-margin * 2;
	height: $dialog-height - $dialog-margin * 2;
	margin: $dialog-margin;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	position: relative;
}

/** Size full in the modal component doesn't have border radius, this adds
it back */
::v-deep .modal-container {
	border-radius: var(--border-radius-large) !important;
}

.navigation {
	display: flex;
	position: absolute;
	bottom: 0;
	// Same as above
	width: $dialog-width - $dialog-margin * 2;
	&__button-right {
		margin-left:auto;
	}
}
</style>
