<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

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
