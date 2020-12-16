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
		<div>
			<input id="listable_settings_registered_users_checkbox"
				aria-describedby="listable_settings_listable_conversation_hint"
				type="checkbox"
				class="checkbox"
				name="listable_settings_registered_users_checkbox"
				:checked="listable !== LISTABLE.NONE"
				:disabled="isListableLoading"
				@change="toggleListableUsers">
			<label for="listable_settings_registered_users_checkbox">{{ t('spreed', 'Make conversation accessible to registered users') }}</label>
		</div>
		<div v-if="listable !== LISTABLE.NONE" class="indent">
			<div id="moderation_settings_listable_conversation_hint" class="app-settings-section__hint">
				{{ t('spreed', 'This conversation will be shown in search results') }}
			</div>
			<div v-if="listable !== LISTABLE.NONE && isGuestAppEnabled">
				<input id="listable_settings_guestapp_users_checkbox"
					type="checkbox"
					class="checkbox"
					name="listable_settings_guestapp_users_checkbox"
					:checked="listable === LISTABLE.ALL"
					:disabled="isListableLoading"
					@change="toggleListableGuests">
				<label for="listable_settings_guestapp_users_checkbox">{{ t('spreed', 'Also make it accessible to guest app users') }}</label>
			</div>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants'

export default {
	name: 'ListableSettings',

	props: {
		token: {
			type: String,
			default: null,
		},

		value: {
			type: Number,
			default: null,
		},
	},

	data() {
		return {
			listable: null,
			isListableLoading: false,
			lastNotification: null,
			LISTABLE: CONVERSATION.LISTABLE,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isGuestAppEnabled() {
			// TODO: how ?
			return true
		},
	},

	watch: {
		value(value) {
			this.listable = value
		},
	},

	mounted() {
		if (this.token) {
			this.listable = this.value || this.conversation.listable
		} else {
			this.listable = this.value
		}
	},

	beforeDestroy() {
		if (this.lastNotification) {
			this.lastNotification.hideToast()
			this.lastNotification = null
		}
	},

	methods: {
		async toggleListableUsers(event) {
			await this.saveListable(event.target.checked ? this.LISTABLE.USERS : this.LISTABLE.NONE)
		},

		async toggleListableGuests(input) {
			await this.saveListable(event.target.checked ? this.LISTABLE.ALL : this.LISTABLE.USERS)
		},

		async saveListable(listable) {
			this.$emit('input', listable)
			if (!this.token) {
				this.listable = listable
				return
			}
			this.isListableLoading = true
			try {
				await this.$store.dispatch('setListable', {
					token: this.token,
					listable: listable,
				})

				if (this.lastNotification) {
					this.lastNotification.hideToast()
					this.lastNotification = null
				}
				if (listable === CONVERSATION.LISTABLE.NONE) {
					this.lastNotification = showSuccess(t('spreed', 'You made the conversation accessible to participants'))
				} else if (listable === CONVERSATION.LISTABLE.USERS) {
					this.lastNotification = showSuccess(t('spreed', 'You made the conversation accessible to registered users only'))
				} else if (listable === CONVERSATION.LISTABLE.ALL) {
					this.lastNotification = showSuccess(t('spreed', 'You made the conversation accessible to everyone'))
				}
				this.listable = listable
			} catch (e) {
				console.error('Error occurred when updating the conversation accessibility', e)
				showError(t('spreed', 'Error occurred when updating the conversation accessibility'))
				this.listable = this.conversation.listable
			}
			this.isListableLoading = false
		},
	},

}
</script>
<style lang="scss" scoped>
.listable-options-select {
	width: 100%;
}

.indent {
	margin-left: 26px;
}
</style>
