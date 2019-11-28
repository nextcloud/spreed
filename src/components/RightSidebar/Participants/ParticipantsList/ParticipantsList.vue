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
				v-for="participant in items"
				:key="participant.userId"
				:participant="participant"
				@clickParticipant="handleClickParticipant" />
		</ul>
	</div>
</template>

<script>

import Participant from './Participant/Participant'
import { addParticipant } from '../../../../services/participantsService'

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
				if (this.selectedParticipants.indexOf(participant) !== -1) {
					/**
					 * Remove the clicked participant from the selected participants list
					 */
					this.selectedParticipants = [...this.selectedParticipants.slice(0, this.selectedParticipants.indexOf(participant)), ...this.selectedParticipants.slice(this.selectedParticipants.indexOf(participant), this.selectedParticipants.length)]
				} else {
					/**
					 * Add the clicked participant from the selected participants list
					 */
					this.selectedParticipants.push(participant)
				}
			}

		},
	},
}
</script>

<style scoped>

</style>
