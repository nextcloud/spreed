<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
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
	<div class="top-bar" :class="{ 'in-call': isInCall }">
		<!-- conversation header -->
		<a v-if="!isInCall"
			class="conversation-header"
			@click="openConversationSettings">
			<ConversationIcon
				:item="conversation"
				:hide-favorite="false"
				:hide-call="false" />
			<div class="conversation-header__text">
				<p class="title">
					{{ conversation.displayName }}
				</p>
				<p v-if="conversation.description" class="description">
					{{ conversation.description }}
				</p>
			</div>
		</a>
		<div class="top-bar__buttons">
			<CallButton class="top-bar__button" />
			<!-- Call layout switcher -->
			<Actions
				slot="trigger"
				class="forced-background"
				container="#content-vue">
				<ActionButton v-if="isInCall"
					:icon="changeViewIconClass"
					@click="changeView">
					{{ changeViewText }}
				</actionbutton>
			</Actions>
			<!-- sidebar toggle -->
			<Actions
				v-shortkey.once="['f']"
				class="top-bar__button forced-background"
				menu-align="right"
				:aria-label="t('spreed', 'Conversation actions')"
				container="#content-vue"
				@shortkey.native="toggleFullscreen">
				<ActionButton
					:icon="iconFullscreen"
					:aria-label="t('spreed', 'Toggle fullscreen')"
					:close-after-click="true"
					@click="toggleFullscreen">
					{{ labelFullscreen }}
				</ActionButton>
				<ActionSeparator
					v-if="showModerationOptions" />
				<ActionLink
					v-if="isFileConversation"
					icon="icon-text"
					:href="linkToFile">
					{{ t('spreed', 'Go to file') }}
				</ActionLink>
				<template
					v-if="showModerationOptions">
					<ActionButton
						:close-after-click="true"
						icon="icon-rename"
						@click="handleRenameConversation">
						{{ t('spreed', 'Rename conversation') }}
					</ActionButton>
				</template>
				<ActionButton
					v-if="!isOneToOneConversation"
					icon="icon-clippy"
					:close-after-click="true"
					@click="handleCopyLink">
					{{ t('spreed', 'Copy link') }}
				</ActionButton>
				<template
					v-if="showModerationOptions && canFullModerate && isInCall">
					<ActionSeparator />
					<ActionButton
						:close-after-click="true"
						@click="forceMuteOthers">
						<MicrophoneOff
							slot="icon"
							:size="16"
							decorative
							title="" />
						{{ t('spreed', 'Mute others') }}
					</ActionButton>
				</template>
				<ActionSeparator
					v-if="showModerationOptions" />
				<ActionButton
					icon="icon-settings"
					:close-after-click="true"
					@click="showConversationSettings">
					{{ t('spreed', 'Conversation settings') }}
				</ActionButton>
			</Actions>
			<Actions v-if="showOpenSidebarButton"
				class="top-bar__button forced-background"
				close-after-click="true"
				container="#content-vue">
				<ActionButton
					:icon="iconMenuPeople"
					@click="openSidebar" />
			</Actions>
		</div>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import CallButton from './CallButton'
import BrowserStorage from '../../services/BrowserStorage'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff'
import { CONVERSATION, PARTICIPANT } from '../../constants'
import { generateUrl } from '@nextcloud/router'
import { callParticipantCollection } from '../../utils/webrtc/index'
import { emit } from '@nextcloud/event-bus'
import ConversationIcon from '../ConversationIcon'

export default {
	name: 'TopBar',

	components: {
		ActionButton,
		Actions,
		ActionLink,
		CallButton,
		ActionSeparator,
		MicrophoneOff,
		ConversationIcon,
	},

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		isFullscreen() {
			return this.$store.getters.isFullscreen()
		},

		iconFullscreen() {
			if (this.isInCall) {
				return 'forced-white icon-fullscreen'
			}
			return 'icon-fullscreen'
		},

		labelFullscreen() {
			if (this.isFullscreen) {
				return t('spreed', 'Exit fullscreen (F)')
			}
			return t('spreed', 'Fullscreen (F)')
		},

		iconMenuPeople() {
			if (this.isInCall) {
				return 'forced-white icon-menu-people'
			}
			return 'icon-menu-people'
		},

		showOpenSidebarButton() {
			return !this.$store.getters.getSidebarStatus
		},

		changeViewText() {
			if (this.isGrid) {
				return t('spreed', 'Speaker view')
			} else {
				return t('spreed', 'Grid view')
			}
		},
		changeViewIconClass() {
			if (this.isGrid) {
				return 'forced-white icon-promoted-view'
			} else {
				return 'forced-white icon-grid-view'
			}
		},

		isFileConversation() {
			return this.conversation.objectType === 'file' && this.conversation.objectId
		},
		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
		},
		linkToFile() {
			if (this.isFileConversation) {
				return window.location.protocol + '//' + window.location.host + generateUrl('/f/' + this.conversation.objectId)
			} else {
				return ''
			}
		},

		participantType() {
			return this.conversation.participantType
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canModerate() {
			return this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR
		},
		showModerationOptions() {
			return !this.isOneToOneConversation && this.canModerate
		},
		token() {
			return this.$store.getters.getToken()
		},
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},
		linkToConversation() {
			if (this.token !== '') {
				return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.token)
			} else {
				return ''
			}
		},
		conversationHasSettings() {
			return this.conversation.type === CONVERSATION.TYPE.GROUP
				|| this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},

		isGrid() {
			return this.$store.getters.isGrid
		},
	},

	mounted() {
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
	},

	methods: {
		openSidebar() {
			this.$store.dispatch('showSidebar')
			BrowserStorage.setItem('sidebarOpen', 'true')
		},

		showConversationSettings() {
			emit('show-conversation-settings')
		},

		fullScreenChanged() {
			this.$store.dispatch(
				'setIsFullscreen',
				document.webkitIsFullScreen || document.mozFullScreen || document.msFullscreenElement
			)
		},

		toggleFullscreen() {
			if (this.isFullscreen) {
				this.disableFullscreen()
				this.$store.dispatch('setIsFullscreen', false)
			} else {
				this.enableFullscreen()
				this.$store.dispatch('setIsFullscreen', true)
			}
		},

		enableFullscreen() {
			const fullscreenElem = document.getElementById('content-vue')

			if (fullscreenElem.requestFullscreen) {
				fullscreenElem.requestFullscreen()
			} else if (fullscreenElem.webkitRequestFullscreen) {
				fullscreenElem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT)
			} else if (fullscreenElem.mozRequestFullScreen) {
				fullscreenElem.mozRequestFullScreen()
			} else if (fullscreenElem.msRequestFullscreen) {
				fullscreenElem.msRequestFullscreen()
			}
		},

		disableFullscreen() {
			if (document.exitFullscreen) {
				document.exitFullscreen()
			} else if (document.webkitExitFullscreen) {
				document.webkitExitFullscreen()
			} else if (document.mozCancelFullScreen) {
				document.mozCancelFullScreen()
			} else if (document.msExitFullscreen) {
				document.msExitFullscreen()
			}
		},

		changeView() {
			this.$store.dispatch('setCallViewMode', { isGrid: !this.isGrid })
			this.$store.dispatch('selectedVideoPeerId', null)
		},

		async handleCopyLink() {
			try {
				await this.$copyText(this.linkToConversation)
				showSuccess(t('spreed', 'Conversation link copied to clipboard.'))
			} catch (error) {
				showError(t('spreed', 'The link could not be copied.'))
			}
		},
		handleRenameConversation() {
			this.$store.dispatch('isRenamingConversation', true)
			this.$store.dispatch('showSidebar')
		},
		forceMuteOthers() {
			callParticipantCollection.callParticipantModels.forEach(callParticipantModel => {
				callParticipantModel.forceMute()
			})
		},

		openConversationSettings() {
			emit('show-conversation-settings')
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../../assets/variables';

.top-bar {
	height: $top-bar-height;
	right: 12px; /* needed so we can still use the scrollbar */
	display: flex;
	z-index: 10;
	justify-content: flex-end;
	padding: 8px;
	width: 100%;
	background-color: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);

	&.in-call {
		right: 0;
		background-color: transparent;
		border: none;
		position: absolute;
		top: 0;
		left:0;
		.forced-background {
			background-color: rgba(0,0,0,0.1) !important;
			border-radius: var(--border-radius-pill);
		}
	}

	&__buttons {
		display: flex;
		margin-left: 8px;
	}

	&__button {
		margin: 0 2px;
		align-self: center;
		display: flex;
		align-items: center;
		white-space: nowrap;
		svg {
			margin-right: 4px !important;
		}
		.icon {
			margin-right: 4px !important;
		}
	}
}

.conversation-header {
	position: relative;
	display: flex;
	overflow-x: hidden;
	overflow-y: clip;
	margin-left: 48px;
	white-space: nowrap;
	width: 100%;
	cursor: pointer;
	&__text {
		display: flex;
		flex-direction:column;
		flex-grow: 1;
		margin-left: 8px;
		justify-content: center;
		width: 100%;
		overflow: hidden;
	}
	.title {
		font-weight: bold;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.description {
		overflow: hidden;
		text-overflow: ellipsis;
		color: var(--color-text-lighter);
	}
}
</style>
