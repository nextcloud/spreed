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
	<div class="set-contacts">
		<div class="icon-search" />
		<input
			ref="setContacts"
			v-model="searchText"
			v-observe-visibility="visibilityChanged"
			class="set-contacts__input"
			type="text"
			:placeholder="t('spreed', 'Search participants')"
			@input="handleInput">
		<template>
			<Caption v-if="contactsLoading"
				:title="t('spreed', 'Loading contacts')" />
			<Caption v-if="!contactsLoading"
				:title="t('spreed', 'Select participants')" />
			<ParticipantsList
				:add-on-click="false"
				height="200px"
				:loading="contactsLoading"
				:no-results="noResults"
				:items="searchResults"
				:display-search-hint="!contactsLoading"
				@updateSelectedParticipants="handleUpdateSelectedParticipants"
				@clickSearchHint="focusInput" />
		</template>
	</div>
</template>

<script>
import Caption from '../../../Caption'
import ParticipantsList from '../../../RightSidebar/Participants/ParticipantsList/ParticipantsList'
import debounce from 'debounce'
import { searchPossibleConversations } from '../../../../services/conversationsService'

export default {
	name: 'SetContacts',
	components: {
		Caption,
		ParticipantsList,
	},

	props: {
		conversationName: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			searchText: '',
			searchResults: [],
			// The loading state is true when the component is initialised as we perform a search for 'contacts'
			// with an empty screen as search text.
			contactsLoading: true,
			noResults: false,
		}
	},

	async mounted() {
		// Focus the input field of the current component.
		this.focusInput()
		// Perform a search with an empty string
		await this.fetchSearchResults()
		// Once the contacts are fetched, remove the spinner.
		this.contactsLoading = false
	},

	methods: {
		handleInput() {
			this.noResults = false
			this.contactsLoading = true
			this.searchResults = []
			this.debounceFetchSearchResults()
		},

		debounceFetchSearchResults: debounce(function() {
			this.fetchSearchResults()
		}, 250),

		async fetchSearchResults() {
			try {
				const response = await searchPossibleConversations(this.searchText)
				this.searchResults = response.data.ocs.data
				this.contactsLoading = false
				if (this.searchResults.length === 0) {
					this.noResults = true
				}
			} catch (exception) {
				console.error(exception)
				OCP.Toast.error(t('spreed', 'An error occurred while performing the search'))
			}
		},
		// Forward the event from the children to the parent
		handleUpdateSelectedParticipants(selectedParticipants) {
			this.$emit('updateSelectedParticipants', selectedParticipants)
		},
		visibilityChanged(isVisible) {
			if (isVisible) {
				// Focus the input field of the current component.
				this.focusInput()
			}
		},
		focusInput() {
			this.$refs.setContacts.focus()
		}
	},
}
</script>

<style lang="scss" scoped>
.set-contacts {
	position: relative;
	&__input {
		width: 100%;
		font-size: 16px;
		padding-left: 28px;
	}
	&__icon {
		margin-top: 40px;
	}
	&__hint {
		margin-top: 20px;
		text-align: center;
	}
}

.icon-search {
	position: absolute;
	top: 12px;
    left: 8px;
}
</style>
