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
	<AppNavigation class="vue navigation">
		<div class="new-conversation">
			<SearchBox
				v-model="searchText"
				:is-searching="isSearching"
				@input="debounceFetchSearchResults"
				@abort-search="abortSearch" />
			<NewGroupConversation />
		</div>
		<ul class="left-sidebar__list">
			<Caption v-if="isSearching"
				:title="t('spreed', 'Conversations')" />
			<li>
				<ConversationsList
					:search-text="searchText" />
			</li>
			<template v-if="isSearching">
				<template v-if="searchResultsUsers.length !== 0">
					<Caption
						:title="t('spreed', 'Contacts')" />
					<li v-if="searchResultsUsers.length !== 0">
						<ConversationsOptionsList
							:items="searchResultsUsers"
							@click="createAndJoinConversation" />
					</li>
				</template>

				<template v-if="searchResultsGroups.length !== 0">
					<Caption
						:title="t('spreed', 'Groups')" />
					<li v-if="searchResultsGroups.length !== 0">
						<ConversationsOptionsList
							:items="searchResultsGroups"
							@click="createAndJoinConversation" />
					</li>
				</template>

				<template v-if="searchResultsCircles.length !== 0">
					<Caption
						:title="t('spreed', 'Circles')" />
					<li v-if="searchResultsCircles.length !== 0">
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
		</ul>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import Caption from '../Caption'
import ConversationsList from './ConversationsList/ConversationsList'
import ConversationsOptionsList from '../ConversationsOptionsList'
import Hint from '../Hint'
import SearchBox from './SearchBox/SearchBox'
import debounce from 'debounce'
import { EventBus } from '../../services/EventBus'
import {
	createGroupConversation, createOneToOneConversation,
	searchPossibleConversations,
} from '../../services/conversationsService'
import { CONVERSATION } from '../../constants'
import { loadState } from '@nextcloud/initial-state'
import NewGroupConversation from './NewGroupConversation/NewGroupConversation'

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
	},

	data() {
		return {
			searchText: '',
			searchResults: {},
			searchResultsUsers: [],
			searchResultsGroups: [],
			searchResultsCircles: [],
			contactsLoading: false,
			isCirclesEnabled: loadState('talk', 'circles_enabled'),
		}
	},

	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},
		isSearching() {
			return this.searchText !== ''
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
						return t('spreed', 'Contacts, groups and circles')
					} else {
						return t('spreed', 'Contacts and groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.searchResultsCircles.length) {
						return t('spreed', 'Contacts and circles')
					} else {
						return t('spreed', 'Contacts')
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
			this.searchText = ''
		})
	},

	methods: {
		debounceFetchSearchResults: debounce(function() {
			if (this.isSearching) {
				this.fetchSearchResults()
			}
		}, 250),

		async fetchSearchResults() {
			this.contactsLoading = true
			const response = await searchPossibleConversations(this.searchText)
			this.searchResults = response.data.ocs.data
			this.searchResultsUsers = this.searchResults.filter((match) => {
				return match.source === 'users'
					&& match.id !== this.$store.getters.getUserId()
					&& !this.hasOneToOneConversationWith(match.id)
			})
			this.searchResultsGroups = this.searchResults.filter((match) => match.source === 'groups')
			this.searchResultsCircles = this.searchResults.filter((match) => match.source === 'circles')
			this.contactsLoading = false
		},

		/**
		 * Create a new conversation with the selected group/user/circle
		 * @param {Object} item The autocomplete suggestion to start a conversation with
		 * @param {string} item.id The ID of the target
		 * @param {string} item.source The source of the target
		 */
		async createAndJoinConversation(item) {
			let response
			if (item.source === 'users') {
				response = await createOneToOneConversation(item.id)
			} else {
				response = await createGroupConversation(item.id, item.source)
			}
			const conversation = response.data.ocs.data
			this.$store.dispatch('addConversation', conversation)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
			EventBus.$emit('resetSearchFilter')
		},

		hasOneToOneConversationWith(userId) {
			return !!this.conversationsList.find(conversation => conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && conversation.name === userId)
		},
		// Reset the search text, therefore end the search operation.
		abortSearch() {
			this.searchText = ''
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../../assets/variables';

.new-conversation {
	display: flex;
	padding: 6px;
	border-bottom: 1px solid var(--color-border-dark);
}

.navigation {
	width: $navigation-width;
	position: fixed;
	top: 50px;
	left: 0;
	z-index: 500;
	overflow-y: auto;
	overflow-x: hidden;
	// Do not use vh because of mobile headers
	// are included in the calculation
	height: calc(100% - 50px);
	box-sizing: border-box;
	background-color: var(--color-main-background);
	-webkit-user-select: none;
	-moz-user-select: none;
	-ms-user-select: none;
	user-select: none;
	border-right: 1px solid var(--color-border);
	display: flex;
	flex-direction: column;
	flex-grow: 0;
	flex-shrink: 0;
}

.settings {
	position: sticky;
	bottom: 0;
	border-top: 1px solid var(--color-border-dark);
}
</style>
