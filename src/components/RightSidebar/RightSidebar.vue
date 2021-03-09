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
		v-show="opened"
		id="app-sidebar"
		:title="title"
		:title-tooltip="title"
		:starred="isFavorited"
		:title-editable="canModerate && isRenamingConversation"
		:class="'active-tab-' + activeTab"
		@update:active="handleUpdateActive"
		@update:starred="onFavoriteChange"
		@update:title="handleUpdateTitle"
		@submit-title="handleSubmitTitle"
		@dismiss-editing="dismissEditing"
		@close="handleClose">
		<template slot="description">
			<Description
				v-if="showDescription"
				:editable="canFullModerate"
				:description="description"
				:editing="isEditingDescription"
				:loading="isDescriptionLoading"
				:placeholder="t('spreed', 'Add a description for this conversation')"
				@submit:description="handleUpdateDescription"
				@update:editing="handleEditDescription" />
			<LobbyStatus v-if="canFullModerate && hasLobbyEnabled" :token="token" />
		</template>
		<AppSidebarTab
			v-if="showChatInSidebar"
			id="chat"
			:order="1"
			:name="t('spreed', 'Chat')"
			icon="icon-comment">
			<ChatView :is-visible="opened" />
		</AppSidebarTab>
		<AppSidebarTab v-if="getUserId"
			id="participants"
			:order="2"
			:name="t('spreed', 'Participants')"
			icon="icon-contacts-dark">
			<ParticipantsTab
				:can-search="canSearchParticipants"
				:can-add="canAddParticipants" />
		</AppSidebarTab>
		<AppSidebarTab
			id="details-tab"
			:order="3"
			:name="t('spreed', 'Details')"
			icon="icon-details">
			<SetGuestUsername
				v-if="!getUserId" />
			<SipSettings
				v-if="showSIPSettings"
				:meeting-id="conversation.token"
				:attendee-pin="conversation.attendeePin" />
			<CollectionList
				v-if="getUserId && conversation.token"
				:id="conversation.token"
				type="room"
				:name="conversation.displayName" />
			<div v-if="!getUserId" id="app-settings">
				<div id="app-settings-header">
					<button class="settings-button" @click="showSettings">
						{{ t('spreed', 'Settings') }}
					</button>
				</div>
			</div>
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import ChatView from '../ChatView'
import { CollectionList } from 'nextcloud-vue-collections'
import BrowserStorage from '../../services/BrowserStorage'
import { CONVERSATION, WEBINAR, PARTICIPANT } from '../../constants'
import ParticipantsTab from './Participants/ParticipantsTab'
import isInLobby from '../../mixins/isInLobby'
import SetGuestUsername from '../SetGuestUsername'
import SipSettings from './SipSettings'
import Description from './Description/Description'
import LobbyStatus from './LobbyStatus'
import { EventBus } from '../../services/EventBus'
import { showError } from '@nextcloud/dialogs'

export default {
	name: 'RightSidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		ChatView,
		CollectionList,
		ParticipantsTab,
		SetGuestUsername,
		SipSettings,
		Description,
		LobbyStatus,
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
			activeTab: 'participants',
			contactsLoading: false,
			// The conversation name (while editing)
			conversationName: '',
			isEditingDescription: false,
			isDescriptionLoading: false,
			// Sidebar status before starting editing operation
			sidebarOpenBeforeEditingName: '',
		}
	},

	computed: {
		show() {
			return this.$store.getters.getSidebarStatus
		},
		opened() {
			return !!this.token && !this.isInLobby && this.show
		},
		token() {
			return this.$store.getters.getToken()
		},
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
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

		canAddParticipants() {
			return this.canFullModerate && this.canSearchParticipants
		},
		canSearchParticipants() {
			return (this.conversation.type === CONVERSATION.TYPE.GROUP
					|| (this.conversation.type === CONVERSATION.TYPE.PUBLIC && this.conversation.objectType !== 'share:password'))
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

		isRenamingConversation() {
			return this.$store.getters.isRenamingConversation
		},

		showSIPSettings() {
			return this.conversation.sipEnabled === WEBINAR.SIP.ENABLED
				&& this.conversation.attendeePin
		},

		description() {
			return this.conversation.description
		},

		showDescription() {
			if (this.canFullModerate) {
				return this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
			} else {
				return this.description !== ''
			}
		},

		hasLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

	},

	watch: {
		conversation() {
			if (!this.isRenamingConversation) {
				this.conversationName = this.conversation.displayName
			}
		},
	},

	mounted() {
		EventBus.$on('routeChange', this.handleRouteChange)
	},

	beforeDestroy() {
		EventBus.$off('routeChange', this.handleRouteChange)
	},

	methods: {
		handleClose() {
			this.dismissEditing()
			this.$store.dispatch('hideSidebar')
			BrowserStorage.setItem('sidebarOpen', 'false')
		},

		async onFavoriteChange() {
			this.$store.dispatch('toggleFavorite', this.conversation)
		},

		handleUpdateActive(active) {
			this.activeTab = active
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
				await this.$store.dispatch('setConversationName', {
					token: this.token,
					name: name,
				})
				this.dismissEditing()
			} catch (exception) {
				console.debug(exception)
			}
		},

		dismissEditing() {
			this.$store.dispatch('isRenamingConversation', false)
		},

		showSettings() {
			emit('show-settings')
		},

		async handleUpdateDescription(description) {
			this.isDescriptionLoading = true
			try {
				await this.$store.dispatch('setConversationDescription', {
					token: this.token,
					description,
				})
				this.isEditingDescription = false
			} catch (error) {
				console.error('Error while setting conversation description', error)
				showError(t('spreed', 'Error while updating conversation description'))
			}
			this.isDescriptionLoading = false
		},

		handleEditDescription(payload) {
			this.isEditingDescription = payload
		},

		handleRouteChange() {
			// Reset description data on route change
			this.isEditingDescription = false
			this.isDescriptionLoading = false
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

::v-deep .app-sidebar-header__description {
	flex-direction: column;
}

.app-sidebar-tabs__content #tab-chat {
	/* Remove padding to maximize the space for the chat view. */
	padding: 0;
}

</style>
