<template>
	<div class="breakout-rooms">
		<template v-for="breakoutRoom in breakoutRooms">
			<NcAppNavigationItem :key="breakoutRoom.displayName"
				:title="breakoutRoom.displayName"
				:allow-collapse="true">
				<template #icon>
					<!-- TODO: choose final icon -->
					<GoogleCircles :size="20" />
				</template>
				<template v-for="participant in $store.getters.participantsList(breakoutRoom.token)">
					<Participant :key="participant.actorId" :participant="participant" />
				</template>
			</NcAppNavigationItem>
		</template>
	</div>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import Participant from '../Participants/ParticipantsList/Participant/Participant.vue'
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'

export default {
	name: 'BreakoutRoomsTab',

	components: {
		NcAppNavigationItem,
		Participant,
		GoogleCircles,
	},

	computed: {
		// TODO: get actual rooms
		breakoutRooms() {
			return [
				this.$store.getters.conversation('zsn49dx9'),
				this.$store.getters.conversation('py2qhwa7'),
				this.$store.getters.conversation('sngyetkc'),
			]
		},
	},

	watch: {
		breakoutRooms() {
			this.$forceUpdate()
		},
	},
}
</script>

<style lang="scss" scoped>

::v-deep .app-navigation-entry__title {
	font-weight: bold !important;
}

// TODO: upsteream collapse icon position fix
::v-deep .icon-collapse {
	position: absolute !important;
	left: 0;
}
</style>
