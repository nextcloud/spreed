<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license AGPL-3.0-or-later
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
	<NcAppSidebar v-if="isSidebarAvailable"
		:open="opened"
		:name="conversation.displayName"
		:title="conversation.displayName"
		:active.sync="activeTab"
		:class="'active-tab-' + activeTab"
		:toggle-classes="{ 'chat-button-sidebar-toggle': isInCall }"
		:toggle-attrs="isInCall ? inCallToggleAttrs : undefined"
		@update:open="handleUpdateOpen"
		@update:active="handleUpdateActive"
		@closed="handleClosed"
		@close="handleClose">
		<!-- Use a custom icon when sidebar is used for chat messages during the call -->
		<template #toggle-icon>
			<template v-if="isInCall">
				<MessageText :size="20" />
				<NcCounterBubble v-if="unreadMessagesCounter > 0"
					class="chat-button__unread-messages-counter"
					:type="hasUnreadMentions ? 'highlighted' : 'outlined'">
					{{ unreadMessagesCounter }}
				</NcCounterBubble>
			</template>
			<template v-else>
				<!-- Use the old icon on older versions -->
				<MenuIcon :size="20" />
			</template>
		</template>
		<template #description>
			<LobbyStatus v-if="canFullModerate && hasLobbyEnabled" :token="token" />
		</template>
		<NcAppSidebarTab v-if="isInCall"
			id="chat"
			key="chat"
			:order="1"
			:name="t('spreed', 'Chat')">
			<template #icon>
				<Message :size="20" />
			</template>
			<ChatView :is-visible="opened" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="showParticipantsTab"
			id="participants"
			key="participants"
			ref="participantsTab"
			:order="2"
			:name="participantsText">
			<template #icon>
				<AccountMultiple :size="20" />
			</template>
			<ParticipantsTab :is-active="activeTab === 'participants'"
				:can-search="canSearchParticipants"
				:can-add="canAddParticipants" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="showBreakoutRoomsTab"
			id="breakout-rooms"
			key="breakout-rooms"
			ref="breakout-rooms"
			:order="3"
			:name="breakoutRoomsText">
			<template #icon>
				<DotsCircle :size="20" />
			</template>
			<BreakoutRoomsTab :main-token="mainConversationToken"
				:main-conversation="mainConversation"
				:is-active="activeTab === 'breakout-rooms'" />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="showDetailsTab"
			id="details-tab"
			key="details-tab"
			:order="4"
			:name="t('spreed', 'Details')">
			<template #icon>
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
		<NcAppSidebarTab v-if="showSharedItemsTab"
			id="shared-items"
			key="shared-items"
			ref="sharedItemsTab"
			:order="5"
			:name="t('spreed', 'Shared items')">
			<template #icon>
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
import MenuIcon from 'vue-material-design-icons/Menu.vue'
import Message from 'vue-material-design-icons/Message.vue'
import MessageText from 'vue-material-design-icons/MessageText.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showMessage } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'

import BreakoutRoomsTab from './BreakoutRooms/BreakoutRoomsTab.vue'
import LobbyStatus from './LobbyStatus.vue'
import ParticipantsTab from './Participants/ParticipantsTab.vue'
import SharedItemsTab from './SharedItems/SharedItemsTab.vue'
import SipSettings from './SipSettings.vue'
import ChatView from '../ChatView.vue'
import SetGuestUsername from '../SetGuestUsername.vue'

import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'

const supportFederationV1 = getCapabilities()?.spreed?.features?.includes('federation-v1')

export default {
	name: 'RightSidebar',
	components: {
		BreakoutRoomsTab,
		ChatView,
		LobbyStatus,
		NcAppSidebar,
		NcAppSidebarTab,
		NcButton,
		NcCounterBubble,
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
		MessageText,
		MenuIcon,
	},

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
			unreadNotificationHandle: null,
		}
	},

	computed: {
		isSidebarAvailable() {
			return this.token && !this.isInLobby
		},
		show() {
			return this.$store.getters.getSidebarStatus
		},
		opened() {
			return this.isSidebarAvailable && this.show
		},
		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		mainConversationToken() {
			if (this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM) {
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

		canAddParticipants() {
			return this.canFullModerate && this.canSearchParticipants
		},

		canSearchParticipants() {
			return (this.conversation.type === CONVERSATION.TYPE.GROUP
				|| (this.conversation.type === CONVERSATION.TYPE.PUBLIC && this.conversation.objectType !== CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION))
		},

		participantType() {
			return this.conversation.participantType
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		isModeratorOrUser() {
			return this.$store.getters.isModeratorOrUser
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		showSIPSettings() {
			return this.conversation.sipEnabled !== WEBINAR.SIP.DISABLED
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
				&& (!supportFederationV1 || !this.conversation.remoteServer)
				&& (this.breakoutRoomsConfigured || this.conversation.breakoutRoomMode === CONVERSATION.BREAKOUT_ROOM_MODE.FREE || this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
		},

		showParticipantsTab() {
			return (this.getUserId || this.isModeratorOrUser) && (!this.isOneToOne || this.isInCall) && !this.isNoteToSelf
		},

		showSharedItemsTab() {
			return this.getUserId && (!supportFederationV1 || !this.conversation.remoteServer)
		},

		showDetailsTab() {
			return !this.getUserId || this.showSIPSettings
		},

		isNoteToSelf() {
			return this.conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF
		},

		breakoutRoomsText() {
			return t('spreed', 'Breakout rooms')
		},

		unreadMessagesCounter() {
			return this.conversation.unreadMessages
		},
		hasUnreadMentions() {
			return this.conversation.unreadMention
		},

		inCallToggleAttrs() {
			return {
				'data-theme-dark': true,
				'aria-label': t('spreed', 'Open chat'),
				title: t('spreed', 'Open chat')
			}
		},
	},

	watch: {
		conversation(newConversation, oldConversation) {
			if (newConversation.token === oldConversation.token || !this.showParticipantsTab) {
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
		},

		showParticipantsTab: {
			immediate: true,
			handler(value) {
				if (!value) {
					this.activeTab = 'shared-items'
				}
			},
		},

		unreadMessagesCounter(newValue, oldValue) {
			if (!this.isInCall || this.opened) {
				return
			}

			// new messages arrived
			if (newValue > 0 && oldValue === 0 && !this.hasUnreadMentions) {
				this.notifyUnreadMessages(t('spreed', 'You have new unread messages in the chat.'))
			}
		},

		hasUnreadMentions(newValue) {
			if (!this.isInCall || this.opened) {
				return
			}

			if (newValue) {
				this.notifyUnreadMessages(t('spreed', 'You have been mentioned in the chat.'))
			}
		},

		isInCall(newValue) {
			if (newValue) {
				// Set 'chat' tab as active, and switch to it if sidebar is open
				this.activeTab = 'chat'
				return
			}

			// discard notification if the call ends
			this.notifyUnreadMessages(null)

			// If 'chat' tab wasn't active, leave it as is
			if (this.activeTab !== 'chat') {
				return
			}

			// In other case switch to other tabs
			if (!this.isOneToOne) {
				this.activeTab = 'participants'
			}
		},

		token() {
			if (this.$refs.participantsTab) {
				this.$refs.participantsTab.$el.scrollTop = 0
			}

			// Discard notification if the conversation changes or closed
			this.notifyUnreadMessages(null)
		},

		isModeratorOrUser(newValue) {
			if (newValue) {
				// Fetch participants list if guest was promoted to moderators
				this.$nextTick(() => {
					emit('guest-promoted', { token: this.token })
				})
			} else {
				// Switch active tab to chat if guest was demoted from moderators
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
		openSidebar() {
			// In call by default open on chat
			if (this.isInCall) {
				this.activeTab = 'chat'
			}
			this.$store.dispatch('showSidebar')
			BrowserStorage.setItem('sidebarOpen', 'true')
		},

		handleClose() {
			this.$store.dispatch('hideSidebar')
			BrowserStorage.setItem('sidebarOpen', 'false')
		},

		handleUpdateOpen(open) {
			if (open) {
				this.openSidebar()
			} else {
				this.handleClose()
			}
		},

		handleUpdateActive(active) {
			this.activeTab = active
		},

		showSettings() {
			emit('show-settings', {})
		},

		handleClosed() {
			emit('files:sidebar:closed', {})
		},

		notifyUnreadMessages(message) {
			if (this.unreadNotificationHandle) {
				this.unreadNotificationHandle.hideToast()
				this.unreadNotificationHandle = null
			}
			if (message) {
				this.unreadNotificationHandle = showMessage(message, {
					onClick: () => {
						this.activeTab = 'chat'
						this.openSidebar()
					},
				})
			}
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

// FIXME upstream: move styles to nextcloud-vue library
:deep(.app-sidebar-tabs__nav) {
	padding: 0 10px;

	.checkbox-radio-switch__label {
		text-align: center;
		justify-content: flex-start;
	}

	.checkbox-radio-switch__icon {
		flex-basis: auto;

		span {
			margin: 0;
		}
	}
}

.app-sidebar-tabs__content #tab-chat {
	/* Remove padding to maximize the space for the chat view. */
	padding: 0;
	height: 100%;
}

.chat-button__unread-messages-counter {
	position: absolute;
	bottom: 2px;
	right: 2px;
	pointer-events: none;

	&.counter-bubble__counter--highlighted {
		color: var(--color-primary-text);
	}
}
</style>

<style lang="scss">
/*
 * NcAppSidebar toggle it rendered on the page outside the sidebar element, so we need global styles here.
 * It is _quite_ safe, as chat-button-sidebar-toggle class is defined here manually, not an internal class.
 */
.chat-button-sidebar-toggle {
	position: relative;
	// Allow unread counter to overflow rounded button
	overflow: visible !important;
}
</style>
