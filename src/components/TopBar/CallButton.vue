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
	<button v-if="showStartCallButton"
		:disabled="startCallButtonDisabled"
		class="top-bar__button primary"
		@click="joinCall">
		{{ startCallLabel }}
	</button>
	<button v-else-if="showLeaveCallButton"
		class="top-bar__button primary"
		@click="leaveCall">
		{{ t('spreed', 'Leave call') }}
	</button>
</template>

<script>
import { CONVERSATION, PARTICIPANT, WEBINAR } from '../../constants'

export default {
	name: 'CallButton',

	computed: {
		token() {
			return this.$route.params.token
		},

		conversation() {
			if (this.$store.getters.conversations[this.token]) {
				return this.$store.getters.conversations[this.token]
			}
			return {
				participantFlags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				participantType: PARTICIPANT.TYPE.USER,
				readOnly: CONVERSATION.STATE.READ_ONLY,
				hasCall: false,
				canStartCall: false,
				lobbyState: WEBINAR.LOBBY.NONE,
			}
		},

		isBlockedByLobby() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
				&& this.isParticipantTypeModerator(this.conversation.participantType)
		},

		startCallButtonDisabled() {
			return (!this.conversation.canStartCall
					&& !this.conversation.hasCall)
				|| this.isBlockedByLobby
		},

		startCallLabel() {
			if (this.conversation.hasCall && !this.isBlockedByLobby) {
				return t('spreed', 'Join call')
			}

			return t('spreed', 'Start call')
		},

		showStartCallButton() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& this.conversation.participantFlags === PARTICIPANT.CALL_FLAG.DISCONNECTED
		},

		showLeaveCallButton() {
			return this.conversation.readOnly === CONVERSATION.STATE.READ_WRITE
				&& this.conversation.participantFlags !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},
	},

	methods: {
		isParticipantTypeModerator(participantType) {
			return [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].indexOf(participantType) !== -1
		},

		joinCall() {
			console.info('Join/start call')
		},

		leaveCall() {
			console.info('Leave call')
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
