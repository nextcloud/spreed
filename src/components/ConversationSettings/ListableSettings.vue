<!--
  - @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
  -
  - @author Vincent Petry <vincent@nextcloud.com>
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
	<div class="app-settings-subsection">
		<div id="moderation_settings_listable_conversation_hint" class="app-settings-section__hint">
			{{ t('spreed', 'Defines who can find this conversation without being invited') }}
		</div>
		<div>
			<label for="moderation_settings_listable_conversation_input">{{ t('spreed', 'Visible for') }}</label>
			<Multiselect id="moderation_settings_listable_conversation_input"
				v-model="listable"
				:options="listableOptions"
				:placeholder="t('spreed', 'Visible for')"
				label="label"
				track-by="value"
				:disabled="isListableLoading"
				aria-describedby="moderation_settings_listable_conversation_hint"
				@input="saveListable" />
		</div>
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants'

const listableOptions = [
	{ value: CONVERSATION.LISTABLE.NONE, label: t('spreed', 'No one') },
	{ value: CONVERSATION.LISTABLE.USERS, label: t('spreed', 'Registered users only') },
	{ value: CONVERSATION.LISTABLE.ALL, label: t('spreed', 'Everyone') },
]

export default {
	name: 'ListableSettings',

	components: {
		Multiselect,
	},

	data() {
		return {
			isListableLoading: false,
			listableOptions,
			listable: null,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},
	},

	mounted() {
		this.listable = this.conversation.listable
	},

	methods: {
		async saveListable(listable) {
			this.isListableLoading = true
			try {
				await this.$store.dispatch('setListable', {
					token: this.token,
					listable: listable.value,
				})

				if (listable.value === CONVERSATION.LISTABLE.NONE) {
					showSuccess(t('spreed', 'You made the conversation invisible'))
				} else if (listable.value === CONVERSATION.LISTABLE.USERS) {
					showSuccess(t('spreed', 'You made the conversation visible for registered users only'))
				} else if (listable.value === CONVERSATION.LISTABLE.ALL) {
					showSuccess(t('spreed', 'You made the conversation visible for everyone'))
				}
			} catch (e) {
				console.error('Error occurred when updating the conversation visibility', e)
				showError(t('spreed', 'Error occurred when updating the conversation visibility'))
				this.listable = this.conversation.listable
			}
			this.isListableLoading = false
		},
	},
}
</script>
