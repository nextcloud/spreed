<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me
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
	<AppNavigation>
		<ul class="app-navigation">
			<AppNavigationSearch
				v-model="searchText"
				@input="debounceFetchSearchResults" />
			<ConversationsList />
		</ul>
		<AppNavigationSettings>
			Example settings
		</AppNavigationSettings>
	</AppNavigation>
</template>

<script>
import ConversationsList from './ConversationsList/ConversationsList'
import AppNavigation from 'nextcloud-vue/dist/Components/AppNavigation'
import AppNavigationSearch from './AppNavigationSearch/AppNavigationSearch'
import AppNavigationSettings from 'nextcloud-vue/dist/Components/AppNavigationSettings'
import { searchPossibleConversations } from '../../services/conversationsService'

import debounce from 'debounce'

export default {

	name: 'Navigation',

	components: {
		ConversationsList,
		AppNavigation,
		AppNavigationSettings,
		AppNavigationSearch
	},

	data() {
		return {
			searchText: '',
			searchResults: {},
			contactsLoading: false
		}
	},

	methods: {
		debounceFetchSearchResults: debounce(function() {
			this.fetchSearchResults()
		}, 250),

		async fetchSearchResults() {
			this.contactsLoading = true
			this.searchResults = await searchPossibleConversations(this.searchText)
			this.contactsLoading = false
		}
	}
}
</script>

<style lang="scss" scoped>

</style>
