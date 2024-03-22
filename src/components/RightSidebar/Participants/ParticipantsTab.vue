<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license AGPL-3.0-or-later
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
	<div class="wrapper">
		<div class="search-form">
			<SearchBox v-if="canSearch"
				class="search-form__input"
				:value.sync="searchText"
				:is-focused.sync="isFocused"
				:placeholder-text="searchBoxPlaceholder"
				@input="handleInput"
				@keydown.enter="addParticipants(participantPhoneItem)"
				@abort-search="abortSearch" />
			<DialpadPanel v-if="canAddPhones"
				:value.sync="searchText"
				@submit="addParticipants(participantPhoneItem)" />
		</div>

		<SelectPhoneNumber v-if="canAddPhones"
			:name="t('spreed', 'Add a phone number')"
			:value="searchText"
			:participant-phone-item.sync="participantPhoneItem"
			@select="addParticipants" />

		<ParticipantsListVirtual v-if="!isSearching"
			class="h-100"
			:participants="participants"
			:loading="!participantsInitialised" />

		<div v-else class="scroller">
			<NcAppNavigationCaption v-if="canAdd" :name="t('spreed', 'Participants')" />

			<ParticipantsList v-if="filteredParticipants.length"
				:items="filteredParticipants"
				:loading="!participantsInitialised" />
			<Hint v-else :hint="t('spreed', 'No search results')" />

			<ParticipantsSearchResults v-if="canAdd"
				:search-results="searchResults"
				:contacts-loading="contactsLoading"
				:no-results="noResults"
				:search-text="searchText"
				@click="addParticipants" />
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'
import { toRefs } from 'vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'

import ParticipantsList from './ParticipantsList.vue'
import ParticipantsListVirtual from './ParticipantsListVirtual.vue'
import ParticipantsSearchResults from './ParticipantsSearchResults.vue'
import DialpadPanel from '../../UIShared/DialpadPanel.vue'
import Hint from '../../UIShared/Hint.vue'
import SearchBox from '../../UIShared/SearchBox.vue'
import SelectPhoneNumber from '../../SelectPhoneNumber.vue'

import { useGetParticipants } from '../../../composables/useGetParticipants.js'
import { useIsInCall } from '../../../composables/useIsInCall.js'
import { useSortParticipants } from '../../../composables/useSortParticipants.js'
import { ATTENDEE } from '../../../constants.js'
import { searchPossibleConversations } from '../../../services/conversationsService.js'
import { EventBus } from '../../../services/EventBus.js'
import { addParticipant } from '../../../services/participantsService.js'
import CancelableRequest from '../../../utils/cancelableRequest.js'

const canModerateSipDialOut = getCapabilities()?.spreed?.features?.includes('sip-support-dialout')
		&& getCapabilities()?.spreed?.config.call['sip-enabled']
		&& getCapabilities()?.spreed?.config.call['sip-dialout-enabled']
		&& getCapabilities()?.spreed?.config.call['can-enable-sip']

export default {
	name: 'ParticipantsTab',
	components: {
		DialpadPanel,
		Hint,
		NcAppNavigationCaption,
		ParticipantsList,
		ParticipantsListVirtual,
		ParticipantsSearchResults,
		SearchBox,
		SelectPhoneNumber,
	},

	props: {
		isActive: {
			type: Boolean,
			required: true,
		},
		canSearch: {
			type: Boolean,
			required: true,
		},
		canAdd: {
			type: Boolean,
			required: true,
		},
	},

	setup(props) {
		const { isActive } = toRefs(props)
		const { sortParticipants } = useSortParticipants()
		const isInCall = useIsInCall()
		const { cancelableGetParticipants } = useGetParticipants(isActive, false)

		return {
			sortParticipants,
			isInCall,
			cancelableGetParticipants,
		}
	},

	data() {
		return {
			searchText: '',
			isFocused: false,
			searchResults: [],
			contactsLoading: false,
			participantPhoneItem: {},
			cancelSearchPossibleConversations: () => {},
			debounceFetchSearchResults: () => {},
		}
	},

	computed: {
		participantsInitialised() {
			return this.$store.getters.participantsInitialised(this.token)
		},

		participants() {
			return this.$store.getters.participantsList(this.token).slice().sort(this.sortParticipants)
		},

		filteredParticipants() {
			const isMatch = (string) => string.toLowerCase().includes(this.searchText.toLowerCase())

			return this.participants.filter(participant => {
				return isMatch(participant.displayName)
					|| (participant.actorType !== ATTENDEE.ACTOR_TYPE.GUESTS && isMatch(participant.actorId))
			})
		},

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
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},
		userId() {
			return this.$store.getters.getUserId()
		},
		canAddPhones() {
			return canModerateSipDialOut && this.conversation.canEnableSIP
		},
		isSearching() {
			return this.searchText !== ''
		},
		noResults() {
			return this.searchResults.length === 0
		},
	},

	watch: {
		searchText(value) {
			this.isFocused = !!value
		},
	},

	beforeMount() {
		this.debounceFetchSearchResults = debounce(this.fetchSearchResults, 250)

		EventBus.$on('route-change', this.abortSearch)
		EventBus.$on('signaling-users-changed', this.updateUsers)
		subscribe('user_status:status.updated', this.updateUserStatus)
	},

	beforeDestroy() {
		this.debounceFetchSearchResults.clear?.()

		EventBus.$off('route-change', this.abortSearch)
		EventBus.$off('signaling-users-changed', this.updateUsers)
		unsubscribe('user_status:status.updated', this.updateUserStatus)

		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null
	},

	methods: {
		async updateUsers(usersList) {
			const currentUser = usersList.flat().find(user => user.userId === this.userId)
			const currentParticipant = this.participants.find(user => user.userId === this.userId)
			if (!currentUser) {
				return
			}
			// refresh conversation, if current user permissions have been changed
			if (currentUser.participantPermissions !== this.conversation.permissions) {
				await this.$store.dispatch('fetchConversation', { token: this.token })
			}
			if (currentUser.participantPermissions !== currentParticipant?.permissions) {
				await this.cancelableGetParticipants()
			}
		},

		handleClose() {
			this.$store.dispatch('hideSidebar')
		},

		handleInput() {
			this.contactsLoading = true
			this.searchResults = []
			this.debounceFetchSearchResults()
		},

		async fetchSearchResults() {
			if (!this.isSearching) {
				return
			}

			try {
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(searchPossibleConversations)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({
					searchText: this.searchText,
					token: this.token,
				})

				this.searchResults = response?.data?.ocs?.data || []
				this.contactsLoading = false
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error(exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		/**
		 * Add the selected group/user/circle to the conversation
		 *
		 * @param {object} item The autocomplete suggestion to start a conversation with
		 * @param {string} item.id The ID of the target
		 * @param {string} item.source The source of the target
		 */
		async addParticipants(item) {
			try {
				await addParticipant(this.token, item.id, item.source)
				this.abortSearch()
				this.cancelableGetParticipants()
			} catch (exception) {
				console.debug(exception)
				showError(t('spreed', 'An error occurred while adding the participants'))
			}
		},

		// Ends the search operation
		abortSearch() {
			this.searchText = ''
			if (this.cancelSearchPossibleConversations) {
				this.cancelSearchPossibleConversations()
			}
		},

		updateUserStatus(state) {
			if (!this.token) {
				return
			}

			const participant = this.participants.find(participant => participant.actorId === state.userId)
			if (participant && (participant.status !== state.status
				|| participant.statusMessage !== state.message
				|| participant.statusIcon !== state.icon
				|| participant.statusClearAt !== state.clearAt
			)) {
				this.$store.dispatch('updateUser', {
					token: this.token,
					participantIdentifier: {
						actorType: ATTENDEE.ACTOR_TYPE.USERS,
						actorId: state.userId,
					},
					updatedData: {
						status: state.status,
						statusIcon: state.icon,
						statusMessage: state.message,
						statusClearAt: state.clearAt,
					},
				})
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.wrapper {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.h-100 {
	height: 100%;
}

.search-form {
  display: flex;
  align-items: center;
  gap: 4px;

  .search-form__input {
    margin: 0;
  }
}

.scroller {
	overflow-y: auto;
}

/** TODO: fix these in the nextcloud-vue library **/

:deep(.app-sidebar-header__menu) {
	top: 6px !important;
	margin-top: 0 !important;
	right: 54px !important;
}

:deep(.app-sidebar__close) {
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
