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
		<ParticipantsList v-if="participantsList.length"
			:items="participantsList"
			:loading="!participantsInitialised" />
		<Hint v-else :hint="t('spreed', 'No search results')" />
	</div>
</template>

<script>

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import Hint from '../../../Hint.vue'
import ParticipantsList from '../ParticipantsList/ParticipantsList.vue'

import { useSortParticipants } from '../../../../composables/useSortParticipants.js'

export default {
	name: 'CurrentParticipants',

	components: {
		ParticipantsList,
		Hint,
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

	setup() {
		const { sortParticipants } = useSortParticipants()

		return {
			sortParticipants,
		}
	},

	computed: {
		token() {
			return this.$store.getters.getToken()
		},
		/**
		 * Gets the participants array.
		 *
		 * @return {Array}
		 */
		participants() {
			let participants = this.$store.getters.participantsList(this.token)

			if (this.searchText !== '') {
				const lowerSearchText = this.searchText.toLowerCase()
				participants = participants.filter(participant => {
					return participant.displayName.toLowerCase().includes(lowerSearchText)
						|| (participant.actorType !== 'guests'
							&& participant.actorId.toLowerCase().includes(lowerSearchText))
				})
			}

			return participants
		},

		participantsList() {
			return this.participants.slice().sort(this.sortParticipants)
		},
	},

	mounted() {
		subscribe('user_status:status.updated', this.userStatusUpdated)
	},

	beforeDestroy() {
		unsubscribe('user_status:status.updated', this.userStatusUpdated)
	},

	methods: {
		userStatusUpdated(state) {
			if (this.token && this.participantsList.find(participant => participant.actorId === state.userId)) {
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
			}
		},
	},
}
</script>
