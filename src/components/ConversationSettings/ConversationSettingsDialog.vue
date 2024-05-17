<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSettingsDialog id="conversation-settings-container"
		v-model:open="showSettings"
		:aria-label="t('spreed', 'Conversation settings')"
		:name="t('spreed', 'Conversation settings')"
		:show-navigation="true"
		:container="container">
		<NcAppSettingsSection id="basic-info"
			:name="t('spreed', 'Basic Info')">
			<BasicInfo :conversation="conversation"
				:can-full-moderate="canFullModerate" />
		</NcAppSettingsSection>

		<template v-if="!isBreakoutRoom">
			<!-- Notifications settings and devices preview screen -->
			<NcAppSettingsSection v-if="!isNoteToSelf"
				id="notifications"
				:name="t('spreed', 'Personal')">
				<NcCheckboxRadioSwitch v-if="showMediaSettingsToggle"
					type="switch"
					:disabled="recordingConsentRequired"
					:model-value="showMediaSettings"
					@update:model-value="setShowMediaSettings">
					{{ t('spreed', 'Always show the device preview screen before joining a call in this conversation.') }}
				</NcCheckboxRadioSwitch>
				<p v-if="recordingConsentRequired">
					{{ t('spreed', 'The consent to be recorded will be required for each participant before joining every call.') }}
				</p>
				<NotificationsSettings v-if="!isGuest" :conversation="conversation" />
			</NcAppSettingsSection>

			<NcAppSettingsSection id="conversation-settings"
				:name="selfIsOwnerOrModerator ? t('spreed', 'Moderation') : t('spreed', 'Setup overview')">
				<ListableSettings v-if="!isNoteToSelf && !isGuest" :token="token" :can-moderate="canFullModerate" />
				<LinkShareSettings v-if="!isNoteToSelf" :token="token" :can-moderate="canFullModerate" />
				<RecordingConsentSettings v-if="!isNoteToSelf && recordingConsentAvailable" :token="token" :can-moderate="selfIsOwnerOrModerator" />
				<ExpirationSettings :token="token" :can-moderate="selfIsOwnerOrModerator" />
			</NcAppSettingsSection>

			<!-- Meeting: lobby and sip -->
			<NcAppSettingsSection v-if="canFullModerate && !isNoteToSelf"
				id="meeting"
				:name="t('spreed', 'Meeting')">
				<LobbySettings :token="token" />
				<SipSettings v-if="canUserEnableSIP" />
			</NcAppSettingsSection>

			<!-- Conversation permissions -->
			<NcAppSettingsSection v-if="canFullModerate && !isNoteToSelf"
				id="permissions"
				:name="t('spreed', 'Permissions')">
				<ConversationPermissionsSettings :token="token" />
			</NcAppSettingsSection>

			<!-- Breakout rooms -->
			<NcAppSettingsSection v-if="canConfigureBreakoutRooms"
				id="breakout-rooms"
				:name="t('spreed', 'Breakout Rooms')">
				<BreakoutRoomsSettings :token="token" />
			</NcAppSettingsSection>

			<!-- Matterbridge settings -->
			<NcAppSettingsSection v-if="canFullModerate && matterbridgeEnabled"
				id="matterbridge"
				:name="t('spreed', 'Matterbridge')">
				<MatterbridgeSettings />
			</NcAppSettingsSection>

			<!-- Bots settings -->
			<NcAppSettingsSection v-if="selfIsOwnerOrModerator && hasBotV1API"
				id="bots"
				:name="t('spreed', 'Bots')">
				<BotsSettings :token="token" />
			</NcAppSettingsSection>

			<!-- Destructive actions -->
			<NcAppSettingsSection v-if="canLeaveConversation || canDeleteConversation"
				id="dangerzone"
				:name="t('spreed', 'Danger zone')">
				<LockingSettings v-if="canFullModerate && !isNoteToSelf" :token="token" />
				<DangerZone :conversation="conversation"
					:can-leave-conversation="canLeaveConversation"
					:can-delete-conversation="canDeleteConversation" />
			</NcAppSettingsSection>
		</template>
	</NcAppSettingsDialog>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import BasicInfo from './BasicInfo.vue'
import BotsSettings from './BotsSettings.vue'
import BreakoutRoomsSettings from './BreakoutRoomsSettings.vue'
import ConversationPermissionsSettings from './ConversationPermissionsSettings.vue'
import DangerZone from './DangerZone.vue'
import ExpirationSettings from './ExpirationSettings.vue'
import LinkShareSettings from './LinkShareSettings.vue'
import ListableSettings from './ListableSettings.vue'
import LobbySettings from './LobbySettings.vue'
import LockingSettings from './LockingSettings.vue'
import MatterbridgeSettings from './Matterbridge/MatterbridgeSettings.vue'
import NotificationsSettings from './NotificationsSettings.vue'
import RecordingConsentSettings from './RecordingConsentSettings.vue'
import SipSettings from './SipSettings.vue'

import { CALL, PARTICIPANT, CONVERSATION } from '../../constants.js'
import { useSettingsStore } from '../../stores/settings.js'

const recordingEnabled = getCapabilities()?.spreed?.config?.call?.recording || false
const recordingConsentCapability = getCapabilities()?.spreed?.features?.includes('recording-consent')
const recordingConsent = getCapabilities()?.spreed?.config?.call?.['recording-consent'] !== CALL.RECORDING_CONSENT.OFF
const supportFederationV1 = getCapabilities()?.spreed?.features?.includes('federation-v1')

export default {
	name: 'ConversationSettingsDialog',

	components: {
		BasicInfo,
		BotsSettings,
		BreakoutRoomsSettings,
		ConversationPermissionsSettings,
		DangerZone,
		ExpirationSettings,
		LinkShareSettings,
		ListableSettings,
		LobbySettings,
		LockingSettings,
		MatterbridgeSettings,
		NcAppSettingsDialog,
		NcAppSettingsSection,
		NcCheckboxRadioSwitch,
		NotificationsSettings,
		RecordingConsentSettings,
		SipSettings,
	},

	setup() {
		const settingsStore = useSettingsStore()
		return { settingsStore }
	},

	data() {
		return {
			showSettings: false,
			matterbridgeEnabled: loadState('spreed', 'enable_matterbridge'),
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		canUserEnableSIP() {
			return this.conversation.canEnableSIP
		},

		isNoteToSelf() {
			return this.conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF
		},

		isGuest() {
			return this.$store.getters.isActorGuest()
		},

		token() {
			return this.$store.getters.getConversationSettingsToken()
				|| this.$store.getters.getToken()
		},

		showMediaSettingsToggle() {
			return (!supportFederationV1 || !this.conversation.remoteServer)
		},

		showMediaSettings() {
			return this.settingsStore.getShowMediaSettings(this.token)
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		participantType() {
			return this.conversation.participantType
		},

		selfIsOwnerOrModerator() {
			return (this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR)
		},

		canFullModerate() {
			return this.selfIsOwnerOrModerator
				&& this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
				&& this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		canDeleteConversation() {
			return this.conversation.canDeleteConversation
		},

		canLeaveConversation() {
			return this.conversation.canLeaveConversation
		},

		isBreakoutRoom() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
		},

		hasBotV1API() {
			return getCapabilities()?.spreed?.features?.includes('bots-v1')
		},

		canConfigureBreakoutRooms() {
			const breakoutRoomsEnabled = getCapabilities()?.spreed?.config?.call?.['breakout-rooms'] || false
			return this.canFullModerate
				&& breakoutRoomsEnabled
				&& this.conversation.type === CONVERSATION.TYPE.GROUP
		},

		recordingConsentAvailable() {
			return recordingEnabled && recordingConsentCapability && recordingConsent
		},

		recordingConsentRequired() {
			return this.conversation.recordingConsent === CALL.RECORDING_CONSENT.REQUIRED
		}
	},

	mounted() {
		subscribe('show-conversation-settings', this.handleShowSettings)
		subscribe('hide-conversation-settings', this.handleHideSettings)

	},

	beforeUnmount() {
		unsubscribe('show-conversation-settings', this.handleShowSettings)
		unsubscribe('hide-conversation-settings', this.handleHideSettings)
	},

	methods: {
		handleShowSettings({ token }) {
			this.$store.dispatch('updateConversationSettingsToken', token)
			this.showSettings = true
		},

		handleHideSettings() {
			this.showSettings = false
			this.$store.dispatch('updateConversationSettingsToken', '')
		},

		setShowMediaSettings(newValue) {
			this.settingsStore.setShowMediaSettings(this.token, newValue)
		}
	},
}
</script>

<style lang="scss" scoped>
:deep(button.icon) {
	height: 32px;
	width: 32px;
	display: inline-block;
	margin-left: 5px;
	vertical-align: middle;
}

:deep(.modal-container) {
	display: flex !important;
}

:deep(.app-settings-section__hint) {
	color: var(--color-text-maxcontrast);
	padding: 8px 0;
}

:deep(.app-settings-section__subtitle),
.app-settings-section__subtitle {
	font-weight: bold;
	font-size: var(--default-font-size);
	margin: calc(var(--default-grid-baseline) * 4) 0 var(--default-grid-baseline) 0;
}

:deep(.app-settings-subsection) {
	margin-top: 25px;

	&:first-child {
		margin-top: 0;
	}
}
</style>
