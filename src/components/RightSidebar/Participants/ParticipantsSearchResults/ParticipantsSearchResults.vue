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
		<template v-if="addableUsers.length !== 0">
			<Caption
				:title="t('spreed', 'Add contacts')" />
			<ParticipantsList

				:items="addableUsers"
				@click="addParticipants" />
		</template>

		<template v-if="addableGroups.length !== 0">
			<Caption
				:title="t('spreed', 'Add groups')" />
			<ParticipantsList
				:items="addableGroups"
				@click="addParticipants" />
		</template>

		<template v-if="addableEmails.length !== 0">
			<Caption
				:title="t('spreed', 'Add emails')" />
			<ParticipantsList
				:items="addableEmails"
				@click="addParticipants" />
		</template>

		<template v-if="addableCircles.length !== 0">
			<Caption
				:title="t('spreed', 'Add circles')" />
			<ParticipantsList
				:items="addableCircles"
				@click="addParticipants" />
		</template>

		<Caption v-if="sourcesWithoutResults"
			:title="sourcesWithoutResultsList" />
		<Hint v-if="contactsLoading" :hint="t('spreed', 'Searching …')" />
		<Hint v-else :hint="t('spreed', 'No search results')" />
	</div>
</template>

<script>
import ParticipantsList from '../ParticipantsList/ParticipantsList'
import Caption from '../../../Caption'
import Hint from '../../../Hint'

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
	},
}
</script>

<style lang="scss" scoped>

</style>
