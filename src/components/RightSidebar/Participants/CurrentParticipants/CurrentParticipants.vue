<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<ParticipantsList
		:items="participantsList"
		:loading="!participantsInitialised" />
</template>

<script>

import ParticipantsList from '../ParticipantsList/ParticipantsList'
import { PARTICIPANT } from '../../../../constants'

export default {
	name: 'CurrentParticipants',

	components: {
		ParticipantsList,
	},

	props: {
		searchText: {
			type: String,
			default: '',
		},
		participantsInitialised: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},

		/**
		 * Gets the participants array.
		 *
		 * @returns {array}
		 */
		participantsList() {
			let participants = this.$store.getters.participantsList(this.token)

			if (this.searchText !== '') {
				const lowerSearchText = this.searchText.toLowerCase()
				participants = participants.filter(participant => participant.displayName.toLowerCase().indexOf(lowerSearchText) !== -1 || participant.userId.toLowerCase().indexOf(lowerSearchText) !== -1)
			}

			return participants.slice().sort(this.sortParticipants)
		},
	},

	methods: {

		/**
		 * Sort two participants by:
		 * - type (moderators before normal participants)
		 * - online status
		 * - display name
		 *
		 * @param {object} participant1 First participant
		 * @param {int} participant1.participantType First participant type
		 * @param {string} participant1.sessionId First participant session
		 * @param {string} participant1.displayName First participant display name
		 * @param {object} participant2 Second participant
		 * @param {int} participant2.participantType Second participant type
		 * @param {string} participant2.sessionId Second participant session
		 * @param {string} participant2.displayName Second participant display name
		 * @returns {number}
		 */
		sortParticipants(participant1, participant2) {
			const moderatorTypes = [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]
			const moderator1 = moderatorTypes.indexOf(participant1.participantType) !== -1
			const moderator2 = moderatorTypes.indexOf(participant2.participantType) !== -1

			if (moderator1 !== moderator2) {
				return moderator1 ? -1 : 1
			}

			if (participant1.sessionId === '0') {
				if (participant2.sessionId !== '0') {
					return 1
				}
			} else if (participant2.sessionId === '0') {
				return -1
			}

			return participant1.displayName.localeCompare(participant2.displayName)
		},
	},
}
</script>

<style scoped>

</style>
