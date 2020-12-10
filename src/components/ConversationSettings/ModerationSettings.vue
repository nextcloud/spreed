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
	<div>
		<ListableSettings :token="token" />
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
			</div>
		</div>
		<div class="app-settings-subsection">
			<div id="moderation_settings_enable_lobby_hint" class="app-settings-section__hint">
				{{ t('spreed', 'Enabling the lobby only allows moderators to post messages.') }}
			</div>
			<div>
				<input id="moderation_settings_enable_lobby_checkbox"
					aria-describedby="moderation_settings_enable_lobby_hint"
					type="checkbox"
					class="checkbox"
					name="moderation_settings_enable_lobby_checkbox"
					:checked="hasLobbyEnabled"
					:disabled="isLobbyStateLoading"
					@change="toggleLobby">
				<label for="moderation_settings_enable_lobby_checkbox">{{ t('spreed', 'Enable lobby') }}</label>
			</div>
		</div>
		<div class="app-settings-subsection">
			<div v-if="hasLobbyEnabled" id="moderation_settings_lobby_timer_hint" class="app-settings-section__hint">
				{{ t('spreed', 'After the time limit the lobby will be automatically disabled.') }}
			</div>
			<div v-if="hasLobbyEnabled">
				<form
					:disabled="lobbyTimerFieldDisabled"
					@submit.prevent="saveLobbyTimer">
					<span class="icon-calendar-dark" />
					<div>
						<label for="moderation_settings_lobby_timer_field">{{ t('spreed', 'Meeting start time') }}</label>
					</div>
					<div>
						<DatetimePicker
							id="moderation_settings_lobby_timer_field"
							aria-describedby="moderation_settings_lobby_timer_hint"
							:value="lobbyTimer"
							:placeholder="t('spreed', 'Start time (optional)')"
							:disabled="lobbyTimerFieldDisabled"
							type="datetime"
							:input-class="['mx-input', { focusable: !lobbyTimerFieldDisabled }]"
							v-bind="dateTimePickerAttrs"
							@change="setNewLobbyTimer" />
						<button
							id="moderation_settings_lobby_timer_submit"
							:aria-label="t('spreed', 'Save meeting start time')"
							:disabled="lobbyTimerFieldDisabled"
							type="submit"
							class="icon icon-confirm-fade" />
					</div>
				</form>
			</div>
		</div>
		<SipSettings v-if="canUserEnableSIP" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { CONVERSATION, WEBINAR } from '../../constants'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import ListableSettings from './ListableSettings'
import SipSettings from './SipSettings'

export default {
	name: 'ModerationSettings',

	components: {
		DatetimePicker,
		ListableSettings,
		SipSettings,
	},

	data() {
		return {
			isReadOnlyStateLoading: false,
			isLobbyStateLoading: false,
			isLobbyTimerLoading: false,
			newLobbyTimer: null,
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

		lobbyTimerFieldDisabled() {
			return this.isLobbyStateLoading || this.isLobbyTimerLoading
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

		setNewLobbyTimer(date) {
			let timestamp = 0
			if (date) {
				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				timestamp = date.getTime() / 1000
			}

			this.newLobbyTimer = timestamp
		},

		async saveLobbyTimer() {
			this.isLobbyTimerLoading = true

			try {
				await this.$store.dispatch('setLobbyTimer', {
					token: this.token,
					timestamp: this.newLobbyTimer,
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
::v-deep .mx-input {
	margin: 0;
}
</style>
