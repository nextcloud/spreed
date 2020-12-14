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
			<Multiselect
				v-model="listable"
				class="listable-options-select"
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
	{ value: CONVERSATION.LISTABLE.NONE, label: t('spreed', 'Visible for no one') },
	{ value: CONVERSATION.LISTABLE.USERS, label: t('spreed', 'Visible for registered users only') },
	{ value: CONVERSATION.LISTABLE.ALL, label: t('spreed', 'Visible for everyone') },
]

export default {
	name: 'ListableSettings',

	components: {
		Multiselect,
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
			isListableLoading: false,
			listableOptions,
			listable: null,
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

	methods: {
		async saveListable(listable) {
			this.$emit('input', listable.value)
			if (!this.token) {
				this.listable = listable.value
				return
			}
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
<style lang="scss" scoped>
.listable-options-select {
	width: 100%;
}
</style>
