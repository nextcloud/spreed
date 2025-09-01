<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="canModerate">
		<NcCheckboxRadioSwitch
			:model-value="listable !== LISTABLE.NONE"
			:disabled="isListableLoading"
			type="switch"
			@update:model-value="toggleListableUsers">
			{{ t('spreed', 'Open conversation to registered users, showing it in search results') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			v-if="listable !== LISTABLE.NONE && isGuestsAccountsEnabled"
			class="additional-top-margin"
			:model-value="listable === LISTABLE.ALL"
			:disabled="isListableLoading"
			type="switch"
			@update:model-value="toggleListableGuests">
			{{ t('spreed', 'Also open to users created with the Guests app') }}
		</NcCheckboxRadioSwitch>
	</div>

	<div v-else>
		<h5 class="app-settings-section__subtitle">
			{{ t('spreed', 'Open conversation') }}
		</h5>
		<p>{{ summaryLabel }}</p>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { CONVERSATION } from '../../constants.ts'

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

		canModerate: {
			type: Boolean,
			default: true,
		},

		value: {
			type: Number,
			default: null,
		},
	},

	emits: ['input'],

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

		summaryLabel() {
			switch (this.listable) {
				case CONVERSATION.LISTABLE.ALL:
					return t('spreed', 'This conversation is open to both registered users and users created with the Guests app')
				case CONVERSATION.LISTABLE.USERS:
					return t('spreed', 'This conversation is open to registered users')
				case CONVERSATION.LISTABLE.NONE:
				default:
					return t('spreed', 'This conversation is limited to the current participants')
			}
		},
	},

	watch: {
		value(value) {
			this.listable = value
		},

		conversation: {
			immediate: true,
			handler() {
				this.listable = this.conversation.listable
			},
		},
	},

	mounted() {
		if (this.token) {
			this.listable = this.value || this.conversation.listable
		} else {
			this.listable = this.value
		}
	},

	beforeUnmount() {
		if (this.lastNotification) {
			this.lastNotification.hideToast()
			this.lastNotification = null
		}
	},

	methods: {
		t,
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
					this.lastNotification = showSuccess(t('spreed', 'You opened the conversation to both registered users and users created with the Guests app'))
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
.additional-top-margin {
	margin-top: 10px;
}
</style>
