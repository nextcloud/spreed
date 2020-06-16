<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar
				:is-in-call="showChatInSidebar" />
			<transition name="fade">
				<ChatView v-if="!showChatInSidebar" :token="token" />
				<template v-else>
					<CallView
						:token="token" />
				</template>
			</transition>
		</template>
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView'
import ChatView from '../components/ChatView'
import LobbyScreen from '../components/LobbyScreen'
import TopBar from '../components/TopBar/TopBar'
import { PARTICIPANT } from '../constants'
import isInLobby from '../mixins/isInLobby'
import SessionStorage from '../services/SessionStorage'

export default {
	name: 'MainView',
	components: {
		ChatView,
		LobbyScreen,
		TopBar,
		CallView,
	},

	mixins: [
		isInLobby,
	],
	props: {
		token: {
			type: String,
			required: true,
		},
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		participant() {
			if (typeof this.token === 'undefined') {
				return {
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				}
			}

			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			if (participantIndex !== -1) {
				return this.$store.getters.getParticipant(this.token, participantIndex)
			}

			return {
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}
		},

		showChatInSidebar() {
			return SessionStorage.getItem('joined_conversation') === this.token
				&& this.participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},
	},

	watch: {
		isInLobby: function(isInLobby) {
			// User is now blocked by the lobby
			if (isInLobby && this.participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				this.$store.dispatch('leaveCall', {
					token: this.token,
					participantIdentifier: this.$store.getters.getParticipantIdentifier(),
				})
			}
		},
	},

}
</script>

<style lang="scss" scoped>

.main-view {
	height: 100%;
	width: 100%;
	display: flex;
	flex-grow: 1;
	flex-direction: column;
	align-content: space-between;
}
</style>
