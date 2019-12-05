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
		<ul>
			<Participant
				v-for="participant in participants"
				:key="participant.userId"
				:participant="participant"
				@clickParticipant="handleClickParticipant" />
		</ul>
	</div>
</template>

<script>

import Participant from './Participant/Participant'
import { addParticipant } from '../../../../services/participantsService'
import Vue from 'vue'

export default {
	name: 'ParticipantsList',

	components: {
		Participant,
	},

	props: {
		/**
		 * List of searched users or groups
		 */
		items: {
			type: Array,
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
	},

	data() {
		return {
			selectedParticipants: [],
		}
	},

	computed: {
		token() {
			return this.$route.params.token
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
		async handleClickParticipant(participant) {
			if (this.addOnClick) {
				/**
				 * Add the clicked participant to the current conversation
				 */
				try {
					await addParticipant(this.token, participant.id, participant.source)
					this.$emit('refreshCurrentParticipants')
				} catch (exeption) {
					console.debug(exeption)
				}
			} else {
				/**
				 * Remove the clicked participant from the selected participants list
				 */
				if (this.selectedParticipants.indexOf(participant) !== -1) {
					this.selectedParticipants = this.selectedParticipants.filter((selectedParticipant) => {
						if (selectedParticipant.id === participant.id) {
							return false
						} return true
					})
					this.$emit('updateSelectedParticipants', this.selectedParticipants)
				} else {
					/**
					 * Add the clicked participant from the selected participants list
					 */
					this.selectedParticipants = [...this.selectedParticipants, participant]
					this.$emit('updateSelectedParticipants', this.selectedParticipants)
				}
			}

		},
	},
}
</script>

<style scoped>

</style>
