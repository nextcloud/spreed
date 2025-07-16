<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<div class="app-settings-subsection">
			<NcNoteCard v-if="hasCall && !hasLobbyEnabled"
				type="warning"
				:text="t('spreed', 'Enabling the lobby will remove non-moderators from the ongoing call.')" />
			<NcCheckboxRadioSwitch :model-value="hasLobbyEnabled"
				type="switch"
				:disabled="isLobbyStateLoading"
				@update:model-value="toggleLobby">
				{{ t('spreed', 'Enable lobby, restricting the conversation to moderators') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div v-if="hasLobbyEnabled" class="app-settings-subsection">
			<form :disabled="lobbyTimerFieldDisabled"
				@submit.prevent="saveLobbyTimer">
				<span class="icon-calendar-dark" />
				<div>
					<label for="moderation_settings_lobby_timer_field">{{ t('spreed', 'Meeting start time') }}</label>
				</div>
				<NcDateTimePicker id="moderation_settings_lobby_timer_field"
					v-model="lobbyTimer"
					aria-describedby="moderation_settings_lobby_timer_hint"
					:default-value="defaultLobbyTimer"
					:placeholder="t('spreed', 'Start time (optional)')"
					:disabled="lobbyTimerFieldDisabled"
					type="datetime"
					value-type="timestamp"
					format="yyyy-MM-dd HH:mm"
					:minute-step="5"
					:input-class="['mx-input', { focusable: !lobbyTimerFieldDisabled }]"
					v-bind="dateTimePickerAttrs"
					confirm
					clearable />
				<div class="lobby_timer--timezone">
					{{ getTimeZone }}
				</div>
				<div v-if="showRelativeTime" class="lobby_timer--relative">
					{{ getRelativeTime }}
				</div>
			</form>
		</div>
		<div v-if="supportImportEmails" class="import-email-participants">
			<h4 class="app-settings-section__subtitle">
				{{ t('spreed', 'Import email participants') }}
			</h4>
			<div class="app-settings-section__hint">
				{{ t('spreed', 'You can import a list of email participants from a CSV file.') }}
			</div>
			<NcButton @click="isImportEmailsDialogOpen = true">
				<template #icon>
					<IconFileUploadOutline :size="20" />
				</template>
				{{ t('spreed', 'Import email participants') }}
			</NcButton>

			<ImportEmailsDialog v-if="isImportEmailsDialogOpen"
				:token="token"
				container=".import-email-participants"
				@close="isImportEmailsDialogOpen = false" />

			<template v-if="canCreatePollDrafts">
				<h4 class="app-settings-section__subtitle">
					{{ t('spreed', 'Poll drafts') }}
				</h4>
				<NcButton @click="openPollDraftHandler">
					<template #icon>
						<IconPoll :size="20" />
					</template>
					{{ t('spreed', 'Browse poll drafts') }}
				</NcButton>
			</template>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconFileUploadOutline from 'vue-material-design-icons/FileUploadOutline.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import ImportEmailsDialog from '../ImportEmailsDialog.vue'
import { WEBINAR } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { convertToUnix, futureRelativeTime, ONE_DAY_IN_MS } from '../../utils/formattedTime.ts'

export default {
	name: 'LobbySettings',

	components: {
		IconFileUploadOutline,
		IconPoll,
		ImportEmailsDialog,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePicker,
		NcNoteCard,
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
			isImportEmailsDialogOpen: false,
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

		supportImportEmails() {
			return hasTalkFeature(this.token, 'email-csv-import')
		},

		defaultLobbyTimer() {
			let date = new Date()
			// strip minutes and seconds
			date = new Date(date.getFullYear(), date.getMonth(), date.getDate(), date.getHours(), 0, 0, 0)
			// add one hour to reach the next hour
			return new Date(date.getTime() + 3600000)
		},

		lobbyTimer: {
			get() {
				// A timestamp of 0 means that there is no lobby, but it would be
				// interpreted as the Unix epoch by the DateTimePicker.
				if (this.conversation.lobbyTimer === 0) {
					return undefined
				}

				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				return this.conversation.lobbyTimer * 1000
			},

			set(value) {
				this.saveLobbyTimer(value)
			},
		},

		dateTimePickerAttrs() {
			return {
				firstDayOfWeek: window.firstDay + 1, // Provided by server
				lang: {
					days: window.dayNamesShort, // Provided by server
					months: window.monthNamesShort, // Provided by server
				},
			}
		},

		showRelativeTime() {
			return this.lobbyTimer
				&& this.lobbyTimer > Date.now()
				&& (this.lobbyTimer - Date.now()) < ONE_DAY_IN_MS // less than 24 hours
		},

		getTimeZone() {
			if (!this.lobbyTimer) {
				return ''
			}
			const date = new Date(this.lobbyTimer)
			return t('spreed', 'Start time: {date}', { date: date.toString() })
		},

		getRelativeTime() {
			return futureRelativeTime(this.lobbyTimer)
		},

		canCreatePollDrafts() {
			return hasTalkFeature(this.token, 'talk-polls-drafts')
		},
	},

	methods: {
		t,
		async toggleLobby() {
			this.isLobbyStateLoading = true
			await this.$store.dispatch('toggleLobby', {
				token: this.token,
				enableLobby: this.conversation.lobbyState !== WEBINAR.LOBBY.NON_MODERATORS,
			})
			this.isLobbyStateLoading = false
		},

		async saveLobbyTimer(timestamp) {
			this.isLobbyTimerLoading = true

			try {
				await this.$store.dispatch('setLobbyTimer', {
					token: this.token,
					timestamp: timestamp ? convertToUnix(timestamp) : 0,
				})
				showSuccess(t('spreed', 'Start time has been updated'))
			} catch (e) {
				console.error('Error occurred while updating start time', e)
				showError(t('spreed', 'Error occurred while updating start time'))
			}

			this.isLobbyTimerLoading = false
		},

		openPollDraftHandler() {
			EventBus.emit('poll-drafts-open', { token: this.token, selector: '#settings-section_meeting' })
		},
	},
}
</script>

<style lang="scss" scoped>
.lobby_timer {
	&--relative {
		color: var(--color-text-maxcontrast);
	}
	&--timezone {
		padding-top: 4px;
	}
}

:deep(.mx-input) {
	margin: 0;
}
</style>
