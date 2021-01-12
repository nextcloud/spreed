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
	<AppNavigation :aria-label="t('spreed', 'Conversation list')">
		<div
			class="new-conversation"
			:class="{ 'new-conversation--scrolled-down': !isScrolledToTop }">
			<SearchBox
				v-model="searchText"
				class="conversations-search"
				:is-searching="isSearching"
				@input="debounceFetchSearchResults"
				@keypress.enter.prevent.stop="onInputEnter"
				@abort-search="abortSearch" />
			<NewGroupConversation
				v-if="canStartConversations" />
		</div>
		<template #list class="left-sidebar__list">
			<div
				ref="scroller"
				class="left-sidebar__list"
				@scroll="debounceHandleScroll">
				<Caption v-if="isSearching"
					:title="t('spreed', 'Conversations')" />
				<li role="presentation">
					<ConversationsList
						ref="conversationsList"
						:conversations-list="conversationsList"
						:initialised-conversations="initialisedConversations"
						:search-text="searchText"
						@click-search-result="handleClickSearchResult"
						@focus="setFocusedIndex" />
				</li>
				<template v-if="isSearching">
					<template v-if="!listedConversationsLoading && searchResultsListedConversations.length > 0">
						<Caption
							:title="t('spreed', 'Listed conversations')" />
						<Conversation
							v-for="item of searchResultsListedConversations"
							:key="item.id"
							:item="item"
							:is-search-result="true"
							@click="joinListedConversation(item)" />
					</template>
					<template v-if="searchResultsUsers.length !== 0">
						<Caption
							:title="t('spreed', 'Users')" />
						<li v-if="searchResultsUsers.length !== 0" role="presentation">
							<ConversationsOptionsList
								:items="searchResultsUsers"
								@click="createAndJoinConversation" />
						</li>
					</template>
					<template v-if="!showStartConversationsOptions">
						<Caption v-if="searchResultsUsers.length === 0"
							:title="t('spreed', 'Users')" />
						<Hint v-if="contactsLoading" :hint="t('spreed', 'Loading')" />
						<Hint v-else :hint="t('spreed', 'No search results')" />
					</template>
				</template>
				<template v-if="showStartConversationsOptions">
					<template v-if="searchResultsGroups.length !== 0">
						<Caption
							:title="t('spreed', 'Groups')" />
						<li v-if="searchResultsGroups.length !== 0" role="presentation">
							<ConversationsOptionsList
								:items="searchResultsGroups"
								@click="createAndJoinConversation" />
						</li>
					</template>

					<template v-if="searchResultsCircles.length !== 0">
						<Caption
							:title="t('spreed', 'Circles')" />
						<li v-if="searchResultsCircles.length !== 0" role="presentation">
							<ConversationsOptionsList
								:items="searchResultsCircles"
								@click="createAndJoinConversation" />
						</li>
					</template>

					<Caption v-if="sourcesWithoutResults"
						:title="sourcesWithoutResultsList" />
					<Hint v-if="contactsLoading" :hint="t('spreed', 'Loading')" />
					<Hint v-else :hint="t('spreed', 'No search results')" />
				</template>
			</div>
		</template>

		<template #footer>
			<div id="app-settings">
				<div id="app-settings-header">
					<button class="settings-button" @click="showSettings">
						{{ t('spreed', 'Talk settings') }}
					</button>
				</div>
			</div>
		</template>
	</AppNavigation>
</template>

<script>
import CancelableRequest from '../../utils/cancelableRequest'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import Caption from '../Caption'
import ConversationsList from './ConversationsList/ConversationsList'
import Conversation from './ConversationsList/Conversation'
import ConversationsOptionsList from '../ConversationsOptionsList'
import Hint from '../Hint'
import SearchBox from './SearchBox/SearchBox'
import debounce from 'debounce'
import { EventBus } from '../../services/EventBus'
import {
	createOneToOneConversation,
	fetchConversations,
	searchPossibleConversations,
	searchListedConversations,
} from '../../services/conversationsService'
import { CONVERSATION } from '../../constants'
import { loadState } from '@nextcloud/initial-state'
import NewGroupConversation from './NewGroupConversation/NewGroupConversation'
import arrowNavigation from '../../mixins/arrowNavigation'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

export default {

	name: 'LeftSidebar',

	components: {
		AppNavigation,
		Caption,
		ConversationsList,
		ConversationsOptionsList,
		Hint,
		SearchBox,
		NewGroupConversation,
		Conversation,
	},

	mixins: [
		arrowNavigation,
	],

	data() {
		return {
			searchText: '',
			searchResults: {},
			searchResultsUsers: [],
			searchResultsGroups: [],
			searchResultsCircles: [],
			searchResultsListedConversations: [],
			contactsLoading: false,
			listedConversationsLoading: false,
			isCirclesEnabled: loadState('spreed', 'circles_enabled'),
			canStartConversations: loadState('spreed', 'start_conversations'),
			initialisedConversations: false,
			cancelSearchPossibleConversations: () => {},
			cancelSearchListedConversations: () => {},
			// Keeps track of wheteher the conversation list is scrolled to the top or not
			isScrolledToTop: true,
		}
	},

	computed: {
		conversationsList() {
			let conversations = this.$store.getters.conversationsList

			if (this.searchText !== '') {
				const lowerSearchText = this.searchText.toLowerCase()
				conversations = conversations.filter(conversation => conversation.displayName.toLowerCase().indexOf(lowerSearchText) !== -1 || conversation.name.toLowerCase().indexOf(lowerSearchText) !== -1)
			}

			return conversations.sort(this.sortConversations)
		},

		isSearching() {
			return this.searchText !== ''
		},

		showStartConversationsOptions() {
			return this.isSearching && this.canStartConversations
		},

		sourcesWithoutResults() {
			return !this.searchResultsUsers.length
				|| !this.searchResultsGroups.length
				|| (this.isCirclesEnabled && !this.searchResultsCircles.length)
		},

		sourcesWithoutResultsList() {
			if (!this.searchResultsUsers.length) {
				if (!this.searchResultsGroups.length) {
					if (this.isCirclesEnabled && !this.searchResultsCircles.length) {
						return t('spreed', 'Users, groups and circles')
					} else {
						return t('spreed', 'Users and groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.searchResultsCircles.length) {
						return t('spreed', 'Users and circles')
					} else {
						return t('spreed', 'Users')
					}
				}
			} else {
				if (!this.searchResultsGroups.length) {
					if (this.isCirclesEnabled && !this.searchResultsCircles.length) {
						return t('spreed', 'Groups and circles')
					} else {
						return t('spreed', 'Groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.searchResultsCircles.length) {
						return t('spreed', 'Circles')
					}
				}
			}
			return t('spreed', 'Other sources')
		},
	},

	beforeMount() {
		/**
		 * After a conversation was created, the search filter is reset
		 */
		EventBus.$once('resetSearchFilter', () => {
			this.abortSearch()
		})

		this.fetchConversations()
	},

	mounted() {
		/** Refreshes the conversations every 30 seconds */
		window.setInterval(() => {
			if (!this.isFetchingConversations) {
				this.fetchConversations()
			}
		}, 30000)

		EventBus.$on('shouldRefreshConversations', this.debounceFetchConversations)

		this.mountArrowNavigation()
	},

	beforeDestroy() {
		EventBus.$off('shouldRefreshConversations', this.debounceFetchConversations)

		this.cancelSearchPossibleConversations()
		this.cancelSearchPossibleConversations = null

		this.cancelSearchListedConversations()
		this.cancelSearchListedConversations = null
	},

	methods: {
		getFocusableList() {
			return this.$el.querySelectorAll('li.acli_wrapper .acli')
		},
		focusCancel() {
			return this.abortSearch()
		},
		isFocused() {
			return this.isSearching
		},

		debounceFetchSearchResults: debounce(function() {
			if (this.isSearching) {
				this.fetchSearchResults()
			}
		}, 250),

		async fetchPossibleConversations() {
			this.contactsLoading = true

			try {
				this.cancelSearchPossibleConversations('canceled')
				const { request, cancel } = CancelableRequest(searchPossibleConversations)
				this.cancelSearchPossibleConversations = cancel

				const response = await request({
					searchText: this.searchText,
					token: undefined,
					onlyUsers: !this.canStartConversations,
				})

				this.searchResults = response?.data?.ocs?.data || []
				this.searchResultsUsers = this.searchResults.filter((match) => {
					return match.source === 'users'
						&& match.id !== this.$store.getters.getUserId()
						&& !this.hasOneToOneConversationWith(match.id)
				})
				this.searchResultsGroups = this.searchResults.filter((match) => match.source === 'groups')
				this.searchResultsCircles = this.searchResults.filter((match) => match.source === 'circles')
				this.contactsLoading = false
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error('Error searching for possible conversations', exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async fetchListedConversations() {
			try {
				this.listedConversationsLoading = true

				this.cancelSearchListedConversations('canceled')
				const { request, cancel } = CancelableRequest(searchListedConversations)
				this.cancelSearchListedConversations = cancel

				const response = await request({ searchText: this.searchText })
				this.searchResultsListedConversations = response.data.ocs.data
				this.listedConversationsLoading = false
			} catch (exception) {
				if (CancelableRequest.isCancel(exception)) {
					return
				}
				console.error('Error searching for open conversations', exception)
				showError(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async fetchSearchResults() {
			await Promise.all([this.fetchPossibleConversations(), this.fetchListedConversations()])

			// If none already focused, focus the first rendered result
			this.focusInitialise()
		},

		/**
		 * Create a new conversation with the selected user
		 * or bring up the dialog to create a new group/circle conversation
		 *
		 * @param {Object} item The autocomplete suggestion to start a conversation with
		 * @param {string} item.id The ID of the target
		 * @param {string} item.label The displayname of the target
		 * @param {string} item.source The source of the target (e.g. users, groups, circle)
		 */
		async createAndJoinConversation(item) {
			let response
			if (item.source === 'users') {
				// Create one-to-one conversation directly
				response = await createOneToOneConversation(item.id)
				const conversation = response.data.ocs.data
				this.abortSearch()
				EventBus.$once('joinedConversation', ({ token }) => {
					this.$refs.conversationsList.scrollToConversation(token)
				})
				this.$store.dispatch('addConversation', conversation)
				this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			} else {
				// For other types we start the conversation creation dialog
				EventBus.$emit('NewGroupConversationDialog', item)
			}
		},

		async joinListedConversation(conversation) {
			this.abortSearch()
			EventBus.$once('joinedConversation', ({ token }) => {
				this.$refs.conversationsList.scrollToConversation(token)
			})
			// add as temporary item that will refresh after the joining process is complete
			this.$store.dispatch('addConversation', conversation)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		hasOneToOneConversationWith(userId) {
			return !!this.conversationsList.find(conversation => conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && conversation.name === userId)
		},

		// Reset the search text, therefore end the search operation.
		abortSearch() {
			this.searchText = ''
			if (this.cancelSearchPossibleConversations) {
				this.cancelSearchPossibleConversations()
			}
			if (this.cancelSearchListedConversations) {
				this.cancelSearchListedConversations()
			}
		},

		showSettings() {
			emit('show-settings')
		},

		handleClickSearchResult(selectedConversationToken) {
			if (this.searchText !== '') {
				EventBus.$once('joinedConversation', ({ token }) => {
					this.$refs.conversationsList.scrollToConversation(token)
				})
			}
			// End the search operation
			this.abortSearch()
		},

		sortConversations(conversation1, conversation2) {
			if (conversation1.isFavorite !== conversation2.isFavorite) {
				return conversation1.isFavorite ? -1 : 1
			}

			return conversation2.lastActivity - conversation1.lastActivity
		},

		debounceFetchConversations: debounce(function() {
			if (!this.isFetchingConversations) {
				this.fetchConversations()
			}
		}, 3000),

		async fetchConversations() {
			this.isFetchingConversations = true

			/**
			 * Fetches the conversations from the server and then adds them one by one
			 * to the store.
			 */
			try {
				const conversations = await fetchConversations()
				this.initialisedConversations = true
				this.$store.dispatch('purgeConversationsStore')
				conversations.data.ocs.data.forEach(conversation => {
					this.$store.dispatch('addConversation', conversation)
					if (conversation.token === this.$store.getters.getToken()) {
						this.$store.dispatch('markConversationRead', this.$store.getters.getToken())
					}
				})
				/**
				 * Emits a global event that is used in App.vue to update the page title once the
				 * ( if the current route is a conversation and once the conversations are received)
				 */
				EventBus.$emit('conversationsReceived', {
					singleConversation: false,
				})
				this.isFetchingConversations = false
			} catch (error) {
				console.debug('Error while fetching conversations: ', error)
				this.isFetchingConversations = false
			}
		},

		// Checks whether the conversations list is scrolled all the way to the top
		// or not
		handleScroll() {
			this.isScrolledToTop = this.$refs.scroller.scrollTop === 0
		},
		debounceHandleScroll: debounce(function() {
			this.handleScroll()
		}, 50),
	},
}
</script>

<style lang="scss" scoped>

@import '../../assets/variables';

.new-conversation {
	display: flex;
	padding: 8px;
	&--scrolled-down {
		border-bottom: 1px solid var(--color-border);
	}
}

// Override vue overflow rules for <ul> elements within app-navigation
.left-sidebar__list {
	height: 100% !important;
	width: 100% !important;
	overflow-y: auto !important;
	overflow-x: hidden !important;
}

</style>
