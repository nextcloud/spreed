<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
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
			<NcActionButton v-if="showAssistanceButton"
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

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import Participant from '../Participants/ParticipantsList/Participant/Participant.vue'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import SendMessageDialog from '../../BreakoutRoomsEditor/SendMessageDialog.vue'
import GoogleCircles from 'vue-material-design-icons/GoogleCircles.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import Send from 'vue-material-design-icons/Send.vue'

import { showWarning } from '@nextcloud/dialogs'

// Constants
import { CONVERSATION, PARTICIPANT } from '../../../constants.js'

export default {
	name: 'BreakoutRoomItem',

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
		breakoutRoom: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			openedDialog: undefined,
		}
	},

	computed: {
		participantType() {
			return this.breakoutRoom.participantType
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canModerate() {
			return this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR
		},

		showAssistanceButton() {
			return this.canModerate && this.breakoutRoom.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STATUS_ASSISTANCE_REQUESTED
		},
	},

	watch: {
		showAssistanceButton(newValue) {
			if (newValue) {
				showWarning(t('spreed', 'Assistance requested in {roomName}', { roomName: this.breakoutRoom.displayName }))
			}
		},
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

<style scoped>

</style>
