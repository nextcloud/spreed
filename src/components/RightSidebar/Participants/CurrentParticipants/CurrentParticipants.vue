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
	<div>
		<ParticipantsList
			v-if="participantsList.length"
			:items="participantsList"
			:loading="!participantsInitialised" />
		<Hint v-else :hint="t('spreed', 'No search results')" />
	</div>
</template>

<script>

import ParticipantsList from '../ParticipantsList/ParticipantsList'
import { PARTICIPANT } from '../../../../constants'
import UserStatus from '../../../../mixins/userStatus'
import Hint from '../../../Hint'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'

export default {
	name: 'CurrentParticipants',

	components: {
		ParticipantsList,
		Hint,
	},

	mixins: [
		UserStatus,
	],

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
				participants = participants.filter(participant => {
					return participant.displayName.toLowerCase().indexOf(lowerSearchText) !== -1
						|| (participant.actorType !== 'guests'
							&& participant.actorId.toLowerCase().indexOf(lowerSearchText) !== -1)
				})
			}

			return participants.slice().sort(this.sortParticipants)
		},
	},

	mounted() {
		subscribe('user_status:status.updated', this.userStatusUpdated)
	},

	beforeDestroyed() {
		unsubscribe('user_status:status.updated', this.userStatusUpdated)
	},

	methods: {
		userStatusUpdated(state) {
			this.$store.dispatch('updateUser', {
				token: this.token,
				participantIdentifier: {
					actorType: 'users',
					actorId: state.userId,
				},
				updatedData: {
					status: state.status,
					statusIcon: state.icon,
					statusMessage: state.message,
				},
			})
		},

		/**
		 * Sort two participants by:
		 * - online status
		 * - in call
		 * - type (moderators before normal participants)
		 * - user status (dnd at the end)
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
			let session1 = participant1.sessionId
			let session2 = participant2.sessionId
			if (participant1.status === 'offline') {
				session1 = '0'
			}
			if (participant2.status === 'offline') {
				session2 = '0'
			}
			if (session1 === '0') {
				if (session2 !== '0') {
					return 1
				}
			} else if (session2 === '0') {
				return -1
			}

			const p1inCall = participant1.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
			const p2inCall = participant2.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
			if (p1inCall !== p2inCall) {
				return p1inCall ? -1 : 1
			}

			const moderatorTypes = [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]
			const moderator1 = moderatorTypes.indexOf(participant1.participantType) !== -1
			const moderator2 = moderatorTypes.indexOf(participant2.participantType) !== -1

			if (moderator1 !== moderator2) {
				return moderator1 ? -1 : 1
			}

			const participant1Away = this.isNotAvailable(participant1)
			const participant2Away = this.isNotAvailable(participant2)
			if (participant1Away !== participant2Away) {
				return participant1Away ? 1 : -1
			}

			return participant1.displayName.localeCompare(participant2.displayName)
		},
	},
}
</script>
