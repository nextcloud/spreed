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
		<div v-if="hasCall" class="app-settings-section__hint">
			{{ t('spreed', 'This will also terminate the ongoing call.') }}
		</div>
		<div>
			<NcCheckboxRadioSwitch :checked="isReadOnly"
				type="switch"
				aria-describedby="moderation_settings_lock_conversation_hint"
				:disabled="isReadOnlyStateLoading"
				@update:checked="toggleReadOnly">
				{{ t('spreed', 'Lock conversation') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION } from '../../constants.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'LockingSettings',

	components: {
		NcCheckboxRadioSwitch,
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
		}
	},

	computed: {
		hasCall() {
			return this.conversation.hasCall
		},

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
	},

}
</script>
