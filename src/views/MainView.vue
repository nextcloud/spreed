<template>
	<div class="chatview">
		<TopBar
			:force-white-icons="showChatInSidebar"
			:signaling-initialised="signalingInitialised" />

		<template v-if="!showChatInSidebar">
			<MessagesList :token="token" />
			<NewMessageForm />
		</template>
		<template v-else>
			<CallView
				:token="token"
				:signaling-server="signalingServer"
				:signaling-ticket="signalingTicket"
				:stun-servers="stunServers"
				:turn-servers="turnServers" />
		</template>
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView'
import MessagesList from '../components/MessagesList/MessagesList'
import NewMessageForm from '../components/NewMessageForm/NewMessageForm'
import TopBar from '../components/TopBar/TopBar'
import { PARTICIPANT } from '../constants'
import { fetchSignalingSettings } from '../services/signalingService'

export default {
	name: 'MainView',

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

	data() {
		return {
			signalingInitialised: false,

			showSignalingWarning: false, // FIXME use
			signalingServer: [],
			signalingTicket: '',
			stunServers: [],
			turnServers: [],
		}
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

	created() {
		this.loadSignalingSettings()
	},

	methods: {
		async loadSignalingSettings() {
			this.signalingInitialised = false
			try {
				const response = await fetchSignalingSettings(this.token)
				const data = response.data.ocs.data
				this.showSignalingWarning = data.hideWarning
				this.signalingServer = data.server
				this.signalingTicket = data.ticket
				this.stunServers = data.stunservers
				this.turnServers = data.turnservers

				this.signalingInitialised = true
			} catch (exception) {
				console.error('Error fetching signaling information', exception)
			}
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
