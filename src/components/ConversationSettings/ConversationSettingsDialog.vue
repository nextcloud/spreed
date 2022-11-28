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
		<!-- Conversation bassic settings -->
		<NcAppSettingsSection v-if="showBasicSettings"
			id="basic-settings"
			:title="t('spreed', 'Basic settings')">
			<BasicSettings :token="token"
				:conversation="conversation" />
		</NcAppSettingsSection>

		<!-- Notifications settings and devices preview screen -->
		<NcAppSettingsSection id="notifications"
			:title="t('spreed', 'Personal')">
			<NcCheckboxRadioSwitch :checked.sync="showDeviceChecker"
				type="switch">
				{{ t('spreed', 'Always show the device preview screen before joining a call in this conversation.') }}
			</NcCheckboxRadioSwitch>

			<NotificationsSettings :conversation="conversation" />
		</NcAppSettingsSection>

		<NcAppSettingsSection id="conversation-settings"
			:title="t('spreed', 'Moderation')">
			<ListableSettings v-if="canFullModerate"
				:token="token" />
			<LinkShareSettings v-if="canFullModerate"
				ref="linkShareSettings" />
			<ExpirationSettings :token="token" />
		</NcAppSettingsSection>

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
	</NcAppSettingsDialog>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { PARTICIPANT, CONVERSATION } from '../../constants.js'
import NcAppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import NcAppSettingsSection from '@nextcloud/vue/dist/Components/NcAppSettingsSection.js'
import ExpirationSettings from './ExpirationSettings.vue'
import LinkShareSettings from './LinkShareSettings.vue'
import ListableSettings from './ListableSettings.vue'
import LockingSettings from './LockingSettings.vue'
import LobbySettings from './LobbySettings.vue'
import SipSettings from './SipSettings.vue'
import MatterbridgeSettings from './Matterbridge/MatterbridgeSettings.vue'
import { loadState } from '@nextcloud/initial-state'
import DangerZone from './DangerZone.vue'
import NotificationsSettings from './NotificationsSettings.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import ConversationPermissionsSettings from './ConversationPermissionsSettings.vue'
import BasicSettings from './BasicSettings.vue'

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
		NcCheckboxRadioSwitch,
		ConversationPermissionsSettings,
		BasicSettings,
	},

	data() {
		return {
			showSettings: false,
			matterbridgeEnabled: loadState('spreed', 'enable_matterbridge'),
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
		},

		canDeleteConversation() {
			return !!this.conversation.canDeleteConversation
		},

		canLeaveConversation() {
			return !!this.conversation.canLeaveConversation
		},

		description() {
			return this.conversation.description
		},

		showBasicSettings() {
			if (this.canFullModerate) {
				return this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
			} else {
				return this.description !== ''
			}
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
		 * Get the deviceChecker value from the browserstorage.
		 */
		this.showDeviceChecker = BrowserStorage.getItem('showDeviceChecker' + this.token) === null
			|| BrowserStorage.getItem('showDeviceChecker' + this.token) === 'true'
	},

	methods: {
		handleShowSettings({ token }) {
			this.$store.dispatch('updateConversationSettingsToken', token)
			this.showSettings = true
			this.$nextTick(() => {
				this.$refs.linkShareSettings.$el.focus()
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
	},
}
</script>

<style lang="scss" scoped>

::v-deep button.icon {
	height: 32px;
	width: 32px;
	display: inline-block;
	margin-left: 5px;
	vertical-align: middle;
}

::v-deep .modal-container {
	display: flex !important;
}

::v-deep .app-settings-section__hint {
	color: var(--color-text-lighter);
	padding: 8px 0;
}

::v-deep .app-settings-section__subtitle,
.app-settings-section__subtitle {
	font-weight: bold;
	font-size: var(--default-font-size);
}

::v-deep .app-settings-subsection {
	margin-top: 25px;

	&:first-child {
		margin-top: 0;
	}
}

::v-deep .input-field__input {
	&:disabled {
		cursor: default;
	}
}
</style>
