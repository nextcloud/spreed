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
		v-if="opened"
		:title="title"
		:starred="isFavorited"
		:title-editable="canModerate && isRenamingConversation"
		@update:starred="onFavoriteChange"
		@update:title="handleUpdateTitle"
		@submit-title="handleSubmitTitle"
		@dismiss-editing="isRenamingConversation = false"
		@close="handleClose">
		<template v-if="isFileConversation || (conversationHasSettings && showModerationMenu)"
			v-slot:secondary-actions>
			<ActionLink
				v-if="isFileConversation"
				icon="icon-text"
				:href="linkToFile">
				{{ t('spreed', 'Go to file') }}
			</ActionLink>
			<ActionButton
				v-if="canModerate"
				:close-after-click="true"
				icon="icon-rename"
				@click="handleRenameConversation">
				{{ t('spreed', 'Rename conversation') }}
			</ActionButton>
			<ActionText
				v-if="canFullModerate"
				icon="icon-shared"
				:title="t('spreed', 'Guests')" />
			<ActionCheckbox
				v-if="canFullModerate"
				:checked="isSharedPublicly"
				@change="toggleGuests">
				{{ t('spreed', 'Share link') }}
			</ActionCheckbox>
			<ActionButton
				v-if="canFullModerate"
				icon="icon-clippy"
				:close-after-click="true"
				@click="handleCopyLink">
				{{ t('spreed', 'Copy link') }}
			</ActionButton>
			<!-- password -->
			<ActionCheckbox
				v-if="canFullModerate && isSharedPublicly"
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
			<ActionText
				v-if="canFullModerate"
				icon="icon-lobby"
				:title="t('spreed', 'Webinar')" />
			<ActionCheckbox
				v-if="canFullModerate"
				:checked="hasLobbyEnabled"
				@change="toggleLobby">
				{{ t('spreed', 'Enable lobby') }}
			</ActionCheckbox>
			<ActionInput
				v-if="canFullModerate && hasLobbyEnabled"
				icon="icon-calendar-dark"
				type="datetime-local"
				v-bind="dateTimePickerAttrs"
				:value="lobbyTimer"
				:disabled="lobbyTimerLoading"
				@change="setLobbyTimer">
				{{ t('spreed', 'Start time (optional)') }}
			</ActionInput>
		</template>
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
			<ParticipantsTab :display-search-box="displaySearchBox" />
		</AppSidebarTab>
		<AppSidebarTab
			v-if="getUserId"
			id="projects"
			:order="3"
			:name="t('spreed', 'Projects')"
			icon="icon-projects">
			<CollectionList v-if="conversation.token"
				:id="conversation.token"
				type="room"
				:name="conversation.displayName" />
		</AppSidebarTab>
		<AppSidebarTab
			v-if="!getUserId"
			id="settings"
			:order="4"
			:name="t('spreed', 'Settings')"
			icon="icon-settings">
			<SetGuestUsername />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionText from '@nextcloud/vue/dist/Components/ActionText'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import ChatView from '../ChatView'
import { CollectionList } from 'nextcloud-vue-collections'
import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants'
import ParticipantsTab from './Participants/ParticipantsTab'
import {
	addToFavorites,
	removeFromFavorites,
	setConversationPassword,
	setConversationName,
} from '../../services/conversationsService'
import isInLobby from '../../mixins/isInLobby'
import { generateUrl } from '@nextcloud/router'
import SetGuestUsername from '../SetGuestUsername'

export default {
	name: 'RightSidebar',
	components: {
		ActionButton,
		ActionCheckbox,
		ActionInput,
		ActionText,
		ActionLink,
		AppSidebar,
		AppSidebarTab,
		ChatView,
		CollectionList,
		ParticipantsTab,
		SetGuestUsername,
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
			contactsLoading: false,
			lobbyTimerLoading: false,
			// The conversation's password
			password: '',
			// Switch for the password-editing operation
			isEditingPassword: false,
			// Changes the conversation title into an input field for renaming
			isRenamingConversation: false,
			// The conversation name (while editing)
			conversationName: '',
		}
	},

	computed: {
		show() {
			return this.$store.getters.getSidebarStatus()
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

		conversationHasSettings() {
			return this.conversation.type === CONVERSATION.TYPE.GROUP
				|| this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},
		isSharedPublicly() {
			return this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},
		hasLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

		lobbyTimer() {
			// A timestamp of 0 means that there is no lobby, but it would be
			// interpreted as the Unix epoch by the DateTimePicker.
			if (this.conversation.lobbyTimer === 0) {
				return undefined
			}

			// PHP timestamp is second-based; JavaScript timestamp is
			// millisecond based.
			return this.conversation.lobbyTimer * 1000
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

		displaySearchBox() {
			return this.canFullModerate
				&& (this.conversation.type === CONVERSATION.TYPE.GROUP
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

		showModerationMenu() {
			return this.canModerate && (this.canFullModerate || this.isSharedPublicly)
		},
		isPasswordProtected() {
			return this.conversation.hasPassword
		},
		linkToConversation() {
			if (this.token !== '') {
				return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.token)
			} else {
				return ''
			}
		},

		isFileConversation() {
			return this.conversation.objectType === 'file' && this.conversation.objectId
		},
		linkToFile() {
			if (this.isFileConversation) {
				return window.location.protocol + '//' + window.location.host + generateUrl('/f/' + this.conversation.objectId)
			} else {
				return ''
			}
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
	},

	methods: {
		handleClose() {
			this.$store.dispatch('hideSidebar')
		},

		async onFavoriteChange() {
			if (this.conversation.isFavorite) {
				await removeFromFavorites(this.conversation.token)
			} else {
				await addToFavorites(this.conversation.token)
			}

			this.conversation.isFavorite = !this.conversation.isFavorite
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

		handleRenameConversation() {
			// Copy the current conversation's title into the renaming title
			this.conversationName = this.title
			this.isRenamingConversation = true
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
				await setConversationName(this.token, name)
				this.isRenamingConversation = false
			} catch (exception) {
				console.debug(exception)
			}
		},
		async handleCopyLink() {
			try {
				await this.$copyText(this.linkToConversation)
				OCP.Toast.success(t('spreed', 'Conversation link copied to clipboard.'))
			} catch (error) {
				OCP.Toast.error(t('spreed', 'The link could not be copied.'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>

/* Force scroll bars in tabs content instead of in whole sidebar. */
::v-deep .app-sidebar-tabs__content {
	overflow: hidden;

	section {
		height: 100%;

		overflow-y: auto;
	}
}
.app-sidebar-tabs__content #tab-chat {
	/* Remove padding to maximize the space for the chat view. */
	padding: 0;
}

</style>
