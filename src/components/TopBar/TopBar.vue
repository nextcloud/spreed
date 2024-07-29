<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="top-bar" :style="topBarStyle" :data-theme-dark="isInCall">
		<ConversationIcon :key="conversation.token"
			class="conversation-icon"
			:offline="isPeerInactive"
			:item="conversation"
			:size="iconSize"
			:disable-menu="false"
			show-user-online-status
			:hide-favorite="false"
			:hide-call="false" />
		<!-- conversation header -->
		<a role="button"
			class="conversation-header"
			:class="{ 'conversation-header--offline': isPeerInactive }"
			@click="openConversationSettings">
			<span class="conversation-header__name">
				{{ conversation.displayName }}
			</span>
			<span v-if="showUserStatus" class="conversation-header__status">
				{{ statusMessage }}
			</span>
		</a>

		<!-- Call time -->
		<CallTime v-if="isInCall"
			:start="conversation.callStartTime" />

		<!-- Participants counter -->
		<NcButton v-if="isInCall && !isOneToOneConversation && isModeratorOrUser"
			:title="participantsInCallAriaLabel"
			:aria-label="participantsInCallAriaLabel"
			type="tertiary"
			@click="openSidebar('participants')">
			<template #icon>
				<AccountMultiple :size="20" />
			</template>
			{{ participantsInCall }}
		</NcButton>

		<!-- Reactions menu -->
		<ReactionMenu v-if="isInCall && hasReactionSupport"
			:token="token"
			:supported-reactions="supportedReactions"
			:local-call-participant-model="localCallParticipantModel" />

		<!-- Local media controls -->
		<TopBarMediaControls v-if="isInCall"
			:token="token"
			:model="localMediaModel"
			:is-sidebar="isSidebar"
			:local-call-participant-model="localCallParticipantModel" />

		<!-- TopBar menu -->
		<TopBarMenu :token="token"
			:show-actions="!isSidebar"
			:is-sidebar="isSidebar"
			:model="localMediaModel"
			@open-breakout-rooms-editor="showBreakoutRoomsEditor = true" />

		<CallButton shrink-on-mobile :is-screensharing="!!localMediaModel.attributes.localScreen" />

		<!-- Breakout rooms editor -->
		<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
			:token="token"
			@close="showBreakoutRoomsEditor = false" />
	</div>
</template>

<script>
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'

import { emit } from '@nextcloud/event-bus'
import { t, n } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
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
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
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
		TopBarMenu,
		ReactionMenu,
		// Icons
		AccountMultiple,
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
		const iconSize = parseFloat(getComputedStyle(document.documentElement)
			.getPropertyValue('--default-clickable-area'))
		return {
			localCallParticipantModel,
			localMediaModel,
			iconSize,
		}
	},

	data: () => {
		return {
			showBreakoutRoomsEditor: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		isModeratorOrUser() {
			return this.$store.getters.isModeratorOrUser
		},

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		showUserStatus() {
			return this.isOneToOneConversation && this.statusMessage
		},

		statusMessage() {
			return getStatusMessage(this.conversation)
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

		supportedReactions() {
			return getTalkConfig(this.token, 'call', 'supported-reactions')
		},

		hasReactionSupport() {
			return this.isInCall && this.supportedReactions?.length > 0
		},

		topBarStyle() {
			return {
				'--original-color-main-background': window.getComputedStyle(document.body).getPropertyValue('--color-main-background')
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
		t,
		n,
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
	flex-wrap: wrap;
	z-index: 10;
	gap: 3px;
	align-items: flex-start;
	justify-content: flex-end;
	padding: calc(2 * var(--default-grid-baseline));
	// Reserve space for the sidebar toggle button
	padding-right: calc(2 * var(--default-grid-baseline) + var(--app-sidebar-offset));
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	.talk-sidebar-callview & {
		margin-right: var(--default-clickable-area);
	}

	&[data-theme-dark="true"] {
		right: 0;
		border: none;
		position: absolute;
		top: 0;
		left: 0;
		background-color: transparent;
	}
}

.conversation-icon {
	margin-left: 48px;
}

.conversation-header {
	position: relative;
	display: flex;
	margin: 0 calc(2 * var(--default-grid-baseline));
	overflow-x: hidden;
	overflow-y: clip;
	white-space: nowrap;
	align-self: center;
	flex-grow: 1;
	text-overflow: ellipsis;
	line-height: var(--default-line-height);
	cursor: pointer;

	&--offline {
		color: var(--color-text-maxcontrast);
	}

	&__name {
		flex-shrink: 0;
		padding-inline: var(--default-grid-baseline);
		font-weight: bold;
		overflow: hidden;
	}

	&__status {
		padding-inline: var(--default-grid-baseline);
		border-left: 1px solid var(--color-border-maxcontrast);
		color: var(--color-text-maxcontrast);
	}
}

:deep(.conversation-icon__type) {
	border-color: var(--original-color-main-background) !important;
	background-color: var(--original-color-main-background) !important;
}
</style>
