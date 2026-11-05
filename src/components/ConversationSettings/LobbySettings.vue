<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<div class="app-settings-subsection">
			<NcNoteCard
				v-if="hasCall && !hasLobbyEnabled"
				type="warning"
				:text="t('spreed', 'Enabling the lobby will remove non-moderators from the ongoing call.')" />
			<NcCheckboxRadioSwitch
				:modelValue="hasLobbyEnabled"
				type="switch"
				:disabled="isLobbyStateLoading"
				@update:modelValue="toggleLobby">
				{{ t('spreed', 'Enable lobby, restricting the conversation to moderators') }}
			</NcCheckboxRadioSwitch>
		</div>
		<div v-if="hasLobbyEnabled" class="app-settings-subsection">
			<form @submit.prevent="saveLobbyTimer(editedLobbyTimer)">
				<div class="lobby_timer--row">
					<NcDateTimePickerNative
						id="moderation_settings_lobby_timer_field"
						v-model="editedLobbyTimer"
						class="lobby_timer--picker"
						:disabled="lobbyTimerFieldDisabled"
						type="datetime-local"
						:min="new Date()"
						:label="t('spreed', 'Meeting start time')"
						clearable
						@trailingButtonClick="lobbyTimer && saveLobbyTimer(null)" />
					<NcButton
						type="submit"
						variant="primary"
						:disabled="lobbyTimerFieldDisabled || !isLobbyTimerDirty">
						{{ t('spreed', 'Save') }}
					</NcButton>
				</div>
				<div class="lobby_timer--timezone">
					{{ getTimeZone }}
				</div>
				<div v-if="showRelativeTime" class="lobby_timer--relative">
					{{ getRelativeTime }}
				</div>
			</form>
		</div>
		<div v-if="supportImportEmails && !isClassified" class="import-email-participants">
			<h4 class="app-settings-section__subtitle">
				{{ t('spreed', 'Import email participants') }}
			</h4>
			<div class="app-settings-section__hint">
				{{ t('spreed', 'You can import a list of email participants from a CSV file.') }}
			</div>
			<NcButton @click="isImportEmailsDialogOpen = true">
				<template #icon>
					<NcIconSvgWrapper :svg="IconFileUpload" :size="20" />
				</template>
				{{ t('spreed', 'Import email participants') }}
			</NcButton>

			<ImportEmailsDialog
				v-if="isImportEmailsDialogOpen"
				:token="token"
				container=".import-email-participants"
				@close="isImportEmailsDialogOpen = false" />
		</div>

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
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import ImportEmailsDialog from '../ImportEmailsDialog.vue'
import IconFileUpload from '../../../img/material-icons/file-upload.svg?raw'
import { WEBINAR } from '../../constants.ts'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { EventBus } from '../../services/EventBus.ts'
import { isClassifiedConversation } from '../../utils/conversation.ts'
import { convertToUnix, futureRelativeTime, ONE_DAY_IN_MS } from '../../utils/formattedTime.ts'

export default {
	name: 'LobbySettings',

	components: {
		NcIconSvgWrapper,
		IconPoll,
		ImportEmailsDialog,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDateTimePickerNative,
		NcNoteCard,
	},

	props: {
		token: {
			type: String,
			default: null,
		},
	},

	setup() {
		return {
			IconFileUpload,
		}
	},

	data() {
		return {
			isLobbyStateLoading: false,
			isLobbyTimerLoading: false,
			isImportEmailsDialogOpen: false,
			// Local buffer so typing doesn't save on every keystroke; committed by the Save button
			editedLobbyTimer: null,
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

		isClassified() {
			return isClassifiedConversation(this.conversation)
		},

		lobbyTimerFieldDisabled() {
			return this.isLobbyStateLoading || this.isLobbyTimerLoading
		},

		supportImportEmails() {
			return hasTalkFeature(this.token, 'email-csv-import')
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

		isLobbyTimerDirty() {
			const edited = this.editedLobbyTimer ? this.editedLobbyTimer.getTime() : 0
			const saved = this.lobbyTimer || 0
			return edited !== saved
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

	watch: {
		lobbyTimer: {
			immediate: true,
			handler(value) {
				this.editedLobbyTimer = value ? new Date(value) : null
			},
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
	&--row {
		display: flex;
		align-items: flex-end;
		gap: var(--default-grid-baseline);
		flex-wrap: wrap;
	}
	&--picker {
		max-width: 260px;
	}
	&--relative {
		color: var(--color-text-maxcontrast);
	}
	&--timezone {
		padding-top: 4px;
	}
}
</style>
