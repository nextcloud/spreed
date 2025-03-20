<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
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
	<div class="top-bar"
		:class="{ 'top-bar--authorised': getUserId }"
		:style="topBarStyle"
		:data-theme-dark="isInCall">
		<ConversationIcon :key="conversation.token"
			class="conversation-icon"
			:offline="isPeerInactive"
			:item="conversation"
			:disable-menu="false"
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
					class="description"
					:class="{'description__in-chat' : !isInCall }">
					{{ statusMessage }}
				</p>
				<template v-if="conversation.description">
					<p v-tooltip.bottom="{
							content: renderedDescription,
							delay: { show: 500, hide: 500 },
							autoHide: false,
							html: true,
						}"
						class="description"
						:class="{'description__in-chat' : !isInCall }">
						{{ conversation.description }}
					</p>
				</template>
			</div>
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

import { getCapabilities } from '@nextcloud/capabilities'
import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
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
		return {
			localCallParticipantModel,
			localMediaModel,
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

		showUserStatusAsDescription() {
			return this.isOneToOneConversation && this.statusMessage
		},

		statusMessage() {
			return getStatusMessage(this.conversation)
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

		supportedReactions() {
			return getCapabilities()?.spreed?.config?.call?.['supported-reactions']
		},

		hasReactionSupport() {
			return this.isInCall && this.supportedReactions?.length > 0
		},

		topBarStyle() {
			return {
				'--original-color-main-background': window.getComputedStyle(document.body).getPropertyValue('--color-main-background')
			}
		},

		getUserId() {
			return this.$store.getters.getUserId()
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
		document.removeEventListener('fullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('mozfullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('MSFullscreenChange', this.fullScreenChanged, false)
		document.removeEventListener('webkitfullscreenchange', this.fullScreenChanged, false)
		document.body.classList.remove('has-topbar')
	},

	methods: {
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

	&--authorised {
		.conversation-icon {
			margin-left: calc(var(--default-clickable-area) + var(--default-grid-baseline));
		}
	}
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
		&__in-chat {
			color: var(--color-text-maxcontrast);
		}
	}
}

:deep(.conversation-icon__type) {
	border-color: var(--original-color-main-background) !important;
	background-color: var(--original-color-main-background) !important;
}
</style>
