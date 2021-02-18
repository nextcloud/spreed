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
	<AppSettingsDialog
		role="dialog"
		:aria-label="t('spreed', 'Conversation settings')"
		:open.sync="showSettings"
		:show-navigation="true">
		<!-- Notifications settings -->
		<AppSettingsSection
			:title="t('spreed', 'Chat Notifications')"
			class="app-settings-section">
			<NotificationsSettings :conversation="conversation" />
		</AppSettingsSection>
		<!-- Guest access -->
		<AppSettingsSection
			:title="t('spreed', 'Guests access')"
			class="app-settings-section">
			<LinkShareSettings ref="linkShareSettings" />
		</AppSettingsSection>
		<!-- TODO sepatate these 2 settings and rename the settings sections
		all the settings in this component are conversation settings. Proposal:
		move lock conversation in destructive actions and create a separate
		section for listablesettings -->
		<AppSettingsSection
			v-if="canFullModerate"
			:title="t('spreed', 'Conversation settings')"
			class="app-settings-section">
			<ListableSettings :token="token" />
			<LockingSettings :token="token" />
		</AppSettingsSection>
		<!-- Meeting settings -->
		<AppSettingsSection
			v-if="canFullModerate"
			:title="t('spreed', 'Meeting settings')"
			class="app-settings-section">
			<LobbySettings :token="token" />
			<SipSettings v-if="canUserEnableSIP" />
		</AppSettingsSection>
		<AppSettingsSection
			v-if="canFullModerate && matterbridgeEnabled"
			:title="t('spreed', 'Matterbridge')"
			class="app-settings-section">
			<MatterbridgeSettings />
		</AppSettingsSection>
		<!-- Destructive actions -->
		<AppSettingsSection
			v-if="canLeaveConversation || canDeleteConversation"
			:title="t('spreed', 'Danger zone')"
			class="app-settings-section">
			<DangerZone
				:conversation="conversation"
				:can-leave-conversation="canLeaveConversation"
				:can-delete-conversation="canDeleteConversation" />
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { PARTICIPANT } from '../../constants'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import LinkShareSettings from './LinkShareSettings'
import ListableSettings from './ListableSettings'
import LockingSettings from './LockingSettings'
import LobbySettings from './LobbySettings'
import SipSettings from './SipSettings'
import MatterbridgeSettings from './Matterbridge/MatterbridgeSettings'
import { loadState } from '@nextcloud/initial-state'
import DangerZone from './DangerZone'
import NotificationsSettings from './NotificationsSettings'

export default {
	name: 'ConversationSettingsDialog',

	components: {
		AppSettingsDialog,
		AppSettingsSection,
		LinkShareSettings,
		LobbySettings,
		ListableSettings,
		LockingSettings,
		SipSettings,
		MatterbridgeSettings,
		DangerZone,
		NotificationsSettings,
	},

	data() {
		return {
			showSettings: false,
			matterbridgeEnabled: loadState('spreed', 'enable_matterbridge'),
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

		participantType() {
			return this.conversation.participantType
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canDeleteConversation() {
			return this.conversation.canDeleteConversation
		},

		canLeaveConversation() {
			return this.conversation.canLeaveConversation
		},
	},

	mounted() {
		subscribe('show-conversation-settings', this.handleShowSettings)
		subscribe('hide-conversation-settings', this.handleHideSettings)
	},

	methods: {
		handleShowSettings() {
			this.showSettings = true
			this.$nextTick(() => {
				this.$refs.linkShareSettings.focus()
			})
		},

		handleHideSettings() {
			this.showSettings = false
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

::v-deep .app-settings__content {
	width: 450px;
}

::v-deep .app-settings-section__hint {
	color: var(--color-text-lighter);
	padding: 8px 0;
}

::v-deep .app-settings-subsection {
	margin-top: 25px;

	&:first-child {
		margin-top: 0;
	}
}
</style>
