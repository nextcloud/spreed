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
	/**
	 * List of searched users or groups
	 */
	props: {
		items: {
			type: Array,
			required: true,
		},
	},

	computed: {
		CurrentConversationParticipants() {
			return this.$store.getters.participantsList
		},
		token() {
			return this.$route.params.token
		}
	},

	methods: {
		handleClickParticipant(participant) {
			try {
				const response = addParticipant(this.token, participant.id, participant.source)
				console.debug(response)
			} catch (exeption) {
				console.debug(exeption)
			}
		},
	},
}
</script>

<style scoped>

</style>
