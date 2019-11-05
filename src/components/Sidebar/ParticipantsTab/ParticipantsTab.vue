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
				v-for="participant in participantsList"
				:key="participant.userId"
				:user-id="participant.userId"
				:display-name="participant.displayName"
				:participant-type="participant.participantType"
				:last-ping="participant.lastPing"
				:session-id="participant.sessionId" />
		</ul>
	</div>
</template>

<script>

import Participant from './Participant'
import { fetchParticipants } from '../../../services/participantsService'
import { EventBus } from '../../../services/EventBus'

export default {
	name: 'ParticipantsTab',

	components: {
		Participant,
	},

	computed: {
		token() {
			return this.$route.params.token
		},

		/**
		 * Gets the participants array.
		 *
		 * @returns {array}
		 */
		participantsList() {
			return this.$store.getters.participantsList(this.token)
		},
	},

	/**
	 * Fetches the messages when the MessageList created. The router mounts this
	 * component only if the token is passed in so there's no need to check the
	 * token prop.
	 */
	created() {
		this.onRouteChange()

		/**
		 * Add a listener for routeChange event emitted by the App.vue component.
		 * Call the onRouteChange method function whenever the route changes.
		 */
		EventBus.$on('routeChange', () => {
			this.$nextTick(() => {
				this.onRouteChange()
			})
		})
	},

	methods: {
		onRouteChange() {
			this.getParticipants()
		},

		async getParticipants() {
			const participants = await fetchParticipants(this.token)
			this.$store.dispatch('purgeParticipantsStore', this.token)
			participants.data.ocs.data.forEach(participant => {
				this.$store.dispatch('addParticipant', {
					token: this.token,
					participant: participant,
				})
			})
		},
	},
}
</script>

<style scoped>

</style>
