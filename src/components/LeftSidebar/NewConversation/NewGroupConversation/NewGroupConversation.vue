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
		<Actions>
			<ActionButton
				class="toggle"
				icon="icon-add"
				@click="showModal" />
		</Actions>
		<Modal
			v-if="modal"
			size="full"
			@close="closeModal">
			<div
				class="new-group-conversation">
				<div
					class="new-group-conversation__content">
					<template
						v-if="page === 0">
						<SetConversationName
							v-model="conversationNameInput"
							@input="handleInput"
							@setConversationName="handleSetConversationName" />
						<p
							v-if="hint !== ''"
							class="warning">
							{{ hint }}
						</p>
						<SetConversationType
							v-model="checked"
							:conversation-name="conversationName" />
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
							:success="success" />
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
						@click="handleClickForward">
						{{ t('spreed', 'Next') }}
					</button>
					<button
						v-if="page===1"
						class="navigation__button-right primary"
						@click="handleCreateConversation">
						{{ t('spreed', 'Add participants') }}
					</button>
					<button
						v-if="page===2 && this.error"
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
import SetContacts from './SetContacts/SetContacts'
import SetConversationName from './SetConversationName/SetConversationName'
import SetConversationType from './SetConversationType/SetConversationType'
import Confirmation from './Confirmation/Confirmation'
import { addParticipant } from '../../../../services/participantsService'
import {
	createPublicConversation,
	createPrivateConversation,
} from '../../../../services/conversationsService'

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
	},

	data() {
		return {
			modal: false,
			page: 0,
			conversationNameInput: '',
			hint: '',
			checked: false,
			isLoading: true,
			token: '',
			selectedParticipants: [],
			success: false,
			error: false,
		}
	},

	computed: {
		conversationName() {
			return this.conversationNameInput.trim()
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
			this.hint = ''
			this.checked = false
			this.isLoading = true
			this.token = ''
			this.selectedParticipants = []
			this.success = false
			this.error = false
		},
		handleSetConversationName(event) {
			this.page = 1
		},
		handleClickForward() {
			if (this.page === 0) {
				if (this.conversationName !== '') {
					this.page = 1
					this.hint = ''
				} else if (this.conversationName === '') {
					this.hint = t('spreed', 'Please enter a valid conversation name')
				}
			}
		},
		handleClickBack() {
			this.page = 0
		},

		handleInput() {
			if (this.conversationName !== '') {
				this.hint = ''
			}
		},

		handleUpdateSelectedParticipants(e) {
			console.debug(e)
			this.selectedParticipants = e
		},

		async handleCreateConversation() {
			this.page = 2
			if (this.checked) {
				try {
					await this.createPublicConversation()
				} catch (exeption) {
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exeptions.
					return
				}
			} else {
				try {
					await this.createPrivateConversation()
				} catch (exeption) {
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exeptions.
					return
				}
			}
			for (const participant of this.selectedParticipants) {
				try {
					await addParticipant(this.token, participant.id, participant.source)
				} catch (exeption) {
					console.debug(exeption)
					this.isLoading = false
					this.error = true
					// Stop the execution of the method on exeptions.
					return
				}
			}
			this.success = true
			// This displays the checkmark for a little while.
			await setTimeout(() => this.closeModal(), 200)
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
		pushNewRoute(token) {
			this.$router.push({ name: 'conversation', params: { token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},
	},

}

</script>

<style lang="scss" scoped>

.toggle {
	margin-left: 5px !important;
}

.new-group-conversation {
	width: 300px;
	height: 400px;
	padding: 20px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	&__content {
		margin-bottom: 20px;
	}
}
.hint{
	color: var(--color-)
}
.navigation {
	display: flex;
	&__button-right {
		margin-left:auto;
	}
}
</style>
