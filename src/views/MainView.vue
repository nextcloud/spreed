<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar
				:is-in-call="showChatInSidebar"
				:is-grid="isGrid"
				@changeView="handleChangeView" />
			<transition name="fade">
				<ChatView v-if="!showChatInSidebar" :token="token" />
				<template v-else>
					<CallView
						:is-grid="isGrid"
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
	data() {
		return {
			isGrid: false,
		}
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
			return this.participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
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

	methods: {
		handleChangeView() {
			this.isGrid = !this.isGrid
		},
	},

	// FIXME reactivate once Signaling is done correctly per conversation again.
	/*
	watch: {
		token: function(token) {
			this.loadSignalingSettings(token)
		},
	},

	mounted() {
		this.signaling = Signaling
		this.loadSignalingSettings(this.token)
	},

	methods: {
		loadSignalingSettings(token) {
			console.debug('Loading signaling settings for ' + this.token)
			this.signaling.loadSettings(token)
			// FIXME reset the settings so we can check it later on if loading is finished
		},
	},
	*/
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
