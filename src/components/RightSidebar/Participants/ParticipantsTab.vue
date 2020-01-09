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
			@input="handleInput" />
		<Caption v-if="isSearching"
			:title="t('spreed', 'Participants')" />
		<CurrentParticipants
			:search-text="searchText" />
		<template v-if="isSearching">
			<template v-if="addableUsers.length !== 0">
				<Caption
					:title="t('spreed', 'Add contacts')" />
				<ContactsList
					:contacts="addableUsers"
					@click="addUserToParticipants" />
			</template>

			<template v-if="addableGroups.length !== 0">
				<Caption
					:title="t('spreed', 'Add groups')" />
				<GroupsList
					:groups="addableGroups"
					@click="addGroupToParticipants" />
			</template>

			<template v-if="addableCircles.length !== 0">
				<Caption
					:title="t('spreed', 'Add circles')" />
				<CirclesList
					:circles="addableCircles"
					@click="addCircleToParticipants" />
			</template>

			<Caption v-if="sourcesWithoutResults"
				:title="sourcesWithoutResultsList" />
			<Hint v-if="contactsLoading" :hint="t('spreed', 'Loading')" />
			<Hint v-else :hint="t('spreed', 'No search results')" />
		</template>
	</div>
</template>

<script>
import Caption from '../../Caption'
import CurrentParticipants from './CurrentParticipants/CurrentParticipants'
import Hint from '../../Hint'
import CirclesList from '../../LeftSidebar/CirclesList/CirclesList'
import ContactsList from '../../LeftSidebar/ContactsList/ContactsList'
import GroupsList from '../../LeftSidebar/GroupsList/GroupsList'
import SearchBox from '../../LeftSidebar/SearchBox/SearchBox'
import debounce from 'debounce'
import { EventBus } from '../../../services/EventBus'
import { CONVERSATION, WEBINAR } from '../../../constants'
import { searchPossibleConversations } from '../../../services/conversationsService'
import {
	addParticipant,
	fetchParticipants,
} from '../../../services/participantsService'
import isInLobby from '../../../mixins/isInLobby'

export default {
	name: 'ParticipantsTab',
	components: {
		CurrentParticipants,
		CirclesList,
		ContactsList,
		GroupsList,
		SearchBox,
		Caption,
		Hint,
	},

	mixins: [
		isInLobby,
	],

	props: {
		displaySearchBox: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			searchText: '',
			searchResults: [],
			contactsLoading: false,
			isCirclesEnabled: true, // FIXME
		}
	},

	computed: {
		show() {
			return this.$store.getters.getSidebarStatus
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
		isSearching() {
			return this.searchText !== ''
		},

		sourcesWithoutResults() {
			return !this.addableUsers.length
				|| !this.addableGroups.length
				|| (this.isCirclesEnabled && !this.addableCircles.length)
		},

		sourcesWithoutResultsList() {
			if (!this.addableUsers.length) {
				if (!this.addableGroups.length) {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add contacts, groups or circles')
					} else {
						return t('spreed', 'Add contacts or groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add contacts or circles')
					} else {
						return t('spreed', 'Add contacts')
					}
				}
			} else {
				if (!this.addableGroups.length) {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add groups or circles')
					} else {
						return t('spreed', 'Add groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add circles')
					}
				}
			}
			return t('spreed', 'Add other sources')
		},

		addableUsers() {
			if (this.searchResults !== []) {
				const searchResultUsers = this.searchResults.filter(item => item.source === 'users')
				const participants = this.$store.getters.participantsList(this.token)
				return searchResultUsers.filter(user => {
					let addable = true
					for (const participant of participants) {
						if (user.id === participant.userId) {
							addable = false
							break
						}
					}
					return addable
				})
			}
			return []
		},
		addableGroups() {
			if (this.searchResults !== []) {
				return this.searchResults.filter((item) => item.source === 'groups')
			}
			return []
		},
		addableCircles() {
			if (this.searchResults !== []) {
				return this.searchResults.filter((item) => item.source === 'circles')
			}
			return []
		},
	},

	beforeMount() {
		this.getParticipants()

		EventBus.$on('routeChange', this.onRouteChange)

		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.$on('Signaling::participantListChanged', this.getParticipants)
	},

	beforeDestroy() {
		EventBus.$off('routeChange', this.onRouteChange)
		EventBus.$off('Signaling::participantListChanged', this.getParticipants)
	},

	methods: {
		/**
		 * If the route changes, the search filter is reset and we get participants again
		 */
		onRouteChange() {
			this.searchText = ''
			this.$nextTick(() => {
				this.getParticipants()
			})
		},

		handleClose() {
			this.$store.dispatch('hideSidebar')
		},
		handleInput() {
			this.contactsLoading = true
			this.searchResults = []
			this.debounceFetchSearchResults()
		},

		debounceFetchSearchResults: debounce(function() {
			if (this.isSearching) {
				this.fetchSearchResults()
			}
		}, 250),

		async fetchSearchResults() {
			try {
				const response = await searchPossibleConversations(this.searchText, this.token)
				this.searchResults = response.data.ocs.data
				this.getParticipants()
				this.contactsLoading = false
			} catch (exeption) {
				console.error(exeption)
				OCP.Toast.error(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async addUserToParticipants(userId) {
			try {
				await addParticipant(this.token, userId, 'users')
				this.searchText = ''
				this.getParticipants()
			} catch (exception) {
				console.debug(exception)
			}
		},

		async addCircleToParticipants(circleId) {
			try {
				await addParticipant(this.token, circleId, 'circles')
				this.searchText = ''
				this.getParticipants()
			} catch (exception) {
				console.debug(exception)
			}
		},

		async addGroupToParticipants(groupId) {
			try {
				await addParticipant(this.token, groupId, 'groups')
				this.searchText = ''
				this.getParticipants()
			} catch (exception) {
				console.debug(exception)
			}
		},

		async getParticipants() {
			if (this.token === '' || this.isInLobby) {
				return
			}

			try {
				// The token must be stored in a local variable to ensure that
				// the same token is used after waiting.
				const token = this.token
				const participants = await fetchParticipants(token)
				this.$store.dispatch('purgeParticipantsStore', token)
				participants.data.ocs.data.forEach(participant => {
					this.$store.dispatch('addParticipant', {
						token: token,
						participant,
					})
				})
			} catch (exeption) {
				console.error(exeption)
				OCP.Toast.error(t('spreed', 'An error occurred while fetching the participants'))
			}
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
