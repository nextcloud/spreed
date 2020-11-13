<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<AppSidebar
		v-show="opened"
		id="app-sidebar"
		:title="title"
		:title-tooltip="title"
		:starred="isFavorited"
		:title-editable="canModerate && isRenamingConversation"
		:class="'active-tab-' + activeTab"
		@update:active="handleUpdateActive"
		@update:starred="onFavoriteChange"
		@update:title="handleUpdateTitle"
		@submit-title="handleSubmitTitle"
		@dismiss-editing="dismissEditing"
		@close="handleClose">
		<AppSidebarTab
			v-if="showChatInSidebar"
			id="chat"
			:order="1"
			:name="t('spreed', 'Chat')"
			icon="icon-comment">
			<ChatView :token="token" />
		</AppSidebarTab>
		<AppSidebarTab v-if="getUserId"
			id="participants"
			:order="2"
			:name="t('spreed', 'Participants')"
			icon="icon-contacts-dark">
			<ParticipantsTab
				:can-search="canSearchParticipants"
				:can-add="canAddParticipants" />
		</AppSidebarTab>
		<AppSidebarTab
			id="settings-tab"
			:order="3"
			:name="t('spreed', 'Settings')"
			icon="icon-settings">
			<SetGuestUsername
				v-if="!getUserId" />
			<CollectionList
				v-if="getUserId && conversation.token"
				:id="conversation.token"
				type="room"
				:name="conversation.displayName" />
			<div id="app-settings">
				<div id="app-settings-header">
					<button class="settings-button" @click="showSettings">
						{{ t('spreed', 'Settings') }}
					</button>
				</div>
			</div>
			<MatterbridgeSettings
				v-if="canModerate && matterbridgeEnabled" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import ChatView from '../ChatView'
import { CollectionList } from 'nextcloud-vue-collections'
import BrowserStorage from '../../services/BrowserStorage'
import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants'
import ParticipantsTab from './Participants/ParticipantsTab'
import MatterbridgeSettings from './Matterbridge/MatterbridgeSettings'
import isInLobby from '../../mixins/isInLobby'
import SetGuestUsername from '../SetGuestUsername'

export default {
	name: 'RightSidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		ChatView,
		CollectionList,
		ParticipantsTab,
		SetGuestUsername,
		MatterbridgeSettings,
	},

	mixins: [
		isInLobby,
	],

	props: {
		showChatInSidebar: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			activeTab: 'participants',
			contactsLoading: false,
			// The conversation name (while editing)
			conversationName: '',
			// Sidebar status before starting editing operation
			sidebarOpenBeforeEditingName: '',
			matterbridgeEnabled: loadState('talk', 'enable_matterbridge'),
		}
	},

	computed: {
		show() {
			return this.$store.getters.getSidebarStatus
		},
		opened() {
			return !!this.token && !this.isInLobby && this.show
		},
		token() {
			return this.$store.getters.getToken()
		},
		conversation() {
			if (this.$store.getters.conversation(this.token)) {
				return this.$store.getters.conversation(this.token)
			}
			return {
				token: '',
				displayName: '',
				isFavorite: false,
				hasPassword: false,
				type: CONVERSATION.TYPE.PUBLIC,
				lobbyState: WEBINAR.LOBBY.NONE,
				lobbyTimer: 0,
			}
		},

		getUserId() {
			return this.$store.getters.getUserId()
		},

		isFavorited() {
			if (!this.getUserId) {
				return null
			}

			return this.conversation.isFavorite
		},

		canAddParticipants() {
			return this.canFullModerate && this.canSearchParticipants
		},
		canSearchParticipants() {
			return (this.conversation.type === CONVERSATION.TYPE.GROUP
					|| this.conversation.type === CONVERSATION.TYPE.PUBLIC)
		},
		isSearching() {
			return this.searchText !== ''
		},
		participantType() {
			return this.conversation.participantType
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canModerate() {
			return this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE && (this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		/**
		 * The conversation title value passed into the AppSidebar component.
		 * @returns {string} The conversation's title.
		 */
		title() {
			if (this.isRenamingConversation) {
				return this.conversationName
			} else {
				return this.conversation.displayName
			}
		},
		isRenamingConversation() {
			return this.$store.getters.isRenamingConversation
		},
	},

	watch: {
		conversation() {
			if (!this.isRenamingConversation) {
				this.conversationName = this.conversation.displayName
			}
		},
	},

	methods: {
		handleClose() {
			this.dismissEditing()
			this.$store.dispatch('hideSidebar')
			BrowserStorage.setItem('sidebarOpen', 'false')
		},

		async onFavoriteChange() {
			this.$store.dispatch('toggleFavorite', this.conversation)
		},

		handleUpdateActive(active) {
			this.activeTab = active
		},

		/**
		 * Updates the conversationName value while editing the conversation's title.
		 * @param {string} title the conversation title emitted by the AppSidevar vue
		 * component.
		 */
		handleUpdateTitle(title) {
			this.conversationName = title
		},

		async handleSubmitTitle(event) {
			const name = event.target[0].value.trim()
			try {
				await this.$store.dispatch('setConversationName', {
					token: this.token,
					name: name,
				})
				this.dismissEditing()
			} catch (exception) {
				console.debug(exception)
			}
		},

		dismissEditing() {
			this.$store.dispatch('isRenamingConversation', false)
		},

		showSettings() {
			emit('show-settings')
		},

	},
}
</script>

<style lang="scss" scoped>

/* Override style set in server for "#app-sidebar" to match the style set in
 * nextcloud-vue for ".app-sidebar". */
#app-sidebar {
	display: flex;
}

.app-sidebar-tabs__content #tab-chat {
	/* Remove padding to maximize the space for the chat view. */
	padding: 0;
}

</style>
