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
			<ActionButton icon="icon-add" @click="showModal" />
		</Actions>
		<Modal v-if="modal" size="full" @close="closeModal">
			<div class="wrapper">
				<div class="content">
					<SetConversationName
						v-if="page === 0"
						v-model="conversationName"
						@setConversationName="handleSetConversationName"/>
					<SetContacts v-else />
				</div>
				<div class="navigation">
					<button
						v-if="page===1"
						class="navigation__button-left"
						@click="handleClickBack">
						{{t('spreed', 'Back')}}
					</button>
					<button
						v-if="page===0"
						class="navigation__button-right primary"
						@click="handleClickForward">
						{{t('spreed', 'Confirm')}}
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

export default {

	name: 'NewGroupConversation',

	components: {
		Modal,
		Actions,
		ActionButton,
		SetContacts,
		SetConversationName
	},

	data() {
		return {
			modal: false,
			page: 0,
			conversationName: '',
			private: true
		}
	},

	methods: {
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		handleSetConversationName(event) {
			console.log(event)
			this.conversationName = event
			this.page = 1
		},
		handleClickForward() {
			if (this.page === 0) {
				if (this.conversationName !== '') {
					this.page = 1
				} else {
					
				}
			}		
		},
		handleClickBack() {
			this.page = 0 
		}
	},

}

</script>

<style lang="scss" scoped>
.wrapper {
	width: 350px;
	height: 500px;
	padding: 10px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
}

.content {
}

.navigation {
	&__button-right {
		justify-self: flex-end;
	}
}
</style>
