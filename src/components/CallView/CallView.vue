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

export default {
	name: 'CallView',

	components: {
		Avatar,
		MediaControls,
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
