<template>
	<div class="main-view">
		<LobbyScreen v-if="isInLobby" />
		<template v-else>
			<TopBar :is-in-call="isInCall" />
			<transition name="fade">
				<div class="main-view__wrapper"
					:class="{'in-call': isInCall}">
					<ChatView v-if="!isInCall" />
					<template v-else>
						<CallView :token="token" />
					</template>
					<RightSidebar />
				</div>
			</transition>
		</template>
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView.vue'
import ChatView from '../components/ChatView.vue'
import LobbyScreen from '../components/LobbyScreen.vue'
import RightSidebar from '../components/RightSidebar/RightSidebar.vue'
import TopBar from '../components/TopBar/TopBar.vue'

import isInCall from '../mixins/isInCall.js'
import isInLobby from '../mixins/isInLobby.js'
import participant from '../mixins/participant.js'

export default {
	name: 'MainView',
	components: {
		ChatView,
		LobbyScreen,
		RightSidebar,
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

	&__wrapper {
		display: flex;
		width: 100%;
		height: calc(100% - 61px);
		overflow: hidden;

		&.in-call {
			height: 100%;
			background-color: $color-call-background;
			backdrop-filter: blur(25px);
		}
	}
}
</style>
