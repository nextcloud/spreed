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
			<NcCheckboxRadioSwitch :checked="listable !== LISTABLE.NONE"
				:disabled="isListableLoading"
				@update:checked="toggleListableUsers">
				{{ t('spreed', 'Open conversation to registered users') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div v-if="listable !== LISTABLE.NONE" class="indent">
			<div id="moderation_settings_listable_conversation_hint" class="app-settings-section__hint">
				{{ t('spreed', 'This conversation will be shown in search results') }}
			</div>
			<div v-if="listable !== LISTABLE.NONE && isGuestsAccountsEnabled">
				<NcCheckboxRadioSwitch :checked="listable === LISTABLE.ALL"
					:disabled="isListableLoading"
					@update:checked="toggleListableGuests">
					{{ t('spreed', 'Also open to guest app users') }}
				</NcCheckboxRadioSwitch>
			</div>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants.js'
import { loadState } from '@nextcloud/initial-state'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'ListableSettings',

	components: {
		NcCheckboxRadioSwitch,
	},

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
			isGuestsAccountsEnabled: loadState('spreed', 'guests_accounts_enabled'),
			LISTABLE: CONVERSATION.LISTABLE,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
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
		async toggleListableUsers(checked) {
			await this.saveListable(checked ? this.LISTABLE.USERS : this.LISTABLE.NONE)
		},

		async toggleListableGuests(checked) {
			await this.saveListable(checked ? this.LISTABLE.ALL : this.LISTABLE.USERS)
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
					listable,
				})

				if (this.lastNotification) {
					this.lastNotification.hideToast()
					this.lastNotification = null
				}
				if (listable === CONVERSATION.LISTABLE.NONE) {
					this.lastNotification = showSuccess(t('spreed', 'You limited the conversation to the current participants'))
				} else if (listable === CONVERSATION.LISTABLE.USERS) {
					this.lastNotification = showSuccess(t('spreed', 'You opened the conversation to registered users'))
				} else if (listable === CONVERSATION.LISTABLE.ALL) {
					this.lastNotification = showSuccess(t('spreed', 'You opened the conversation to registered and guest app users'))
				}
				this.listable = listable
			} catch (e) {
				console.error('Error occurred when opening or limiting the conversation', e)
				showError(t('spreed', 'Error occurred when opening or limiting the conversation'))
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
