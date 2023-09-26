<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcAppSidebar v-show="opened"
		:title="title"
		:title-tooltip="title"
		:starred="isFavorited"
		:active="activeTab"
		:title-editable="canModerate && isRenamingConversation"
		:class="'active-tab-' + activeTab"
		@update:active="handleUpdateActive"
		@update:starred="onFavoriteChange"
		@update:title="handleUpdateTitle"
		@submit-title="handleSubmitTitle"
		@dismiss-editing="dismissEditing"
		@closed="handleClosed"
		@close="handleClose">
		<template slot="description">
			<LobbyStatus v-if="canFullModerate && hasLobbyEnabled" :token="token" />
		</template>
		<NcAppSidebarTab v-if="isInCall"
			id="chat"
			:order="1"
			:name="t('spreed', 'Chat')">
			<template slot="icon">
				<Message :size="20" />
			</template>
			<ChatView :is-visible="opened" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="(getUserId || isModeratorOrUser) && !isOneToOne"
			id="participants"
			ref="participantsTab"
			:order="2"
			:name="participantsText">
			<template slot="icon">
				<AccountMultiple :size="20" />
			</template>
			<ParticipantsTab :is-active="activeTab === 'participants'"
				:can-search="canSearchParticipants"
				:can-add="canAddParticipants" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="showBreakoutRoomsTab"
			id="breakout-rooms"
			ref="breakout-rooms"
			:order="3"
			:name="breakoutRoomsText">
			<template slot="icon">
				<DotsCircle :size="20" />
			</template>
			<BreakoutRoomsTab :main-token="mainConversationToken"
				:main-conversation="mainConversation"
				:is-active="activeTab === 'breakout-rooms'" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="!getUserId || showSIPSettings"
			id="details-tab"
			:order="4"
			:name="t('spreed', 'Details')">
			<template slot="icon">
				<InformationOutline :size="20" />
			</template>
			<SetGuestUsername v-if="!getUserId" />
			<SipSettings v-if="showSIPSettings"
				:meeting-id="conversation.token"
				:attendee-pin="conversation.attendeePin" />
			<div v-if="!getUserId" id="app-settings">
				<div id="app-settings-header">
					<NcButton type="tertiary" @click="showSettings">
						<template #icon>
							<CogIcon :size="20" />
						</template>
						{{ t('spreed', 'Settings') }}
					</NcButton>
				</div>
			</div>
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="getUserId"
			id="shared-items"
			ref="sharedItemsTab"
			:order="5"
			:name="t('spreed', 'Shared items')">
			<template slot="icon">
				<FolderMultipleImage :size="20" />
			</template>
			<SharedItemsTab :active="activeTab === 'shared-items'" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import FolderMultipleImage from 'vue-material-design-icons/FolderMultipleImage.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import Message from 'vue-material-design-icons/Message.vue'

import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import ChatView from '../ChatView.vue'
import SetGuestUsername from '../SetGuestUsername.vue'
import BreakoutRoomsTab from './BreakoutRooms/BreakoutRoomsTab.vue'
import LobbyStatus from './LobbyStatus.vue'
import ParticipantsTab from './Participants/ParticipantsTab.vue'
import SharedItemsTab from './SharedItems/SharedItemsTab.vue'
import SipSettings from './SipSettings.vue'

import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants.js'
import isInLobby from '../../mixins/isInLobby.js'
import BrowserStorage from '../../services/BrowserStorage.js'

export default {
	name: 'RightSidebar',
	components: {
		BreakoutRoomsTab,
		ChatView,
		LobbyStatus,
		NcAppSidebar,
		NcAppSidebarTab,
		NcButton,
		ParticipantsTab,
		SetGuestUsername,
		SharedItemsTab,
		SipSettings,
		// Icons
		AccountMultiple,
		CogIcon,
		DotsCircle,
		FolderMultipleImage,
		InformationOutline,
		Message,
	},

	mixins: [
		isInLobby,
	],

	props: {
		isInCall: {
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
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		mainConversationToken() {
			if (this.conversation.objectType === 'room') {
				return this.conversation.objectId
			}
			return this.token
		},

		mainConversation() {
			return this.$store.getters.conversation(this.mainConversationToken) || this.$store.getters.dummyConversation
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
				|| (this.conversation.type === CONVERSATION.TYPE.PUBLIC && this.conversation.objectType !== 'share:password'))
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
			return !this.isOneToOne && (this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		/**
		 * The conversation title value passed into the NcAppSidebar component.
		 *
		 * @return {string} The conversation's title.
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

		showSIPSettings() {
			return this.conversation.sipEnabled === WEBINAR.SIP.ENABLED
				&& this.conversation.attendeePin
		},

		hasLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

		isOneToOne() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		participantsText() {
			const participants = this.$store.getters.participantsList(this.token)
			return t('spreed', 'Participants ({count})', { count: participants.length })
		},

		breakoutRoomsConfigured() {
			return this.conversation.breakoutRoomMode !== CONVERSATION.BREAKOUT_ROOM_MODE.NOT_CONFIGURED
		},

		showBreakoutRoomsTab() {
			return this.getUserId && !this.isOneToOne
				&& (this.breakoutRoomsConfigured || this.conversation.breakoutRoomMode === CONVERSATION.BREAKOUT_ROOM_MODE.FREE || this.conversation.objectType === 'room')
		},

		breakoutRoomsText() {
			return t('spreed', 'Breakout rooms')
		},
	},

	watch: {
		conversation(newConversation, oldConversation) {
			this.$nextTick(() => {
				if (!this.isRenamingConversation) {
					this.conversationName = this.conversation.displayName
				}

				if (newConversation.token === oldConversation.token || this.isOneToOne) {
					return
				}

				// Remain on "breakout-rooms" tab, when switching back to main room
				if (this.breakoutRoomsConfigured && this.activeTab === 'breakout-rooms') {
					return
				}

				// In other case switch to other tabs
				if (this.isInCall) {
					this.activeTab = 'chat'
				} else {
					this.activeTab = 'participants'
				}
			})
		},

		isOneToOne: {
			immediate: true,
			handler(value) {
				if (value) {
					this.activeTab = 'shared-items'
				}
			},
		},

		isInCall(newValue) {
			// Waiting for chat tab to mount / destroy
			this.$nextTick(() => {
				if (newValue) {
					// Set 'chat' tab as active, and switch to it if sidebar is open
					this.activeTab = 'chat'
					return
				}

				// If 'chat' tab wasn't active, leave it as is
				if (this.activeTab !== 'chat') {
					return
				}

				// In other case switch to other tabs
				if (!this.isOneToOne) {
					this.activeTab = 'participants'
				}
			})
		},

		token() {
			if (this.$refs.participantsTab) {
				this.$refs.participantsTab.$el.scrollTop = 0
			}
		},

		$slots() {
			console.debug('Sidebar slots changed, re rendering')
			this.$forceUpdate()
		},

		// Switch tab for guest if he is demoted from moderators
		isModeratorOrUser(newValue) {
			if (!newValue) {
				this.activeTab = 'chat'
			}
		},

	},

	mounted() {
		subscribe('spreed:select-active-sidebar-tab', this.handleUpdateActive)
	},

	beforeDestroy() {
		unsubscribe('spreed:select-active-sidebar-tab', this.handleUpdateActive)
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
		 *
		 * @param {string} title the conversation title emitted by the AppSidebar vue
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
					name,
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

		handleClosed() {
			emit('files:sidebar:closed')
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

:deep(.app-sidebar-header__description) {
	flex-direction: column;
}

.app-sidebar-tabs__content #tab-chat {
	/* Remove padding to maximize the space for the chat view. */
	padding: 0;
	height: 100%;
}

</style>
