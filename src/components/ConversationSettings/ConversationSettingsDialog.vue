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
	<AppSettingsDialog :open.sync="showSettings" :show-navigation="true" first-selected-section="keyboard shortcuts">
		<AppSettingsSection :title="t('spreed', 'Guests access')"
			class="app-settings-section">
			<LinkShareSettings />
		</AppSettingsSection>
		<AppSettingsSection
			v-if="canFullModerate"
			:title="t('spreed', 'Moderation settings')"
			class="app-settings-section">
			<ModerationSettings />
		</AppSettingsSection>
	</AppSettingsDialog>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { PARTICIPANT } from '../../constants'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/AppSettingsDialog'
import AppSettingsSection from '@nextcloud/vue/dist/Components/AppSettingsSection'
import LinkShareSettings from './LinkShareSettings'
import ModerationSettings from './ModerationSettings'

export default {
	name: 'ConversationSettingsDialog',

	components: {
		AppSettingsDialog,
		AppSettingsSection,
		LinkShareSettings,
		ModerationSettings,
	},

	data() {
		return {
			showSettings: false,
		}
	},

	computed: {
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
	},

	mounted() {
		subscribe('show-conversation-settings', this.handleShowSettings)
	},

	methods: {
		handleShowSettings() {
			this.showSettings = true
		},

		beforeDestroy() {
			unsubscribe('show-conversation-settings', this.handleShowSettings)
		},
	},
}
</script>
