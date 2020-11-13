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
		<CallButton class="top-bar__button" />
		<!-- Call layout switcher -->
		<Popover v-if="isInCall"
			class="top-bar__button"
			trigger="manual"
			:open="showLayoutHint && !hintDismissed"
			@auto-hide="showLayoutHint=false">
			<Actions slot="trigger">
				<ActionButton v-if="isInCall"
					:icon="changeViewIconClass"
					@click="changeView">
					{{ changeViewText }}
				</actionbutton>
			</Actions>
			<div class="hint">
				{{ layoutHintText }}
				<div class="hint__actions">
					<button
						class="hint__button"
						@click="showLayoutHint=false, hintDismissed=true">
						{{ t('spreed', 'Dismiss') }}
					</button>
					<button
						class="hint__button primary"
						@click="changeView">
						{{ t('spreed', 'Use speaker view') }}
					</button>
				</div>
			</div>
		</Popover>
		<!-- sidebar toggle -->
		<Actions
			v-shortkey="['f']"
			class="top-bar__button"
			menu-align="right"
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
				<ActionSeparator
					v-if="canFullModerate" />
				<ActionCheckbox
					v-if="canFullModerate"
					:checked="isSharedPublicly"
					@change="toggleGuests">
					{{ t('spreed', 'Share link') }}
				</ActionCheckbox>
			</template>
			<ActionButton
				v-if="!isOneToOneConversation"
				icon="icon-clippy"
				:close-after-click="true"
				@click="handleCopyLink">
				{{ t('spreed', 'Copy link') }}
			</ActionButton>
			<!-- password -->
			<template
				v-if="showModerationOptions">
				<ActionCheckbox
					v-if="isSharedPublicly"
					class="share-link-password-checkbox"
					:checked="isPasswordProtected"
					@check="handlePasswordEnable"
					@uncheck="handlePasswordDisable">
					{{ t('spreed', 'Password protection') }}
				</ActionCheckbox>
				<ActionInput
					v-show="isEditingPassword"
					class="share-link-password"
					icon="icon-password"
					type="password"
					:value.sync="password"
					autocomplete="new-password"
					@submit="handleSetNewPassword">
					{{ t('spreed', 'Enter a password') }}
				</ActionInput>
				<ActionSeparator />
				<ActionCheckbox
					:checked="isReadOnly"
					:disabled="readOnlyStateLoading"
					@change="toggleReadOnly">
					{{ t('spreed', 'Lock conversation') }}
				</ActionCheckbox>
				<ActionCheckbox
					:checked="hasLobbyEnabled"
					@change="toggleLobby">
					{{ t('spreed', 'Enable lobby') }}
				</ActionCheckbox>
				<ActionInput
					v-if="hasLobbyEnabled"
					icon="icon-calendar-dark"
					type="datetime-local"
					v-bind="dateTimePickerAttrs"
					:value="lobbyTimer"
					:disabled="lobbyTimerLoading"
					@change="setLobbyTimer">
					{{ t('spreed', 'Start time (optional)') }}
				</ActionInput>
				<ActionCheckbox
					:checked="hasSIPEnabled"
					@change="toggleSIPEnabled">
					{{ t('spreed', 'Enable SIP dial-in') }}
				</ActionCheckbox>
			</template>
			<template
				v-if="showModerationOptions && canFullModerate && isInCall">
				<ActionSeparator />
				<ActionButton
					icon="icon-audio"
					:close-after-click="true"
					@click="forceMuteOthers">
					{{ t('spreed', 'Mute others') }}
				</ActionButton>
			</template>
		</Actions>
		<Actions v-if="showOpenSidebarButton"
			class="top-bar__button"
			close-after-click="true">
			<ActionButton
				:icon="iconMenuPeople"
				@click="openSidebar" />
		</Actions>
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Popover from '@nextcloud/vue/dist/Components/Popover'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import CallButton from './CallButton'
import { EventBus } from '../../services/EventBus'
import BrowserStorage from '../../services/BrowserStorage'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants'
import {
	setConversationPassword,
} from '../../services/conversationsService'
import { generateUrl } from '@nextcloud/router'
import { callParticipantCollection } from '../../utils/webrtc/index'

export default {
	name: 'TopBar',

	components: {
		ActionButton,
		Actions,
		ActionCheckbox,
		ActionInput,
		ActionLink,
		CallButton,
		Popover,
		ActionSeparator,
	},

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			showLayoutHint: false,
			hintDismissed: false,
			// The conversation's password
			password: '',
			// Switch for the password-editing operation
			isEditingPassword: false,
			lobbyTimerLoading: false,
			readOnlyStateLoading: false,
		}
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
				return t('spreed', 'Exit fullscreen (f)')
			}
			return t('spreed', 'Fullscreen (f)')
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

		layoutHintText() {
			return t('Spreed', 'Too many videos to fit in the window. Maximize the window or switch to "speaker view" for a better experience.')
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
		isSharedPublicly() {
			return this.conversation.type === CONVERSATION.TYPE.PUBLIC
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
		hasLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},
		isReadOnly() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_ONLY
		},
		isPasswordProtected() {
			return this.conversation.hasPassword
		},
		lobbyTimer() {
			// A timestamp of 0 means that there is no lobby, but it would be
			// interpreted as the Unix epoch by the DateTimePicker.
			if (this.conversation.lobbyTimer === 0) {
				return undefined
			}

			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			return new Date(this.conversation.lobbyTimer * 1000)
		},

		dateTimePickerAttrs() {
			return {
				format: 'YYYY-MM-DD HH:mm',
				firstDayOfWeek: window.firstDay + 1, // Provided by server
				lang: {
					days: window.dayNamesShort, // Provided by server
					months: window.monthNamesShort, // Provided by server
				},
				// Do not update the value until the confirm button has been
				// pressed. Otherwise it would not be possible to set a lobby
				// for today, because as soon as the day is selected the lobby
				// timer would be set, but as no time was set at that point the
				// lobby timer would be set to today at 00:00, which would
				// disable the lobby due to being in the past.
				confirm: true,
			}
		},
		hasSIPEnabled() {
			return this.conversation.sipEnabled === WEBINAR.SIP.ENABLED
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
		// Add call layout hint listener
		EventBus.$on('toggleLayoutHint', this.handleToggleLayoutHint)
	},

	beforeDestroy() {
		document.removeEventListener('fullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('mozfullscreenchange', this.fullScreenChanged, false)
		document.removeEventListener('MSFullscreenChange', this.fullScreenChanged, false)
		document.removeEventListener('webkitfullscreenchange', this.fullScreenChanged, false)
		// Remove call layout hint listener
		EventBus.$off('toggleLayoutHint', this.handleToggleLayoutHint)
	},

	methods: {
		openSidebar() {
			this.$store.dispatch('showSidebar')
			BrowserStorage.setItem('sidebarOpen', 'true')
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
			this.showLayoutHint = false
		},
		async toggleGuests() {
			await this.$store.dispatch('toggleGuests', {
				token: this.token,
				allowGuests: this.conversation.type !== CONVERSATION.TYPE.PUBLIC,
			})
		},

		async toggleLobby() {
			await this.$store.dispatch('toggleLobby', {
				token: this.token,
				enableLobby: this.conversation.lobbyState !== WEBINAR.LOBBY.NON_MODERATORS,
			})
		},

		async toggleSIPEnabled(checked) {
			try {
				await this.$store.dispatch('setSIPEnabled', {
					token: this.token,
					state: checked ? WEBINAR.SIP.ENABLED : WEBINAR.SIP.DISABLED,
				})
			} catch (e) {
				// TODO check "precondition failed"
				// TODO showError()
				console.error(e)
			}
		},

		async setLobbyTimer(date) {
			this.lobbyTimerLoading = true

			let timestamp = 0
			if (date) {
				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				timestamp = date.getTime() / 1000
			}

			await this.$store.dispatch('setLobbyTimer', {
				token: this.token,
				timestamp: timestamp,
			})

			this.lobbyTimerLoading = false
		},

		async toggleReadOnly() {
			this.readOnlyStateLoading = true
			await this.$store.dispatch('setReadOnlyState', {
				token: this.token,
				readOnly: this.isReadOnly ? CONVERSATION.STATE.READ_WRITE : CONVERSATION.STATE.READ_ONLY,
			})
			this.readOnlyStateLoading = false
		},

		async handlePasswordDisable() {
			// disable the password protection for the current conversation
			if (this.conversation.hasPassword) {
				await setConversationPassword(this.token, '')
			}
			this.password = ''
			this.isEditingPassword = false
		},
		async handlePasswordEnable() {
			this.isEditingPassword = true
		},

		async handleSetNewPassword() {
			await setConversationPassword(this.token, this.password)
			this.password = ''
			this.isEditingPassword = false
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
		handleToggleLayoutHint(display) {
			this.showLayoutHint = display
		},
		forceMuteOthers() {
			callParticipantCollection.callParticipantModels.forEach(callParticipantModel => {
				callParticipantModel.forceMute()
			})
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../../assets/variables';

.top-bar {
	height: $top-bar-height;
	position: absolute;
	top: 0;
	right: 12px; /* needed so we can still use the scrollbar */
	display: flex;
	z-index: 10;
	justify-content: flex-end;
	padding: 8px;

	&.in-call {
		right: 0;
	}

	&__button {
		margin: 0 2px;
		align-self: center;
		display: flex;
		align-items: center;
		svg {
			margin-right: 4px !important;
		}
		.icon {
			margin-right: 4px !important;
		}
	}
}

.hint {
	padding: 12px;
	max-width: 300px;
	text-align: left;
	&__button {
		height: $clickable-area;
	}
	&__actions{
		display: flex;
		justify-content: space-between;
		padding-top:4px;
	}
}
</style>
