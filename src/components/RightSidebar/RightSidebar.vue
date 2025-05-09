<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar v-if="isSidebarAvailable"
		ref="sidebar"
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
			<IconMessageText :size="20" />
			<span v-if="unreadMessagesCounter > 0" class="chat-button-unread-marker" />
		</template>
		<template #info>
			<RightSidebarContent :is-user="!!getUserId"
				:mode="CONTENT_MODES[contentModeIndex]"
				:state="showSearchMessagesTab ? 'search' : 'default'"
				@update:mode="handleUpdateMode"
				@update:search="handleShowSearch" />
		</template>
		<template #description>
			<InternalSignalingHint />
			<LobbyStatus v-if="canFullModerate && hasLobbyEnabled" :token="token" />
		</template>
		<NcAppSidebarTab v-if="showSearchMessagesTab"
			id="search-messages"
			key="search-messages"
			:order="0"
			:name="t('spreed', 'Search messages')">
			<SearchMessagesTab :is-active="activeTab === 'search-messages'"
				@close="handleShowSearch(false)" />
		</NcAppSidebarTab>
		<template v-else>
			<NcAppSidebarTab v-if="isInCall"
				id="chat"
				key="chat"
				:order="1"
				:name="t('spreed', 'Chat')">
				<template #icon>
					<IconMessage :size="20" />
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
					<IconAccountMultiple :size="20" />
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
					<IconDotsCircle :size="20" />
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
					<IconInformationOutline :size="20" />
				</template>
				<SetGuestUsername v-if="!getUserId" />
				<SipSettings v-if="showSIPSettings" :conversation="conversation" />
				<div v-if="!getUserId" id="app-settings">
					<div id="app-settings-header">
						<NcButton type="tertiary" @click="showSettings">
							<template #icon>
								<IconCog :size="20" />
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
					<IconFolderMultipleImage :size="20" />
				</template>
				<SharedItemsTab :active="activeTab === 'shared-items'" />
			</NcAppSidebarTab>
		</template>
	</NcAppSidebar>
</template>

<script>
import { useEventListener } from '@vueuse/core'
import { ref } from 'vue'

import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconCog from 'vue-material-design-icons/Cog.vue'
import IconDotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import IconFolderMultipleImage from 'vue-material-design-icons/FolderMultipleImage.vue'
import IconInformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import IconMessage from 'vue-material-design-icons/Message.vue'
import IconMessageText from 'vue-material-design-icons/MessageText.vue'

import { showMessage } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'

import BreakoutRoomsTab from './BreakoutRooms/BreakoutRoomsTab.vue'
import InternalSignalingHint from './InternalSignalingHint.vue'
import LobbyStatus from './LobbyStatus.vue'
import ParticipantsTab from './Participants/ParticipantsTab.vue'
import RightSidebarContent from './RightSidebarContent.vue'
import SearchMessagesTab from './SearchMessages/SearchMessagesTab.vue'
import SharedItemsTab from './SharedItems/SharedItemsTab.vue'
import SipSettings from './SipSettings.vue'
import ChatView from '../ChatView.vue'
import SetGuestUsername from '../SetGuestUsername.vue'

import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useSidebarStore } from '../../stores/sidebar.ts'

const supportConversationCreationAll = hasTalkFeature('local', 'conversation-creation-all')

const CONTENT_MODES = ['compact', 'preview', 'full']

export default {
	name: 'RightSidebar',
	components: {
		BreakoutRoomsTab,
		ChatView,
		InternalSignalingHint,
		LobbyStatus,
		NcAppSidebar,
		NcAppSidebarTab,
		NcButton,
		ParticipantsTab,
		RightSidebarContent,
		SearchMessagesTab,
		SetGuestUsername,
		SharedItemsTab,
		SipSettings,
		// Icons
		IconAccountMultiple,
		IconCog,
		IconDotsCircle,
		IconFolderMultipleImage,
		IconInformationOutline,
		IconMessage,
		IconMessageText,
	},

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const sidebar = ref(null)
		const contentModeIndex = ref(parseInt(BrowserStorage.getItem('sidebarContentMode') ?? 1, 10))

		let throttleTimeout = null
		const throttleHandleWheelEvent = (event) => {
			if (!throttleTimeout) {
				handleWheelEvent(event)
				throttleTimeout = setTimeout(() => {
					// do something
					clearTimeout(throttleTimeout)
					throttleTimeout = null
				}, 300 /* var(--animation-slow) */)
			}
		}

		useEventListener(sidebar, 'wheel', throttleHandleWheelEvent, { capture: true })

		/**
		 * Listen to wheel event on sidebar to switch between header info appearances
		 * @param {Event} event Wheel event
		 */
		function handleWheelEvent(event) {
			// [1]: scrolling up; [-1]: scrolling down
			const direction = event.deltaY < 0 ? 1 : -1

			if (!CONTENT_MODES[contentModeIndex.value + direction]) {
				// Already at the edge state
				return
			}

			if (direction === -1) {
				// Shrink before scrolling other content (block following scroll events)
				event.preventDefault()
			} else {
				let target = event.target
				while (target !== sidebar.value?.$el) {
					if (target.scrollTop !== 0) {
						// Expand only if other content is scrolled to the top
						return
					}
					target = target.parentNode
				}
			}

			contentModeIndex.value += direction
		}

		return {
			CONTENT_MODES,
			contentModeIndex,
			sidebar,
			sidebarStore: useSidebarStore()
		}
	},

	data() {
		return {
			activeTab: 'participants',
			contactsLoading: false,
			unreadNotificationHandle: null,
			showSearchMessagesTab: false,
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
			return this.conversation.type === CONVERSATION.TYPE.GROUP
				|| (this.conversation.type === CONVERSATION.TYPE.PUBLIC && this.conversation.objectType !== CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION)
				|| (this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && supportConversationCreationAll)
		},

		canFullModerate() {
			return this.conversation.participantType === PARTICIPANT.TYPE.OWNER
				|| this.conversation.participantType === PARTICIPANT.TYPE.MODERATOR
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
				&& !this.conversation.remoteServer // no breakout rooms support in federated conversations
				&& (this.breakoutRoomsConfigured || this.conversation.breakoutRoomMode === CONVERSATION.BREAKOUT_ROOM_MODE.FREE || this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
		},

		showParticipantsTab() {
			return (this.getUserId || this.isModeratorOrUser) && (!this.isOneToOne || this.isInCall) && !this.isNoteToSelf
		},

		showSharedItemsTab() {
			return this.getUserId && !this.conversation.remoteServer // no attachments support in federated conversations
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

			// FIXME collapse for group conversations until we show anything useful there
			this.contentModeIndex = this.isOneToOne ? 1 : 0
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

		contentModeIndex(value) {
			BrowserStorage.setItem('sidebarContentMode', value)
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

		handleUpdateMode(mode) {
			const newModeIndex = CONTENT_MODES.findIndex((m) => m === mode)
			if (newModeIndex !== -1) {
				this.contentModeIndex = newModeIndex
			}
		},

		handleShowSearch(value) {
			this.showSearchMessagesTab = value
			// FIXME upstream: NcAppSidebar should emit update:active
			if (value) {
				this.activeTab = 'search-messages'
			} else {
				this.activeTab = this.isInCall ? 'chat' : 'participants'
			}
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
// Override NcAppSidebar styles
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

:deep(.app-sidebar-tabs__content) {
	/* Allow to shrink tabs content in favor of header information */
	min-height: inherit !important;
}

.app-sidebar-tabs__content #tab-chat {
	/* Remove padding to maximize the space for the chat view. */
	padding: 0;
	height: 100%;
}

.app-sidebar-tabs__content #tab-participants {
	/* Remove padding to maximize the space for the participants list. */
	padding: var(--default-grid-baseline) 0;
	height: 100%;
}

.chat-button-unread-marker {
	position: absolute;
	top: 4px;
	inset-inline-end: 4px;
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
