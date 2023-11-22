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
	<NcAppSettingsDialog role="dialog"
		:aria-label="t('spreed', 'Conversation settings')"
		:title="t('spreed', 'Conversation settings')"
		:open.sync="showSettings"
		:show-navigation="true"
		:container="container">
		<!-- description -->
		<NcAppSettingsSection v-if="showDescription"
			id="description"
			:title="t('spreed', 'Description')">
			<!-- Rename to "Basic info" when Name is moved over -->
			<Description :editable="canFullModerate"
				:description="description"
				:editing="isEditingDescription"
				:loading="isDescriptionLoading"
				:placeholder="t('spreed', 'Enter a description for this conversation')"
				@submit-description="handleUpdateDescription"
				@update:editing="handleEditDescription" />
		</NcAppSettingsSection>

		<template v-if="!isBreakoutRoom">
			<!-- Notifications settings and devices preview screen -->
			<NcAppSettingsSection id="notifications"
				:title="t('spreed', 'Personal')">
				<NcCheckboxRadioSwitch :checked.sync="showDeviceChecker"
					type="switch">
					{{ t('spreed', 'Always show the device preview screen before joining a call in this conversation.') }}
				</NcCheckboxRadioSwitch>

				<NotificationsSettings v-if="!isGuest" :conversation="conversation" />
			</NcAppSettingsSection>

			<NcAppSettingsSection id="conversation-settings"
				:title="t('spreed', 'Moderation')">
				<ListableSettings v-if="canFullModerate"
					:token="token" />
				<LinkShareSettings v-if="canFullModerate"
					ref="linkShareSettings" />
				<ExpirationSettings :token="token" />
			</NcAppSettingsSection>

			<!-- Meeting: lobby and sip -->
			<NcAppSettingsSection v-if="canFullModerate"
				id="meeting"
				:title="t('spreed', 'Meeting')">
				<LobbySettings :token="token" />
				<SipSettings v-if="canUserEnableSIP" />
			</NcAppSettingsSection>

			<!-- Conversation permissions -->
			<NcAppSettingsSection v-if="canFullModerate"
				id="permissions"
				:title="t('spreed', 'Permissions')">
				<ConversationPermissionsSettings :token="token" />
			</NcAppSettingsSection>

			<!-- Breakout rooms -->
			<NcAppSettingsSection v-if="canConfigureBreakoutRooms"
				id="breakout-rooms"
				:title="t('spreed', 'Breakout Rooms')">
				<BreakoutRoomsSettings :token="token" />
			</NcAppSettingsSection>

			<!-- Matterbridge settings -->
			<NcAppSettingsSection v-if="canFullModerate && matterbridgeEnabled"
				id="matterbridge"
				:title="t('spreed', 'Matterbridge')">
				<MatterbridgeSettings />
			</NcAppSettingsSection>

			<!-- Destructive actions -->
			<NcAppSettingsSection v-if="canLeaveConversation || canDeleteConversation"
				id="dangerzone"
				:title="t('spreed', 'Danger zone')">
				<LockingSettings :token="token" />
				<DangerZone :conversation="conversation"
					:can-leave-conversation="canLeaveConversation"
					:can-delete-conversation="canDeleteConversation" />
			</NcAppSettingsSection>
		</template>
	</NcAppSettingsDialog>
</template>

<script>
import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import Description from '../Description/Description.vue'
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
import SipSettings from './SipSettings.vue'

import { PARTICIPANT, CONVERSATION } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'

export default {
	name: 'ConversationSettingsDialog',

	components: {
		NcAppSettingsDialog,
		NcAppSettingsSection,
		ExpirationSettings,
		LinkShareSettings,
		LobbySettings,
		ListableSettings,
		LockingSettings,
		SipSettings,
		MatterbridgeSettings,
		DangerZone,
		NotificationsSettings,
		Description,
		NcCheckboxRadioSwitch,
		ConversationPermissionsSettings,
		BreakoutRoomsSettings,
	},

	data() {
		return {
			showSettings: false,
			matterbridgeEnabled: loadState('spreed', 'enable_matterbridge'),
			isEditingDescription: false,
			isDescriptionLoading: false,
			showDeviceChecker: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		canUserEnableSIP() {
			return this.conversation.canEnableSIP
		},

		isGuest() {
			return this.$store.getters.getActorType() === 'guests'
		},

		token() {
			return this.$store.getters.getConversationSettingsToken()
				|| this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		participantType() {
			return this.conversation.participantType
		},

		canFullModerate() {
			return (this.participantType === PARTICIPANT.TYPE.OWNER
				|| this.participantType === PARTICIPANT.TYPE.MODERATOR)
				&& this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
				&& this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		canDeleteConversation() {
			return this.conversation.canDeleteConversation
		},

		canLeaveConversation() {
			return this.conversation.canLeaveConversation
		},

		description() {
			return this.conversation.description
		},

		showDescription() {
			if (this.canFullModerate) {
				return this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
					&& this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE_FORMER
			} else {
				return this.description !== ''
			}
		},

		isBreakoutRoom() {
			return this.conversation.objectType === 'room'
		},

		canConfigureBreakoutRooms() {
			const breakoutRoomsEnabled = getCapabilities()?.spreed?.config?.call?.['breakout-rooms'] || false
			return this.canFullModerate
				&& breakoutRoomsEnabled
				&& this.conversation.type === CONVERSATION.TYPE.GROUP
		},
	},

	watch: {
		showDeviceChecker(newValue) {
			const browserValue = newValue ? 'true' : 'false'
			BrowserStorage.setItem('showDeviceChecker' + this.token, browserValue)
		},
	},

	mounted() {
		subscribe('show-conversation-settings', this.handleShowSettings)
		subscribe('hide-conversation-settings', this.handleHideSettings)

		/**
		 * Get the deviceChecker value from the browser storage.
		 */
		this.showDeviceChecker = BrowserStorage.getItem('showDeviceChecker' + this.token) === null
			|| BrowserStorage.getItem('showDeviceChecker' + this.token) === 'true'
	},

	methods: {
		handleShowSettings({ token }) {
			this.$store.dispatch('updateConversationSettingsToken', token)
			this.showSettings = true
			this.$nextTick(() => {
				if (this.$refs.linkShareSettings) {
					this.$refs.linkShareSettings.$el.focus()
				}
			})
		},

		handleHideSettings() {
			this.showSettings = false
			this.$store.dispatch('updateConversationSettingsToken', '')
		},

		beforeDestroy() {
			unsubscribe('show-conversation-settings', this.handleShowSettings)
			unsubscribe('hide-conversation-settings', this.handleHideSettings)
		},

		async handleUpdateDescription(description) {
			this.isDescriptionLoading = true
			try {
				await this.$store.dispatch('setConversationDescription', {
					token: this.token,
					description,
				})
				this.isEditingDescription = false
			} catch (error) {
				console.error('Error while setting conversation description', error)
				showError(t('spreed', 'Error while updating conversation description'))
			}
			this.isDescriptionLoading = false
		},

		handleEditDescription(payload) {
			this.isEditingDescription = payload
		},
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
	color: var(--color-text-lighter);
	padding: 8px 0;
}

:deep(.app-settings-section__subtitle),
.app-settings-section__subtitle {
	font-weight: bold;
	font-size: var(--default-font-size);
}

:deep(.app-settings-subsection) {
	margin-top: 25px;

	&:first-child {
		margin-top: 0;
	}
}
</style>
