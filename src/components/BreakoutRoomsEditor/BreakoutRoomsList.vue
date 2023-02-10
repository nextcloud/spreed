<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div>
		<template v-for="breakoutRoom in breakoutRooms">
			<NcAppNavigationItem :key="breakoutRoom.displayName"
				class="breakout-rooms__room"
				:title="breakoutRoom.displayName"
				:allow-collapse="true"
				:inline-actions="1"
				:open="true">
				<template #icon>
					<!-- TODO: choose final icon -->
					<GoogleCircles :size="20" />
				</template>
				<template #actions>
					<NcActionButton v-if="breakoutRoom.breakoutRoomStatus ===
							CONVERSATION.BREAKOUT_ROOM_STATUS.STATUS_ASSISTANCE_REQUESTED"
						@click="dismissRequestAssistance(breakoutRoom.token)">
						<template #icon>
							<HandBackLeft :size="16" />
						</template>
						{{ t('spreed', 'Dismiss request for assistance') }}
					</NcActionButton>
					<NcActionButton @click="openSendMessageForm(breakoutRoom.token)">
						<template #icon>
							<Send :size="16" />
						</template>
						{{ t('spreed', 'Send message to room') }}
					</NcActionButton>
				</template>
				<!-- Send message dialog -->
				<SendMessageDialog v-if="openedDialog === breakoutRoom.token"
					:display-name="breakoutRoom.displayName"
					:token="breakoutRoom.token"
					@close="closeSendMessageForm(breakoutRoom.token)" />
				<template v-for="participant in $store.getters.participantsList(breakoutRoom.token)">
					<Participant :key="participant.actorId" :participant="participant" />
				</template>
			</NcAppNavigationItem>
		</template>
	</div>
</template>

<script>

// Components
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import Participant from '../RightSidebar/Participants/ParticipantsList/Participant/Participant.vue'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import SendMessageDialog from './SendMessageDialog.vue'

// Icons
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import Send from 'vue-material-design-icons/Send.vue'

// Constants
import { CONVERSATION } from '../../constants.js'

export default {
	name: 'BreakoutRoomsList',

	components: {
		// Components
		NcAppNavigationItem,
		Participant,
		NcActionButton,
		SendMessageDialog,

		// Icons
		GoogleCircles,
		HandBackLeft,
		Send,
	},

	props: {
		breakoutRooms: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			openedDialog: undefined,
			CONVERSATION,
		}
	},

	methods: {
		openSendMessageForm(token) {
			this.openedDialog = token
		},

		closeSendMessageForm() {
			this.openedDialog = undefined
		},

		dismissRequestAssistance(token) {
			this.$store.dispatch('resetRequestAssistanceAction', { token })
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
