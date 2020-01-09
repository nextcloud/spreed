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
		<input
			ref="setContacts"
			v-model="searchText"
			v-observe-visibility="visibilityChanged"
			class="set-contacts__input"
			type="text"
			:placeholder="t('spreed', 'Search participants')"
			@input="handleInput">
		<template v-if="isSearching">
			<Caption
				:title="t('spreed', 'Select participants')" />
			<ParticipantsList
				:add-on-click="false"
				height="250px"
				:loading="contactsLoading"
				:no-results="noResults"
				:items="searchResults"
				@updateSelectedParticipants="handleUpdateSelectedParticipants" />
		</template>
		<template v-if="!isSearching">
			<div class="icon-contacts-dark set-contacts__icon" />
			<p class="set-contacts__hint">
				{{ t('spreed', 'Search participants') }}
			</p>
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
			contactsLoading: false,
			noResults: false,
		}
	},

	computed: {
		isSearching() {
			return this.searchText !== ''
		},
	},

	mounted() {
		// Focus the input field of the current componnent.
		this.$refs.setContacts.focus()
	},

	methods: {
		handleInput() {
			this.noResults = false
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
				const response = await searchPossibleConversations(this.searchText)
				this.searchResults = response.data.ocs.data
				this.contactsLoading = false
				if (this.searchResults.length === 0) {
					this.noResults = true
				}
			} catch (exeption) {
				console.error(exeption)
				OCP.Toast.error(t('spreed', 'An error occurred while performing the search'))
			}
		},
		// Forward the event from the children to the parent
		handleUpdateSelectedParticipants(selectedParticipants) {
			this.$emit('updateSelectedParticipants', selectedParticipants)
		},
		visibilityChanged(isVisible) {
			if (isVisible) {
				// Focus the input field of the current componnent.
				this.$refs.setContacts.focus()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.set-contacts {
	position: relative;
	&__input {
		width: 100%;
		font-size: 16px;
	}
	&__icon {
		margin-top: 40px;
	}
	&__hint {
		margin-top: 20px;
		text-align: center;
	}
}

</style>
