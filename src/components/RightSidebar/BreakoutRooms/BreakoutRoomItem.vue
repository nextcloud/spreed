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
	<li :key="roomName"
		class="breakout-room-item"
		@mouseenter="elementHoveredOrFocused = true"
		@mouseleave="elementHoveredOrFocused = false">
		<div class="breakout-room-item__wrapper">
			<NcButton type="tertiary-no-background"
				:aria-label="toggleParticipantsListLabel"
				@focus="elementHoveredOrFocused = true"
				@blur="elementHoveredOrFocused = false"
				@click="toggleParticipantsVisibility">
				<template #icon>
					<DotsCircle v-if="!elementHoveredOrFocused" :size="20" />
					<MenuRight v-else-if="!showParticipants" :size="20" />
					<MenuDown v-else :size="20" />
				</template>
			</NcButton>
			<span class="breakout-room-item__room-name">
				{{ roomName }}
			</span>
			<NcButton v-if="showJoinButton" @click="joinRoom">
				{{ t('spreed', 'Join') }}
			</NcButton>
			<NcActions v-if="canModerate" :force-menu="true">
				<NcActionButton v-if="showAssistanceButton"
					@click="dismissRequestAssistance">
					<template #icon>
						<HandBackLeft :size="16" />
					</template>
					{{ t('spreed', 'Dismiss request for assistance') }}
				</NcActionButton>
				<NcActionButton @click="openSendMessageForm">
					<template #icon>
						<Send :size="16" />
					</template>
					{{ t('spreed', 'Send message to room') }}
				</NcActionButton>
			</NcActions>
		</div>
		<ul v-show="showParticipants">
			<template v-for="participant in roomParticipants">
				<Participant :key="participant.actorId" :participant="participant" />
			</template>
		</ul>
		<!-- Send message dialog -->
		<SendMessageDialog v-if="isDialogOpened"
			:display-name="roomName"
			:token="roomToken"
			@close="closeSendMessageForm" />
	</li>
</template>

<script>
import DotsCircle from 'vue-material-design-icons/DotsCircle.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import MenuDown from 'vue-material-design-icons/MenuDown.vue'
import MenuRight from 'vue-material-design-icons/MenuRight.vue'
import Send from 'vue-material-design-icons/Send.vue'

import { showWarning } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import SendMessageDialog from '../../BreakoutRoomsEditor/SendMessageDialog.vue'
import Participant from '../Participants/ParticipantsList/Participant/Participant.vue'

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import { EventBus } from '../../../services/EventBus.js'

export default {
	name: 'BreakoutRoomItem',

	components: {
		// Components
		NcActionButton,
		NcActions,
		NcButton,
		Participant,
		SendMessageDialog,

		// Icons
		DotsCircle,
		HandBackLeft,
		MenuDown,
		MenuRight,
		Send,
	},

	props: {
		breakoutRoom: {
			type: Object,
			required: true,
		},
		mainConversation: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			showParticipants: true,
			isDialogOpened: false,
			elementHoveredOrFocused: false,
		}
	},

	computed: {
		participantType() {
			return this.breakoutRoom.participantType
		},

		roomName() {
			return this.breakoutRoom.displayName
		},

		roomToken() {
			return this.breakoutRoom.token
		},

		showJoinButton() {
			return this.roomToken !== this.$store.getters.getToken()
		},

		roomParticipants() {
			return this.$store.getters.participantsList(this.roomToken)
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

		toggleParticipantsListLabel() {
			return this.showParticipants
				? t('spreed', 'Hide list of participants')
				: t('spreed', 'Show list of participants')
		},
	},

	watch: {
		showAssistanceButton(newValue) {
			if (newValue) {
				showWarning(t('spreed', 'Assistance requested in {roomName}', {
					roomName: this.roomName,
				}))
			}
		},
	},

	methods: {
		openSendMessageForm() {
			this.isDialogOpened = true
		},

		closeSendMessageForm() {
			this.isDialogOpened = false
		},

		dismissRequestAssistance() {
			this.$store.dispatch('resetRequestAssistanceAction', { token: this.roomToken })
		},

		async joinRoom() {
			if (this.canModerate) {
				EventBus.$emit('switch-to-conversation', {
					token: this.roomToken,
				})
			} else {
				try {
					if (this.mainConversation.breakoutRoomMode === CONVERSATION.BREAKOUT_ROOM_MODE.FREE) {
						await this.$store.dispatch('switchToBreakoutRoomAction', {
							token: this.$store.getters.parentRoomToken(this.roomToken),
							target: this.roomToken,
						})
					}
					EventBus.$emit('switch-to-conversation', {
						token: this.roomToken,
					})
				} catch (error) {
					console.debug(error)
				}
			}
		},

		toggleParticipantsVisibility() {
			this.showParticipants = !this.showParticipants
		},
	},
}
</script>

<style lang="scss" scoped>
.breakout-room-item {
	margin-top: calc(var(--default-grid-baseline)*5);
	font-weight: bold;

	&__wrapper {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
	}

	&__room-name {
		margin-right: auto;
	}
}
</style>
