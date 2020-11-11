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
	<ul>
		<h3 class="app-settings-section__hint">
			{{ t('spreed', 'Locking the conversation prevents anyone to post messages or start calls.') }}
		</h3>
		<ActionCheckbox
			:checked="isReadOnly"
			:disabled="isReadOnlyStateLoading"
			@change="toggleReadOnly">
			{{ t('spreed', 'Lock conversation') }}
		</ActionCheckbox>
		<h3 class="app-settings-section__hint">
			{{ t('spreed', 'Enabling the lobby only allows moderators to post messages.') }}
		</h3>
		<ActionCheckbox
			:disabled="isLobbyStateLoading"
			:checked="hasLobbyEnabled"
			@change="toggleLobby">
			{{ t('spreed', 'Enable lobby') }}
		</ActionCheckbox>
		<h3 v-if="hasLobbyEnabled" class="app-settings-section__hint">
			{{ t('spreed', 'After the time limit the lobby will be automatically disabled.') }}
		</h3>
		<ActionInput
			v-if="hasLobbyEnabled"
			icon="icon-calendar-dark"
			type="datetime-local"
			v-bind="dateTimePickerAttrs"
			:value="lobbyTimer"
			:disabled="isLobbyTimerLoading || isLobbyStateLoading"
			@change="setLobbyTimer">
			{{ t('spreed', 'Start time (optional)') }}
		</ActionInput>
		<SipSettings v-if="canUserEnableSIP" />
	</ul>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION, WEBINAR } from '../../constants'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import SipSettings from './SipSettings'

export default {
	name: 'ModerationSettings',

	components: {
		ActionCheckbox,
		ActionInput,
		SipSettings,
	},

	data() {
		return {
			isReadOnlyStateLoading: false,
			isLobbyStateLoading: false,
			isLobbyTimerLoading: false,
		}
	},

	computed: {
		canUserEnableSIP() {
			return this.conversation.canEnableSIP
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		isReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},

		hasLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

		lobbyTimer() {
			// A timestamp of 0 means that there is no lobby, but it would be
			// interpreted as the Unix epoch by the DateTimePicker.
			if (this.conversation.lobbyTimer === 0) {
				return undefined
			}

			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			return new Date(this.conversation.lobbyTimer * 1000)
		},

		dateTimePickerAttrs() {
			return {
				format: 'YYYY-MM-DD HH:mm',
				firstDayOfWeek: window.firstDay + 1, // Provided by server
				lang: {
					days: window.dayNamesShort, // Provided by server
					months: window.monthNamesShort, // Provided by server
				},
				// Do not update the value until the confirm button has been
				// pressed. Otherwise it would not be possible to set a lobby
				// for today, because as soon as the day is selected the lobby
				// timer would be set, but as no time was set at that point the
				// lobby timer would be set to today at 00:00, which would
				// disable the lobby due to being in the past.
				confirm: true,
			}
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

		async toggleLobby() {
			const newLobbyState = this.conversation.lobbyState !== WEBINAR.LOBBY.NON_MODERATORS
			this.isLobbyStateLoading = true
			try {
				await this.$store.dispatch('toggleLobby', {
					token: this.token,
					enableLobby: newLobbyState,
				})
				if (newLobbyState) {
					showSuccess(t('spreed', 'You restricted the conversation to moderators'))
				} else {
					showSuccess(t('spreed', 'You opened the conversation to everyone'))
				}
			} catch (e) {
				if (newLobbyState) {
					console.error('Error occurred when restricting the conversation to moderator', e)
					showError(t('spreed', 'Error occurred when restricting the conversation to moderator'))
				} else {
					console.error('Error occurred when opening the conversation to everyone', e)
					showError(t('spreed', 'Error occurred when opening the conversation to everyone'))
				}
			}
			this.isLobbyStateLoading = false
		},

		async setLobbyTimer(date) {
			this.isLobbyTimerLoading = true

			let timestamp = 0
			if (date) {
				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				timestamp = date.getTime() / 1000
			}

			try {
				await this.$store.dispatch('setLobbyTimer', {
					token: this.token,
					timestamp: timestamp,
				})
				showSuccess(t('spreed', 'Start time has been updated'))
			} catch (e) {
				console.error('Error occurred while updating start time', e)
				showError(t('spreed', 'Error occurred while updating start time'))
			}

			this.isLobbyTimerLoading = false
		},
	},
}
</script>

<style lang="scss" scoped>
.app-settings-section__hint {
	color: var(--color-text-lighter);
	padding: 8px 0;
}
</style>
