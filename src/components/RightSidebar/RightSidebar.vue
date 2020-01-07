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
		</template>
		<AppSidebarTab
			:order="1"
			:name="t('spreed', 'Chat')"
			icon="icon-comment">
			<ChatView v-if="showChatInSidebar" :token="token" />
			<template v-else>
				This should be hidden, but the visibility of the tab can not change at the moment:
				https://github.com/nextcloud/nextcloud-vue/issues/747
			</template>
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
} from '../../services/conversationsService'

export default {
	name: 'RightSidebar',
	components: {
		ActionCheckbox,
		ActionText,
		AppSidebar,
		AppSidebarTab,
		ChatView,
		CollectionList,
		ParticipantsTab,
	},

	props: {
		showChatInSidebar: {
			type: Boolean,
			required: true,
		},
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
	},
}
</script>

<style lang="scss" scoped>

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
