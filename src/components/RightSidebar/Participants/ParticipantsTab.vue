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
			v-if="canSearch"
			v-model="searchText"
			:placeholder-text="searchBoxPlaceholder"
			:is-searching="isSearching"
			@input="handleInput"
			@abort-search="abortSearch" />
		<Caption v-if="isSearching && canAdd"
			:title="t('spreed', 'Participants')" />
		<CurrentParticipants
			:search-text="searchText"
			:participants-initialised="participantsInitialised" />
		<ParticipantsSearchResults
			v-if="canAdd && isSearching"
			:search-results="searchResults"
			:contacts-loading="contactsLoading"
			:no-results="noResults"
			@click="addParticipants" />
	</div>
</template>

<script>
import Caption from '../../Caption'
import CurrentParticipants from './CurrentParticipants/CurrentParticipants'
import SearchBox from '../../LeftSidebar/SearchBox/SearchBox'
import debounce from 'debounce'
import { EventBus } from '../../../services/EventBus'
import { CONVERSATION, PARTICIPANT, WEBINAR } from '../../../constants'
import { searchPossibleConversations } from '../../../services/conversationsService'
import {
	addParticipant,
	fetchParticipants,
} from '../../../services/participantsService'
import isInLobby from '../../../mixins/isInLobby'
import { loadState } from '@nextcloud/initial-state'
import SHA1 from 'crypto-js/sha1'
import Hex from 'crypto-js/enc-hex'
import CancelableRequest from '../../../utils/cancelableRequest'
import Axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import ParticipantsSearchResults from './ParticipantsSearchResults/ParticipantsSearchResults'

export default {
	name: 'ParticipantsTab',
	components: {
		CurrentParticipants,
		SearchBox,
		Caption,
		ParticipantsSearchResults,
	},

	mixins: [
		isInLobby,
	],

	props: {
		canSearch: {
			type: Boolean,
			required: true,
		},
		canAdd: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			searchText: '',
			searchResults: [],
			contactsLoading: false,
			participantsInitialised: false,
			isCirclesEnabled: loadState('talk', 'circles_enabled'),
			/**
			 * Stores the cancel function for cancelableGetParticipants
			 */
			cancelGetParticipants: () => {},
			fetchingParticipants: false,
		}
	},

	computed: {
		searchBoxPlaceholder() {
			return this.canAdd
				? t('spreed', 'Search or add participants')
				: t('spreed', 'Search participants')
		},
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
			if (this.$store.getters.conversation(this.token)) {
				return this.$store.getters.conversation(this.token)
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
		noResults() {
			return this.searchResults === []
		},
	},

	beforeMount() {
		EventBus.$on('routeChange', this.onRouteChange)
		EventBus.$on('joinedConversation', this.onJoinedConversation)

		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.$on('Signaling::participantListChanged', this.debounceUpdateParticipants)
	},

	beforeDestroy() {
		EventBus.$off('routeChange', this.onRouteChange)
		EventBus.$off('joinedConversation', this.onJoinedConversation)
		EventBus.$off('Signaling::participantListChanged', this.debounceUpdateParticipants)
	},

	methods: {
		/**
		 * If the route changes, the search filter is reset
		 */
		onRouteChange() {
			// Reset participantsInitialised when there is only the current user in the participant list
			this.participantsInitialised = this.$store.getters.participantsList(this.token).length > 1
			this.searchText = ''
		},

		/**
		 * If the conversation has been joined, we get the participants
		 */
		onJoinedConversation() {
			this.$nextTick(() => {
				this.cancelableGetParticipants()
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

		debounceUpdateParticipants: debounce(function() {
			if (!this.fetchingParticipants) {
				this.cancelableGetParticipants()
			}
		}, 2000),

		async fetchSearchResults() {
			try {
				const response = await searchPossibleConversations(this.searchText, this.token)
				this.searchResults = response.data.ocs.data
				this.contactsLoading = false
			} catch (exception) {
				console.error(exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		/**
		 * Add the selected group/user/circle to the conversation
		 * @param {Object} item The autocomplete suggestion to start a conversation with
		 * @param {string} item.id The ID of the target
		 * @param {string} item.source The source of the target
		 */
		async addParticipants(item) {
			try {
				await addParticipant(this.token, item.id, item.source)
				this.searchText = ''
				this.cancelableGetParticipants()
			} catch (exception) {
				console.debug(exception)
			}
		},

		async cancelableGetParticipants() {
			if (this.token === '' || this.isInLobby) {
				return
			}

			try {
				// The token must be stored in a local variable to ensure that
				// the same token is used after waiting.
				const token = this.token
				// Clear previous requests if there's one pending
				this.cancelGetParticipants('Cancel get participants')
				// Get a new cancelable request function and cancel function pair
				this.fetchingParticipants = true
				const { request, cancel } = CancelableRequest(fetchParticipants)
				this.cancelGetParticipants = cancel
				const participants = await request(token)
				this.$store.dispatch('purgeParticipantsStore', token)

				const hasUserStatuses = !!participants.headers['x-nextcloud-has-user-statuses']
				participants.data.ocs.data.forEach(participant => {
					this.$store.dispatch('addParticipant', {
						token: token,
						participant,
					})
					if (participant.participantType === PARTICIPANT.TYPE.GUEST
						|| participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR) {
						this.$store.dispatch('forceGuestName', {
							token: token,
							actorId: Hex.stringify(SHA1(participant.sessionId)),
							actorDisplayName: participant.displayName,
						})
					} else if (participant.actorType === 'users' && hasUserStatuses) {
						emit('user_status:status.updated', {
							status: participant.status,
							message: participant.statusMessage,
							icon: participant.statusIcon,
							clearAt: participant.statusClearAt,
							userId: participant.actorId,
						})
					}
				})
				this.participantsInitialised = true
			} catch (exception) {
				if (!Axios.isCancel(exception)) {
					console.error(exception)
					showError(t('spreed', 'An error occurred while fetching the participants'))
				}
			} finally {
				this.fetchingParticipants = false
			}
		},

		// Ends the search operation
		abortSearch() {
			this.searchText = ''
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

/*
 * The field will fully overlap the top of the sidebar content so
 * that elements will scroll behind it
 */
.app-navigation-search {
	top: -10px;
	margin: -10px;
	padding: 10px;
}

</style>
