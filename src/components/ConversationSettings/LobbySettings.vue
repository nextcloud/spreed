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
		<div class="app-settings-subsection">
			<div id="moderation_settings_enable_lobby_hint" class="app-settings-section__hint">
				{{ t('spreed', 'Enabling the lobby only allows moderators to post messages.') }}
			</div>
			<div v-if="hasCall" class="app-settings-section__hint">
				{{ t('spreed', 'This will also remove non-moderators from the ongoing call.') }}
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
				<form :disabled="lobbyTimerFieldDisabled"
					@submit.prevent="saveLobbyTimer">
					<span class="icon-calendar-dark" />
					<div>
						<label for="moderation_settings_lobby_timer_field">{{ t('spreed', 'Meeting start time') }}</label>
					</div>
					<DatetimePicker id="moderation_settings_lobby_timer_field"
						aria-describedby="moderation_settings_lobby_timer_hint"
						:value="lobbyTimer"
						:default-value="defaultLobbyTimer"
						:placeholder="t('spreed', 'Start time (optional)')"
						:disabled="lobbyTimerFieldDisabled"
						type="datetime"
						:input-class="['mx-input', { focusable: !lobbyTimerFieldDisabled }]"
						v-bind="dateTimePickerAttrs"
						@change="saveLobbyTimer" />
				</form>
			</div>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { WEBINAR } from '../../constants'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'

export default {
	name: 'LobbySettings',

	components: {
		DatetimePicker,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	data() {
		return {
			isLobbyStateLoading: false,
			isLobbyTimerLoading: false,
		}
	},

	computed: {
		hasCall() {
			return this.conversation.hasCall
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

		lobbyTimerFieldDisabled() {
			return this.isLobbyStateLoading || this.isLobbyTimerLoading
		},

		defaultLobbyTimer() {
			let date = new Date()
			// strip minutes and seconds
			date = new Date(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), 0, 0, 0)
			// add one hour to reach the next hour
			return new Date(date.getTime() + 3600000)
		},

		lobbyTimer() {
			// A timestamp of 0 means that there is no lobby, but it would be
			// interpreted as the Unix epoch by the DateTimePicker.
			if (this.conversation.lobbyTimer === 0) {
				return undefined
			}

			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			return this.conversation.lobbyTimer * 1000
		},

		dateTimePickerAttrs() {
			return {
				format: 'YYYY-MM-DD HH:mm',
				firstDayOfWeek: window.firstDay + 1, // Provided by server
				lang: {
					days: window.dayNamesShort, // Provided by server
					months: window.monthNamesShort, // Provided by server
				},
				confirm: true,
				clearable: true,
				minuteStep: 5,
				appendToBody: true,
				valueType: 'timestamp',
			}
		},
	},

	methods: {
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

		async saveLobbyTimer(timestamp) {
			this.isLobbyTimerLoading = true

			try {
				await this.$store.dispatch('setLobbyTimer', {
					token: this.token,
					timestamp: timestamp ? (timestamp / 1000) : 0,
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
