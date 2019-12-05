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
			v-model="searchText"
			class="set-contacts__input"
			type="text"
			autofocus
			:placeholder="t('spreed', `Search Participants`)"
			@input="handleInput">
		<template>
			<Caption
				:title="t('spreed', `Add participants to ${conversationName}`)" />
			<ParticipantsList
				:add-on-click="false"
				:items="searchResults"
				@refreshCurrentParticipants="getParticipants" />
			<Hint v-if="contactsLoading" :hint="t('spreed', 'Loading')" />
			<Hint v-if="false" :hint="t('spreed', 'No search results')" />
		</template>
	</div>
</template>

<script>
import Caption from '../../../../Caption'
import Hint from '../../../../Hint'
import ParticipantsList from '../../../../RightSidebar/Participants/ParticipantsList/ParticipantsList'
import debounce from 'debounce'
import { searchPossibleConversations } from '../../../../../services/conversationsService'
import { fetchParticipants } from '../../../../../services/participantsService'

export default {
	name: 'SetContacts',
	components: {
		Caption,
		Hint,
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
		}
	},

	computed: {

		isSearching() {
			return this.searchText !== ''
		},
	},

	methods: {
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
				const response = await searchPossibleConversations(this.searchText)
				this.searchResults = response.data.ocs.data
				this.getParticipants()
				this.contactsLoading = false
			} catch (exeption) {
				console.error(exeption)
				OCP.Toast.error(t('spreed', 'An error occurred while performing the search'))
			}
		},

		async getParticipants() {
			try {
				const participants = await fetchParticipants(this.token)
				this.$store.dispatch('purgeParticipantsStore', this.token)
				participants.data.ocs.data.forEach(participant => {
					this.$store.dispatch('addParticipant', {
						token: this.token,
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

<style lang="scss" scoped>
.set-contacts {
	&__input {
		width: 100%;
		outline: none;
		border: none;
		font-size: 16px;
		padding-left: 0;
	}
}

</style>
