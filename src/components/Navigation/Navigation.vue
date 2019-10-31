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
		<AppNavigationSearch
			v-model="searchText"
			@input="debounceFetchSearchResults" />
		<ul>
			<Caption v-if="isSearching"
				:title="t('spreed', 'Conversations')" />

			<ConversationsList
				:search-text="searchText" />

			<template v-if="isSearching">
				<Caption
					:title="t('spreed', 'Contacts')" />
				<ContactsList v-if="searchResultsUsers.length !== 0" :contacts="searchResultsUsers" />
				<Hint v-else-if="contactsLoading" :hint="t('spreed', 'Loading')" />
				<Hint v-else :hint="t('spreed', 'No search results')" />

				<Caption
					:title="t('spreed', 'Groups')" />
				<GroupsList v-if="searchResultsGroups.length !== 0" :groups="searchResultsGroups" />
				<Hint v-else-if="contactsLoading" :hint="t('spreed', 'Loading')" />
				<Hint v-else :hint="t('spreed', 'No search results')" />
			</template>
		</ul>
	</AppNavigation>
</template>

<script>
import ConversationsList from './ConversationsList/ConversationsList'
import AppNavigation from 'nextcloud-vue/dist/Components/AppNavigation'
import AppNavigationSearch from './AppNavigationSearch/AppNavigationSearch'
import ContactsList from './ContactsList/ContactsList'
import GroupsList from './GroupsList/GroupsList'
import debounce from 'debounce'
import Caption from './Caption/Caption'
import Hint from './Hint/Hint'
import { searchPossibleConversations } from '../../services/conversationsService'
import { getCurrentUser } from '@nextcloud/auth'
import { CONVERSATION } from '../../constants'

export default {

	name: 'Navigation',

	components: {
		ConversationsList,
		AppNavigation,
		AppNavigationSearch,
		ContactsList,
		GroupsList,
		Caption,
		Hint,
	},

	data() {
		return {
			searchText: '',
			searchResults: {},
			searchResultsUsers: [],
			searchResultsGroups: [],
			contactsLoading: false,
		}
	},

	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},
		isSearching() {
			return this.searchText !== ''
		},
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
			this.searchResultsUsers = this.searchResults.filter((match) => match.source === 'users' && match.id !== getCurrentUser().uid && !this.hasOneToOneConversationWith(match.id))
			this.searchResultsGroups = this.searchResults.filter((match) => match.source === 'groups')
			this.contactsLoading = false
		},

		hasOneToOneConversationWith(userId) {
			return !!this.conversationsList.find(conversation => conversation.type === CONVERSATION.TYPE.ONE_TO_ONE && conversation.name === userId)
		},
	},
}
</script>

<style lang="scss" scoped>

.navigation {
	width: 300px;
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
	border-top: 1px solid lightgray;
}
</style>
