<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
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
		:class="{'scrollable': scrollable }">
		<template v-if="addableUsers.length !== 0">
			<AppNavigationCaption :title="t('spreed', 'Add users')" />
			<ParticipantsList
				:items="addableUsers"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableGroups.length !== 0">
			<AppNavigationCaption :title="t('spreed', 'Add groups')" />
			<ParticipantsList
				:items="addableGroups"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableEmails.length !== 0">
			<AppNavigationCaption :title="t('spreed', 'Add emails')" />
			<ParticipantsList
				:items="addableEmails"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableCircles.length !== 0">
			<AppNavigationCaption :title="t('spreed', 'Add circles')" />
			<ParticipantsList
				:items="addableCircles"
				@click="handleClickParticipant" />
		</template>

		<template v-if="addableRemotes.length !== 0">
			<AppNavigationCaption :title="t('spreed', 'Add federated users')" />
			<ParticipantsList
				:items="addableRemotes"
				@click="handleClickParticipant" />
		</template>
		<AppNavigationCaption
			v-if="sourcesWithoutResults"
			:title="sourcesWithoutResultsList" />
		<Hint v-if="contactsLoading" :hint="t('spreed', 'Searching …')" />
		<Hint v-if="!contactsLoading && sourcesWithoutResults" :hint="t('spreed', 'No search results')" />
		<template v-if="isNewGroupConversation">
			<template v-if="noResults">
				<div class="icon-category-search participants-search-results__icon" />
				<p class="participants-search-results__warning">
					{{ t('spreed', 'No results') }}
				</p>
			</template>
			<!-- 'search for more' empty content to display at the end of the
				participants list, this is useful in case the participants list is used
				to display the results of a search. Upon clicking on it, an event is
				emitted to the parent component in order to be able to focus on it's
				input field -->
			<div
				v-if="displaySearchHint && !noResults"
				class="participants-search-results__hint"
				@click="handleClickHint">
				<div class="icon-contacts-dark set-contacts__icon" />
				<p>
					{{ t('spreed', 'Search for more users') }}
				</p>
			</div>
		</template>
	</div>
</template>

<script>
import ParticipantsList from '../ParticipantsList/ParticipantsList'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import Hint from '../../../Hint'

export default {
	name: 'ParticipantsSearchResults',

	components: {
		ParticipantsList,
		AppNavigationCaption,
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
		scrollable: {
			type: Boolean,
			default: false,
		},
		/**
		 * If so, this component will add clicked participant to the selected
		 * participants store;
		 */
		selectable: {
			type: Boolean,
			default: false,
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
						return t('spreed', 'Add users, groups or circles')
					} else {
						return t('spreed', 'Add users or groups')
					}
				} else {
					if (this.isCirclesEnabled && !this.addableCircles.length) {
						return t('spreed', 'Add users or circles')
					} else {
						return t('spreed', 'Add users')
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
		addableRemotes() {
			if (this.searchResults !== []) {
				return this.searchResults.filter((item) => item.source === 'remotes')
			}
			return []
		},
		// Determines whether this component is used in the new group conversation creation
		// context
		isNewGroupConversation() {
			return this.$parent.$options.name === 'SetContacts'
		},

	},
	methods: {
		handleClickParticipant(participant) {
			// Needed for right sidebar
			this.$emit('click', participant)
			// Needed for bulk participants selection (like in the new group conversation
			// creation process)
			if (this.selectable) {
				this.$store.dispatch('updateSelectedParticipants', participant)
			}

		},
		handleClickHint() {
			this.$emit('click-search-hint')
		},
	},
}
</script>

<style lang="scss" scoped>
.scrollable {
	overflow-y: auto;
	overflow-x: hidden;
	flex-shrink: 1;
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
