<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar
				:is-in-call="showChatInSidebar" />
			<transition name="fade">
				<ChatView v-if="!showChatInSidebar" />
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
import isInLobby from '../mixins/isInLobby'
import isInCall from '../mixins/isInCall'
import participant from '../mixins/participant'

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
		isInLobby: function(isInLobby) {
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

.main-view {
	height: 100%;
	width: 100%;
	display: flex;
	flex-grow: 1;
	flex-direction: column;
	align-content: space-between;
}
</style>
