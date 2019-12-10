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
import { restartInternalSignaling, stopInternalSignaling } from '../../services/signaling/internalSignalingService'
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
		signalingServer: {
			type: Array,
			required: true,
		},
		signalingTicket: {
			type: String,
			required: true,
		},
		stunServers: {
			type: Array,
			required: true,
		},
		turnServers: {
			type: Array,
			required: true,
		},
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

		isUsingExternalSignaling() {
			return this.signalingServer
				&& this.signalingServer.urls
				&& this.signalingServer.urls.length > 0
		},
	},

	created() {
		if (!this.isUsingExternalSignaling) {
			console.error(this.token)
			restartInternalSignaling(this.token)
			EventBus.$on('routeChange', () => {
				restartInternalSignaling(this.token)
			})
		}
	},

	beforeDestroy() {
		if (!this.isUsingExternalSignaling) {
			stopInternalSignaling()
		}
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
