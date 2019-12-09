<template>
	<div class="stage">
		<div class="promoted-stream" />

		<div class="video-container-row">
			<div class="own-video">
				<Avatar v-if="actorType === 'users'"
					class="messages__avatar__icon"
					:user="actorId"
					:display-name="displayName"
					:size="128" />
				<div v-else
					class="avatar guest">
					{{ firstLetterOfGuestName }}
				</div>

				<MediaControls />
			</div>
		</div>
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import MediaControls from './MediaControls'
import { fetchSignalingSettings } from '../../services/signalingService'
import { resetInternalSignaling } from '../../services/signaling/internalSignalingService'
import { EventBus } from '../../services/EventBus'

export default {
	name: 'CallView',

	components: {
		Avatar,
		MediaControls,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			showSignalingWarning: false,
			turnServers: [],
			stunServers: [],
			signalingServer: [],
			signalingTicket: '',
		}
	},

	computed: {
		actorType() {
			return this.$store.getters.getActorType()
		},
		actorId() {
			return this.$store.getters.getActorId()
		},
		displayName() {
			return this.$store.getters.getDisplayName()
		},

		firstLetterOfGuestName() {
			const customName = this.displayName !== t('spreed', 'Guest') ? this.displayName : '?'
			return customName.charAt(0)
		},
	},

	created() {
		resetInternalSignaling(this.token)
		EventBus.$on('routeChange', () => {
			resetInternalSignaling(this.token)
		})
	},

	methods: {
		loadSignalingSettings() {
			// FIXME move to MainView, so it's ready loaded when we start the call
			try {
				const response = fetchSignalingSettings(this.token)
				this.showSignalingWarning = response.ocs.data.hideWarning
				this.turnServers = response.ocs.data.turnserver
				this.stunServers = response.ocs.data.stunserver
				this.signalingServer = response.ocs.data.server
				this.signalingTicket = response.ocs.data.ticket
			} catch (exception) {
				console.error('Error fetching signaling information', exception)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.stage {
	background: #000000;
	width: 100%;
	height: 100%;

	.promoted-stream {
		height: auto;
	}

	.video-container-row {
		height: 180px;
		position: absolute;
		bottom: 0;
		width: 100%;

		.own-video {
			float: right;
		}
	}
}
</style>
