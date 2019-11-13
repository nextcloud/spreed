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
		:starred.sync="conversation.isFavorite"
		@close="handleClose">
		<template v-if="conversationHasSettings"
			v-slot:secondary-actions>
			<ActionText
				icon="icon-shared"
				:title="t('spreed', 'Guests')" />
			<ActionCheckbox
				:checked="isSharedPublicly"
				@change="toggleGuests">
				{{ t('spreed', 'Share link') }}
			</ActionCheckbox>
			<ActionText
				icon="icon-lobby"
				:title="t('spreed', 'Webinar')" />
			<ActionCheckbox
				:checked="hasLobbyEnabled"
				@change="toggleLobby">
				{{ t('spreed', 'Enable lobby') }}
			</ActionCheckbox>
		</template>
		<AppSidebarTab v-if="getUserId"
			:name="t('spreed', 'Participants')"
			icon="icon-contacts-dark">
			<ParticipantsTab :display-search-box="displaySearchBox" />
		</AppSidebarTab>
		<AppSidebarTab v-if="getUserId"
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
import ActionText from '@nextcloud/vue/dist/Components/ActionText'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { CollectionList } from 'nextcloud-vue-collections'
import { CONVERSATION, WEBINAR } from '../../constants'
import { searchPossibleConversations } from '../../services/conversationsService'
import ParticipantsTab from './Participants/ParticipantsTab'

export default {
	name: 'Sidebar',
	components: {
		ActionCheckbox,
		ActionText,
		AppSidebar,
		AppSidebarTab,
		CollectionList,
		ParticipantsTab,
	},

	data() {
		return {
			contactsLoading: false,
		}
	},

	computed: {
		show() {
			return this.$store.getters.getSidebarStatus()
		},
		opened() {
			return !!this.token && this.show
		},
		token() {
			return this.$route.params.token
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
			}
		},

		getUserId() {
			return this.$store.getters.getUserId()
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

		displaySearchBox() {
			return this.conversation.type === CONVERSATION.TYPE.GROUP || this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},
		isSearching() {
			return this.searchText !== ''
		},
	},

	methods: {
		handleClose() {
			this.$store.dispatch('hideSidebar')
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
	},
}
</script>

<style scoped>

/** TODO: fix these in the nextcloud-vue library **/

::v-deep .app-sidebar-header__menu {
	top: 6px !important;
	margin-top: 0 !important;
	right: 54px !important;
}
::v-deep .app-sidebar__close {
	top: 6px !important;
	right: 6px !important;
}

</style>
