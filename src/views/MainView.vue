<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar :is-in-call="showChatInSidebar" />
			<transition name="fade">
				<ChatView v-if="!showChatInSidebar" />
				<template v-else>
					<CallView :token="token" />
				</template>
			</transition>
		</template>
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView.vue'
import ChatView from '../components/ChatView.vue'
import LobbyScreen from '../components/LobbyScreen.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import isInLobby from '../mixins/isInLobby.js'
import isInCall from '../mixins/isInCall.js'
import participant from '../mixins/participant.js'

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
		isInCall,
		participant,
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

		showChatInSidebar() {
			return this.isInCall
		},
	},

	watch: {
		isInLobby(isInLobby) {
			// User is now blocked by the lobby
			if (isInLobby && this.isInCall) {
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
@import '../assets/variables';

.main-view {
	height: 100%;
	width: 100%;
	display: flex;
	flex-grow: 1;
	flex-direction: column;
	align-content: space-between;
	position: relative;
}
</style>
