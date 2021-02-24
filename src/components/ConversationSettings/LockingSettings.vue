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
		<div id="moderation_settings_lock_conversation_hint" class="app-settings-section__hint">
			{{ t('spreed', 'Locking the conversation prevents anyone to post messages or start calls.') }}
		</div>
		<div>
			<input id="moderation_settings_lock_conversation_checkbox"
				aria-describedby="moderation_settings_lock_conversation_hint"
				type="checkbox"
				class="checkbox"
				name="moderation_settings_lock_conversation_checkbox"
				:checked="isReadOnly"
				:disabled="isReadOnlyStateLoading"
				@change="toggleReadOnly">
			<label for="moderation_settings_lock_conversation_checkbox">{{ t('spreed', 'Lock conversation') }}</label>

			<h3>{{ t('spreed', 'Limit writing to a conversation') }}</h3>
			<p>
				<Multiselect id="writing_conversations"
					v-model="writingConversations"
					:options="writingConversationOptions"
					:placeholder="t('spreed', 'Limit writing to conversations')"
					label="label"
					track-by="value"
					:disabled="loading || loadingWritingConversations"
					@input="saveWritingConversations" />
			</p>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

const writingConversationOptions = [
	{ value: 0, label: t('spreed', 'Everyone can read and write') },
	{ value: 1, label: t('spreed', 'Lock conversation') },
	{ value: 2, label: t('spreed', 'Only Moderators can write') },
]

export default {
	name: 'LockingSettings',

	components: {
		Multiselect,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			isReadOnlyStateLoading: false,
			writingConversationOptions,
			writingConversations: writingConversationOptions[0],
			loadingWritingConversations: false,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},
	},

	methods: {
		async toggleReadOnly() {
			const newReadOnly = this.isReadOnly ? CONVERSATION.STATE.READ_WRITE : CONVERSATION.STATE.READ_ONLY
			this.isReadOnlyStateLoading = true
			try {
				await this.$store.dispatch('setReadOnlyState', {
					token: this.token,
					readOnly: newReadOnly,
				})
				if (newReadOnly) {
					showSuccess(t('spreed', 'You locked the conversation'))
				} else {
					showSuccess(t('spreed', 'You unlocked the conversation'))
				}
			} catch (e) {
				if (newReadOnly) {
					console.error('Error occurred when locking the conversation', e)
					showError(t('spreed', 'Error occurred when locking the conversation'))
				} else {
					console.error('Error updating read-only state', e)
					showError(t('spreed', 'Error occurred when unlocking the conversation'))
				}
			}
			this.isReadOnlyStateLoading = false
		},

		async saveWritingConversations() {
			this.loadingWritingConversations = true
			try {
				await this.$store.dispatch('setConversationState', {
					token: this.token,
					state: this.writingConversations,
				})
			} catch (e) {

			}
			this.loadingWritingConversations = false
		},
	},

}
</script>
