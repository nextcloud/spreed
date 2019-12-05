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
							:conversation-name="conversationName" />
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
						{{ t('spreed', 'Create conversation') }}
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

export default {

	name: 'NewGroupConversation',

	components: {
		Modal,
		Actions,
		ActionButton,
		SetContacts,
		SetConversationName,
		SetConversationType,
	},

	data() {
		return {
			modal: false,
			page: 0,
			conversationNameInput: '',
			hint: '',
			checked: false,
		}
	},

	computed: {
		conversationName() {
			return this.conversationNameInput.trim()
		}
	},

	methods: {
		showModal() {
			this.modal = true
		},
		// Resets to the base state of the component
		closeModal() {
			this.modal = false
			this.page = 0
			this.conversationName = ''
			this.hint = ''
			this.checked = false
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
		handleCreateConversation() {
			return true
		},
		handleInput() {
			if (this.conversationName !== '') {
				this.hint = ''
			}
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
