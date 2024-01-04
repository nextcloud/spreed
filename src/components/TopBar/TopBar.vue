<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
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
	<div class="top-bar" :class="{ 'in-call': isInCall }">
		<ConversationIcon :key="conversation.token"
			class="conversation-icon"
			:offline="isPeerInactive"
			:item="conversation"
			:disable-menu="disableMenu"
			show-user-online-status
			:hide-favorite="false"
			:hide-call="false" />
		<!-- conversation header -->
		<a role="button"
			class="conversation-header"
			@click="openConversationSettings">
			<div class="conversation-header__text"
				:class="{'conversation-header__text--offline': isPeerInactive}">
				<p class="title">
					{{ conversation.displayName }}
				</p>
				<p v-if="showUserStatusAsDescription"
					class="description">
					{{ statusMessage }}
				</p>
				<template v-if="!isInCall && conversation.description">
					<p v-tooltip.bottom="{
							content: renderedDescription,
							delay: { show: 500, hide: 500 },
							autoHide: false,
							html: true,
						}"
						class="description">
						{{ conversation.description }}
					</p>
				</template>
			</div>
		</a>

		<!-- Call time -->
		<CallTime v-if="isInCall"
			:start="conversation.callStartTime"
			class="top-bar__button dark-hover" />

		<!-- Participants counter -->
		<NcButton v-if="isInCall && !isOneToOneConversation && isModeratorOrUser"
			:title="participantsInCallAriaLabel"
			:aria-label="participantsInCallAriaLabel"
			class="top-bar__button dark-hover"
			type="tertiary"
			@click="openSidebar('participants')">
			<template #icon>
				<AccountMultiple :size="20"
					fill-color="#ffffff" />
			</template>
			{{ participantsInCall }}
		</NcButton>

		<!-- Reactions menu -->
		<ReactionMenu v-if="hasReactionSupport"
			class="top-bar__button dark-hover"
			:token="token"
			:supported-reactions="supportedReactions"
			:local-call-participant-model="localCallParticipantModel" />

		<!-- Local media controls -->
		<TopBarMediaControls v-if="isInCall"
			class="local-media-controls dark-hover"
			:token="token"
			:model="localMediaModel"
			:show-actions="!isSidebar"
			:screen-sharing-button-hidden="isSidebar"
			:local-call-participant-model="localCallParticipantModel" />

		<!-- TopBar menu -->
		<TopBarMenu :token="token"
			class="top-bar__button dark-hover"
			:show-actions="!isSidebar"
			:is-sidebar="isSidebar"
			:model="localMediaModel"
			@open-breakout-rooms-editor="showBreakoutRoomsEditor = true" />

		<CallButton class="top-bar__button" />

		<!-- sidebar toggle -->
		<template v-if="showOpenSidebarButton">
			<!-- in chat: open last tab -->
			<NcButton v-if="!isInCall"
				:aria-label="t('spreed', 'Open sidebar')"
				:title="t('spreed', 'Open sidebar')"
				class="top-bar__button dark-hover"
				close-after-click="true"
				type="tertiary"
				@click="openSidebar">
				<template #icon>
					<MenuIcon :size="20" />
				</template>
			</NcButton>

			<!-- in call: open chat tab -->
			<NcButton v-else
				:aria-label="t('spreed', 'Open chat')"
				:title="t('spreed', 'Open chat')"
				class="top-bar__button chat-button dark-hover"
				type="tertiary"
				@click="openSidebar('chat')">
				<template #icon>
					<MessageText :size="20"
						fill-color="#ffffff" />
					<NcCounterBubble v-if="unreadMessagesCounter > 0"
						class="chat-button__unread-messages-counter"
						:type="hasUnreadMentions ? 'highlighted' : 'outlined'">
						{{ unreadMessagesCounter }}
					</NcCounterBubble>
				</template>
			</NcButton>
		</template>

		<!-- Breakout rooms editor -->
		<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
			:token="token"
			@close="showBreakoutRoomsEditor = false" />
	</div>
</template>

<script>
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'
import MessageText from 'vue-material-design-icons/MessageText.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showMessage } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import richEditor from '@nextcloud/vue/dist/Mixins/richEditor.js'

import CallButton from './CallButton.vue'
import CallTime from './CallTime.vue'
import ReactionMenu from './ReactionMenu.vue'
import TopBarMediaControls from './TopBarMediaControls.vue'
import TopBarMenu from './TopBarMenu.vue'
import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'
import ConversationIcon from '../ConversationIcon.vue'

import { useGetParticipants } from '../../composables/useGetParticipants.js'
import { CONVERSATION } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getStatusMessage } from '../../utils/userStatus.js'
import { localCallParticipantModel, localMediaModel } from '../../utils/webrtc/index.js'

export default {
	name: 'TopBar',

	directives: {
		Tooltip,
	},

	components: {
		// Components
		BreakoutRoomsEditor,
		CallButton,
		CallTime,
		ConversationIcon,
		TopBarMediaControls,
		NcButton,
		NcCounterBubble,
		TopBarMenu,
		ReactionMenu,
		// Icons
		AccountMultiple,
		MenuIcon,
		MessageText,
	},

	mixins: [richEditor],

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},

		/**
		 * In the sidebar the conversation settings are hidden
		 */
		isSidebar: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		useGetParticipants()
	},

	data: () => {
		return {
			unreadNotificationHandle: null,
			showBreakoutRoomsEditor: false,
			localCallParticipantModel,
			localMediaModel,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		showOpenSidebarButton() {
			return !this.$store.getters.getSidebarStatus
		},

		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		showUserStatusAsDescription() {
			return this.isOneToOneConversation && this.statusMessage
		},

		statusMessage() {
			return getStatusMessage(this.conversation)
		},

		unreadMessagesCounter() {
			return this.conversation.unreadMessages
		},
		hasUnreadMentions() {
			return this.conversation.unreadMention
		},

		renderedDescription() {
			return this.renderContent(this.conversation.description)
		},

		/**
		 * Current actor id
		 */
		actorId() {
			return this.$store.getters.getActorId()
		},

		/**
		 * Online status of the peer in one to one conversation.
		 */
		isPeerInactive() {
			// Only compute this in one-to-one conversations
			if (!this.isOneToOneConversation) {
				return undefined
			}

			// Get the 1 to 1 peer
			let peer
			const participants = this.$store.getters.participantsList(this.token)
			for (const participant of participants) {
				if (participant.actorId !== this.actorId) {
					peer = participant
				}
			}

			if (peer) {
				return !peer.sessionIds.length
			} else return false
		},

		participantsInCall() {
			return this.$store.getters.participantsInCall(this.token) || ''
		},

		participantsInCallAriaLabel() {
			return n('spreed', '%n participant in call', '%n participants in call', this.$store.getters.participantsInCall(this.token))
		},

		disableMenu() {
			// NcAvatarMenu doesn't work on Desktop
			// See: https://github.com/nextcloud/talk-desktop/issues/34
			return IS_DESKTOP
		},

		supportedReactions() {
			return getCapabilities()?.spreed?.config?.call?.['supported-reactions']
		},

		hasReactionSupport() {
			return this.isInCall && this.supportedReactions?.length > 0
		},
	},

	watch: {
		unreadMessagesCounter(newValue, oldValue) {
			if (!this.isInCall || !this.showOpenSidebarButton) {
				return
			}

			// new messages arrived
			if (newValue > 0 && oldValue === 0 && !this.hasUnreadMentions) {
				this.notifyUnreadMessages(t('spreed', 'You have new unread messages in the chat.'))
			}
		},

		hasUnreadMentions(newValue) {
			if (!this.isInCall || !this.showOpenSidebarButton) {
				return
			}

			if (newValue) {
				this.notifyUnreadMessages(t('spreed', 'You have been mentioned in the chat.'))
			}
		},

		isInCall(newValue) {
			if (!newValue) {
				// discard notification if the call ends
				this.notifyUnreadMessages(null)
			}
		},
	},

	mounted() {
		document.body.classList.add('has-topbar')
		document.addEventListener('fullscreenchange', this.fullScreenChanged, false)
		document.addEventListener('mozfullscreenchange', this.fullScreenChanged, false)
		document.addEventListener('MSFullscreenChange', this.fullScreenChanged, false)
		document.addEventListener('webkitfullscreenchange', this.fullScreenChanged, false)
	},

	beforeDestroy() {
		this.notifyUnreadMessages(null)
		document.removeEventListener('fullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('mozfullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('MSFullscreenChange', this.fullScreenChanged, false)
		document.removeEventListener('webkitfullscreenchange', this.fullScreenChanged, false)
		document.body.classList.remove('has-topbar')
	},

	methods: {
		notifyUnreadMessages(message) {
			if (this.unreadNotificationHandle) {
				this.unreadNotificationHandle.hideToast()
				this.unreadNotificationHandle = null
			}
			if (message) {
				this.unreadNotificationHandle = showMessage(message)
			}
		},

		openSidebar(activeTab) {
			if (typeof activeTab === 'string') {
				emit('spreed:select-active-sidebar-tab', activeTab)
			}
			this.$store.dispatch('showSidebar')
			BrowserStorage.setItem('sidebarOpen', 'true')
		},

		fullScreenChanged() {
			this.$store.dispatch(
				'setIsFullscreen',
				document.webkitIsFullScreen || document.mozFullScreen || document.msFullscreenElement
			)
		},

		openConversationSettings() {
			emit('show-conversation-settings', { token: this.token })
		},
	},
}
</script>

<style lang="scss" scoped>
.top-bar {
	display: flex;
	z-index: 10;
	justify-content: flex-end;
	padding: 8px;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	.talk-sidebar-callview & {
		margin-right: var(--default-clickable-area);
	}

	&.in-call {
		right: 0;
		border: none;
		position: absolute;
		top: 0;
		left:0;
		background-color: transparent;
		display: flex;
		flex-wrap: wrap;
		& * {
			color: #fff;
		}

		:deep(button.dark-hover:hover),
		.dark-hover :deep(button:hover),
		.dark-hover :deep(.action-item--open button),
		:deep(.action-item--open.dark-hover button) {
			background-color: rgba(0, 0, 0, 0.2);
		}
	}

	&__button {
		margin: 0 2px;
		align-self: center;
		display: flex;
		align-items: center;
		white-space: nowrap;
		.icon {
			margin-right: 4px !important;
		}

		&__force-white {
			color: white;
		}
	}

	.chat-button {
		position: relative;
		overflow: visible;
		&__unread-messages-counter {
			position: absolute;
			top: 24px;
			right: 2px;
			pointer-events: none;
			color: var(--color-primary-element);
		}
	}
}

.conversation-icon {
	margin-left: 48px;
}

.conversation-header {
	position: relative;
	display: flex;
	overflow-x: hidden;
	overflow-y: clip;
	white-space: nowrap;
	width: 0;
	flex-grow: 1;
	cursor: pointer;
	&__text {
		display: flex;
		flex-direction:column;
		flex-grow: 1;
		margin-left: 8px;
		justify-content: center;
		width: 100%;
		overflow: hidden;
		height: var(--default-clickable-area);
		&--offline {
			color: var(--color-text-maxcontrast);
		}
	}
	.title {
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.description {
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: fit-content;
		color: var(--color-text-lighter);
	}
}
</style>
