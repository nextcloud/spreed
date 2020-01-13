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
		:title="conversation.displayName"
		:starred="isFavorited"
		@update:starred="onFavoriteChange"
		@close="handleClose">
		<template v-if="conversationHasSettings && showModerationMenu"
			v-slot:secondary-actions>
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
			<!-- password -->
			<ActionCheckbox
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
			:order="1"
			:name="t('spreed', 'Chat')"
			icon="icon-comment">
			<ChatView :token="token" />
		</AppSidebarTab>
		<AppSidebarTab v-if="getUserId"
			:order="2"
			:name="t('spreed', 'Participants')"
			icon="icon-contacts-dark">
			<ParticipantsTab :display-search-box="displaySearchBox" />
		</AppSidebarTab>
		<AppSidebarTab v-if="getUserId"
			:order="3"
			:name="t('spreed', 'Projects')"
			icon="icon-projects">
			<CollectionList v-if="conversation.token"
				:id="conversation.token"
				type="room"
				:name="conversation.displayName" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
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
} from '../../services/conversationsService'
import isInLobby from '../../mixins/isInLobby'

export default {
	name: 'RightSidebar',
	components: {
		ActionCheckbox,
		ActionInput,
		ActionText,
		AppSidebar,
		AppSidebarTab,
		ChatView,
		CollectionList,
		ParticipantsTab,
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
			if (this.$store.getters.conversations[this.token]) {
				return this.$store.getters.conversations[this.token]
			}
			return {
				token: '',
				displayName: '',
				isFavorite: false,
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
			return this.conversation.type === CONVERSATION.TYPE.GROUP
				|| this.conversation.type === CONVERSATION.TYPE.PUBLIC
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
			return this.$store.getters.conversations[this.token].hasPassword
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

/** TODO: fix these in the nextcloud-vue library **/

::v-deep .app-sidebar-header {
	&__menu {
	top: 6px !important;
	margin-top: 0 !important;
	right: 54px !important;
	}
	&__title {
		line-height: inherit;
	}
}
::v-deep .app-sidebar__close {
	top: 6px !important;
	right: 6px !important;
}

</style>
