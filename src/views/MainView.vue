<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar :force-white-icons="showChatInSidebar" />
			<ChatView v-if="!showChatInSidebar" :token="token" />
			<template v-else>
				<GridView :grid-width="mainViewWidth" :grid-height="mainViewHeight" />
			</template>
		</template>
	</div>
</template>

<script>
import GridView from '../components/GridView/GridView'
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
		GridView,
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
			mainViewWidth: 0,
			mainViewHeight: 0,
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
		mainView() {
			return document.getElementsByClassName('main-view')[0]
		},
		sidebarStatus() {
			return this.$store.getters.getSidebarStatus
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
		sidebarStatus: () => {
			this.$nextTick(() => {
				this.handleResize()
			})
		},
	},
	// bind event handlers to the `handleResize` method
	mounted() {
		window.addEventListener('resize', this.handleResize)
		this.handleResize()

	},
	beforeDestroy() {
		window.removeEventListener('resize', this.handleResize)
	},

	methods: {
		// whenever the document is resized, re-set the 'clientWidth' variable
		handleResize(event) {
			if (this.mainView) {
				this.mainViewWidth = this.mainView.clientWidth
				this.mainViewHeight = this.mainView.clientHeight
			}
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
			// FIXME reset the settings so we can check it later on if loading is finished
			this.signaling.loadSettings(token)
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
