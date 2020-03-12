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
	<div
		class="participants-search-results"
		:class="{'scrollable': scrollable }"
		:style="{ 'height': height }">
		<template v-if="addableUsers.length !== 0">
			<Caption
				:title="t('spreed', 'Add contacts')" />
			<ParticipantsList
				:items="addableUsers"
				@click="handleClickParticipant"
				@updateSelectedParticipants="handleUpdateSelectedParticipants" />
		</template>

		<template v-if="addableGroups.length !== 0">
			<Caption
				:title="t('spreed', 'Add groups')" />
			<ParticipantsList
				:items="addableGroups"
				@click="handleClickParticipant"
				@updateSelectedParticipants="handleUpdateSelectedParticipants" />
		</template>

		<template v-if="addableEmails.length !== 0">
			<Caption
				:title="t('spreed', 'Add emails')" />
			<ParticipantsList
				:items="addableEmails"
				@click="handleClickParticipant"
				@updateSelectedParticipants="handleUpdateSelectedParticipants" />
		</template>

		<template v-if="addableCircles.length !== 0">
			<Caption
				:title="t('spreed', 'Add circles')" />
			<ParticipantsList
				:items="addableCircles"
				@click="handleClickParticipant"
				@updateSelectedParticipants="handleUpdateSelectedParticipants" />
		</template>

		<Caption v-if="sourcesWithoutResults"
			:title="sourcesWithoutResultsList" />
		<Hint v-if="contactsLoading && !noResults" :hint="t('spreed', 'Searching …')" />
		<Hint v-else :hint="t('spreed', 'No search results')" />
		<template v-if="noResults">
			<div class="icon-category-search participants-search-results__icon" />
			<p class="participants-search-results__warning">
				{{ t('spreed', 'No results') }}
			</p>
		</template>
		<template v-if="loading">
			<template>
				<LoadingParticipant
					v-for="n in dummyParticipants"
					:key="n" />
			</template>
			<template>
				<div class="icon-loading participants-search-results__icon" />
				<p class="participants-search-results__warning">
					{{ t('spreed', 'Contacts loading') }}
				</p>
			</template>
		</template>
		<!-- 'search for more' empty content to display at the end of the
			participants list, this is useful in case the participants list is used
			to display the results of a search. Upon clicking on it, an event is
			emitted to the parent component in order to be able to focus on it's
			input field -->
		<div
			v-if="displaySearchHint"
			class="participants-search-results__hint"
			@click="handleClickHint">
			<div class="icon-contacts-dark set-contacts__icon" />
			<p>
				{{ t('spreed', 'Search for more contacts') }}
			</p>
		</div>
	</div>
</template>

<script>
import ParticipantsList from '../ParticipantsList/ParticipantsList'
import Caption from '../../../Caption'
import Hint from '../../../Hint'
import Vue from 'vue'

export default {
	name: 'ParticipantsSearchResults',

	components: {
		ParticipantsList,
		Caption,
		Hint,
	},

	props: {
		searchResults: {
			type: Array,
			required: true,
		},
		contactsLoading: {
			type: Boolean,
			required: true,
		},
		/**
		 * If true, clicking the participant will add it to to the current conversation.
		 * This behavior is used in the right sidebar for already existing conversations.
		 * If false, clicking on the participant will add the participant to the
		 * `selectedParticipants` array in the data.
		 */
		addOnClick: {
			type: Boolean,
			default: true,
		},
		/**
		 * A fixed height can be passed in e.g. ('250px'). This will limit the height of
		 * the ul and make it scrollable.
		 */
		height: {
			type: String,
			default: 'auto',
		},
		/**
		 * Display no-results state instead of list.
		 */
		noResults: {
			type: Boolean,
			default: false,
		},
		/**
		 * Display 'search for more' empty content at the end of the list.
		 */
		displaySearchHint: {
			type: Boolean,
			default: false,
		},
		/**
		 * Display loading state instead of list.
		 */
		loading: {
			type: Boolean,
			default: false,
		},
		selectedParticipants: {
			type: Array,
			default: () => [],
		},
	},

	computed: {
		sourcesWithoutResults() {
			return !this.addableUsers.length
				|| !this.addableGroups.length
				|| (this.isCirclesEnabled && !this.addableCircles.length)
		},

		sourcesWithoutResultsList() {
			if (!this.addableUsers.length) {
				if (!this.addableGroups.length) {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add contacts, groups or circles')
					} else {
						return t('spreed', 'Add contacts or groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add contacts or circles')
					} else {
						return t('spreed', 'Add contacts')
					}
				}
			} else {
				if (!this.addableGroups.length) {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add groups or circles')
					} else {
						return t('spreed', 'Add groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add circles')
					}
				}
			}
			return t('spreed', 'Add other sources')
		},

		addableUsers() {
			if (this.searchResults !== []) {
				const searchResultUsers = this.searchResults.filter(item => item.source === 'users')
				const participants = this.$store.getters.participantsList(this.token)
				return searchResultUsers.filter(user => {
					let addable = true
					for (const participant of participants) {
						if (user.id === participant.userId) {
							addable = false
							break
						}
					}
					return addable
				})
			}
			return []
		},
		addableGroups() {
			if (this.searchResults !== []) {
				return this.searchResults.filter((item) => item.source === 'groups')
			}
			return []
		},
		addableEmails() {
			if (this.searchResults !== []) {
				return this.searchResults.filter((item) => item.source === 'emails')
			}
			return []
		},
		addableCircles() {
			if (this.searchResults !== []) {
				return this.searchResults.filter((item) => item.source === 'circles')
			}
			return []
		},
		scrollable() {
			return this.height !== 'auto'
		},
		/**
		 * Creates a new array that combines the items (participants received as a prop)
		 * with the current selectedParticipants so that each participant in the returned
		 * array has a new 'selected' boolean key.
		 * @returns {array} An array of 'participant' objects
		 */
		participants() {
			/**
			 * Compute this only in the new group conversation form.
			 */
			if (this.addOnClick === false) {
				if (this.items !== []) {
					const participants = this.items.slice()
					participants.forEach(item => {
						if (this.selectedParticipants.indexOf(item) !== -1) {
							Vue.set(item, 'selected', true)
						} else {
							Vue.set(item, 'selected', false)
						}
					})
					return participants
				} else {
					return []
				}
			} else {
				return this.items
			}
		},
	},

	methods: {
		handleClickParticipant(participant) {
			// Needed for right sidebar
			this.$emit('click', participant)
			// Needed for bulk participants selection (like in the new group conversation
			// creation process)
			this.$store.dispatch('updateSelectedParticipants', participant)
		},
		handleClickHint() {
			this.$emit('clickSearchHint')
		},
		handleUpdateSelectedParticipants(selectedParticipants) {
			this.$emit('updateSelectedParticipants', selectedParticipants)
		},
	},
}
</script>

<style lang="scss" scoped>
.scrollable {
	overflow-y: auto;
	overflow-x: hidden;
}

.participants-search-results {
	&__icon {
		margin-top: 40px;
	}
	&__warning {
		margin-top: 20px;
		text-align: center;
	}
	&__hint {
		margin: 20px 0;
		cursor: pointer;
		p {
			margin-top: 20px;
			text-align: center;
		}
	}
}

</style>
