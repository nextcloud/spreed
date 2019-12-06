<template>
	<div class="chatview">
		<TopBar />

		<template v-if="!showChatInSidebar">
			<MessagesList :token="token" />
			<NewMessageForm />
		</template>
		<template v-else>
			<CallView :token="token" />
		</template>
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView'
import MessagesList from '../components/MessagesList/MessagesList'
import NewMessageForm from '../components/NewMessageForm/NewMessageForm'
import TopBar from '../components/TopBar/TopBar'
import { PARTICIPANT } from '../constants'

export default {
	name: 'ChatView',
	components: {
		CallView,
		MessagesList,
		NewMessageForm,
		TopBar,
	},
	props: {
		token: {
			type: String,
			required: true,
		},
	},

	computed: {
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
}
</script>

<style lang="scss" scoped>
.chatview {
	height: 100%;
	display: flex;
	flex-grow: 1;
	flex-direction: column;
	align-content: space-between;
}
</style>
