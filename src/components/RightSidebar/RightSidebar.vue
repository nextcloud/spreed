<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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
		@closed="handleClosed">
		<!-- Use a custom icon when sidebar is used for chat messages during the call -->
		<template v-if="isInCall" #toggle-icon>
			<MessageText :size="20" />
			<span v-if="unreadMessagesCounter > 0" class="chat-button-unread-marker" />
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
			<ChatView :is-visible="opened" is-sidebar />
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
		<NcAppSidebarTab v-if="showSearchMessagesTab"
			id="search-messages"
			key="search-messages"
			ref="searchMessgesTab"
			:order="6"
			:name="t('spreed', 'Search Messages')">
			<template #icon>
				<TextSearchVariant :size="20" />
			</template>
			<SearchMessagesTab :is-active="activeTab === 'search-messages'" />
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
import MessageText from 'vue-material-design-icons/MessageText.vue'
import TextSearchVariant from 'vue-material-design-icons/TextSearchVariant.vue'

import { showMessage } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import BreakoutRoomsTab from './BreakoutRooms/BreakoutRoomsTab.vue'
import LobbyStatus from './LobbyStatus.vue'
import ParticipantsTab from './Participants/ParticipantsTab.vue'
import SearchMessagesTab from './SearchMessages/SearchMessagesTab.vue'
import SharedItemsTab from './SharedItems/SharedItemsTab.vue'
import SipSettings from './SipSettings.vue'
import ChatView from '../ChatView.vue'
import SetGuestUsername from '../SetGuestUsername.vue'

import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useSidebarStore } from '../../stores/sidebar.js'

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
		SearchMessagesTab,
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
		TextSearchVariant,
	},

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		return {
			sidebarStore: useSidebarStore()
		}
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
			return this.sidebarStore.show
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

		supportFederationV1() {
			return hasTalkFeature(this.token, 'federation-v1')
		},

		showBreakoutRoomsTab() {
			return this.getUserId && !this.isOneToOne
				&& (!this.supportFederationV1 || !this.conversation.remoteServer)
				&& (this.breakoutRoomsConfigured || this.conversation.breakoutRoomMode === CONVERSATION.BREAKOUT_ROOM_MODE.FREE || this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
		},

		showParticipantsTab() {
			return (this.getUserId || this.isModeratorOrUser) && (!this.isOneToOne || this.isInCall) && !this.isNoteToSelf
		},

		showSharedItemsTab() {
			return this.getUserId && (!this.supportFederationV1 || !this.conversation.remoteServer)
		},

		showSearchMessagesTab() {
			return this.getUserId
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
		t,

		handleUpdateOpen(open) {
			if (open) {
				// In call ('Open chat') by default
				if (this.isInCall) {
					this.activeTab = 'chat'
				}
				this.sidebarStore.showSidebar()
			} else {
				this.sidebarStore.hideSidebar()
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
						this.sidebarStore.showSidebar()
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

.chat-button-unread-marker {
	position: absolute;
	top: 4px;
	right: 4px;
	width: 8px;
	height: 8px;
	border-radius: 8px;
	background-color: var(--color-primary-element);
	pointer-events: none;
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
