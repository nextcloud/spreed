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
	<div>
		<SearchBox
			v-if="displaySearchBox"
			v-model="searchText"
			:placeholder-text="t('spreed', 'Add participants to the conversation')"
			@input="debounceFetchSearchResults" />
		<CurrentParticipants />
		<template v-if="isSearching">
			<Caption
				:title="t('spreed', 'Add participants')" />
			<ParticipantsList
				v-if="addableUsers.length !== 0"
				:items="addableUsers"
				@refreshCurrentParticipants="getParticipants" />
			<Hint v-else-if="contactsLoading" :hint="t('spreed', 'Loading')" />
			<Hint v-else :hint="t('spreed', 'No search results')" />

			<Caption
				:title="t('spreed', 'Add groups')" />
			<ParticipantsList
				v-if="addableGroups.length !== 0"
				:items="addableGroups"
				@refreshCurrentParticipants="getParticipants" />
			<Hint v-else-if="contactsLoading" :hint="t('spreed', 'Loading')" />
			<Hint v-else :hint="t('spreed', 'No search results')" />
		</template>
	</div>
</template>

<script>
import Caption from '../../Caption'
import CurrentParticipants from './CurrentParticipants/CurrentParticipants'
import Hint from '../../Hint'
import ParticipantsList from './ParticipantsList/ParticipantsList'
import SearchBox from '../../SearchBox'
import debounce from 'debounce'
import { EventBus } from '../../../services/EventBus'
import { CONVERSATION, WEBINAR } from '../../../constants'
import { getCurrentUser } from '@nextcloud/auth'
import { searchPossibleConversations } from '../../../services/conversationsService'
import { fetchParticipants } from '../../../services/participantsService'

export default {
	name: 'ParticipantsTab',
	components: {
		CurrentParticipants,
		SearchBox,
		Caption,
		Hint,
		ParticipantsList,
	},

	props: {
		displaySearchBox: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			searchText: '',
			addableUsers: [],
			addableGroups: [],
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
		isSearching() {
			return this.searchText !== ''
		},
	},

	beforeMount() {
		this.getParticipants()
		/**
		 * If the route changes, the search filter is reset and we get participants again
		 */
		EventBus.$on('routeChange', () => {
			this.searchText = ''
			this.$nextTick(() => {
				this.getParticipants()
			})
		})
	},

	methods: {
		handleClose() {
			this.$store.dispatch('hideSidebar')
		},

		debounceFetchSearchResults: debounce(function() {
			if (this.isSearching) {
				this.fetchSearchResults()
			}
		}, 250),

		async fetchSearchResults() {
			this.contactsLoading = true
			const response = await searchPossibleConversations(this.searchText)
			const searchResults = response.data.ocs.data

			// get current participants
			const currentParticipants = this.$store.getters.participantsList(this.token)
			// array of both participants already in the conversation and response from server
			const allParticipants = [...currentParticipants, ...searchResults]
			this.addableUsers = allParticipants.filter((match) => match.source === 'users' && match.id !== getCurrentUser().uid)
			this.addableGroups = allParticipants.filter((match) => match.source === 'groups')
			this.contactsLoading = false
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
		async getParticipants() {
			const participants = await fetchParticipants(this.token)
			this.$store.dispatch('purgeParticipantsStore', this.token)
			participants.data.ocs.data.forEach(participant => {
				this.$store.dispatch('addParticipant', {
					token: this.token,
					participant: participant,
				})
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
