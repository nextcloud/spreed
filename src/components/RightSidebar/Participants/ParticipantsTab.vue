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
		<SearchBox v-if="canSearch"
			v-model="searchText"
			:placeholder-text="searchBoxPlaceholder"
			:is-searching="isSearching"
			@input="handleInput"
			@abort-search="abortSearch" />
		<AppNavigationCaption v-if="isSearching && canAdd"
			:title="t('spreed', 'Participants')" />
		<CurrentParticipants :search-text="searchText"
			:participants-initialised="participantsInitialised" />
		<ParticipantsSearchResults v-if="canAdd && isSearching"
			:search-results="searchResults"
			:contacts-loading="contactsLoading"
			:no-results="noResults"
			@click="addParticipants" />
	</div>
</template>

<script>
import CurrentParticipants from './CurrentParticipants/CurrentParticipants'
import SearchBox from '../../LeftSidebar/SearchBox/SearchBox'
import debounce from 'debounce'
import { EventBus } from '../../../services/EventBus'
import { searchPossibleConversations } from '../../../services/conversationsService'
import { addParticipant } from '../../../services/participantsService'
import { loadState } from '@nextcloud/initial-state'
import CancelableRequest from '../../../utils/cancelableRequest'
import { showError } from '@nextcloud/dialogs'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import ParticipantsSearchResults from './ParticipantsSearchResults/ParticipantsSearchResults'
import getParticipants from '../../../mixins/getParticipants'

export default {
	name: 'ParticipantsTab',
	components: {
		AppNavigationCaption,
		CurrentParticipants,
		SearchBox,
		ParticipantsSearchResults,
	},

	mixins: [getParticipants],

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
			isCirclesEnabled: loadState('spreed', 'circles_enabled'),
			cancelSearchPossibleConversations: () => {},
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
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},
		isSearching() {
			return this.searchText !== ''
		},
		noResults() {
			return this.searchResults === []
		},
	},

	beforeMount() {
		EventBus.$on('route-change', this.abortSearch)

		// Initialises the get participants mixin
		this.initialiseGetParticipantsMixin()
	},

	beforeDestroy() {
		EventBus.$off('route-change', this.abortSearch)

		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null

		this.stopGetParticipantsMixin()
	},

	methods: {
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
