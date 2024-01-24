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
			<template v-if="!isParticipantsEditor">
				<NcButton v-if="showJoinButton" @click="joinRoom">
					{{ t('spreed', 'Join') }}
				</NcButton>
				<NcActions v-if="canModerate"
					:container="container"
					:inline="showAssistanceButton ? 1 : 0"
					:force-menu="!showAssistanceButton">
					<NcActionButton v-if="showAssistanceButton"
						@click="dismissRequestAssistance">
						<template #icon>
							<HandBackLeft :size="16" />
						</template>
						{{ t('spreed', 'Dismiss request for assistance') }}
					</NcActionButton>
					<NcActionButton @click="openSendMessageDialog">
						<template #icon>
							<Send :size="16" />
						</template>
						{{ t('spreed', 'Send message to room') }}
					</NcActionButton>
				</NcActions>
				<!-- Send message dialog -->
				<SendMessageDialog v-if="isDialogOpened"
					:display-name="roomName"
					:token="roomToken"
					@close="closeSendMessageDialog" />
			</template>
		</div>
		<ul v-show="showParticipants">
			<!-- Participants slot -->
			<slot />
		</ul>
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

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import { EventBus } from '../../../services/EventBus.js'

export default {
	name: 'BreakoutRoomItem',

	components: {
		// Components
		NcActionButton,
		NcActions,
		NcButton,
		SendMessageDialog,

		// Icons
		DotsCircle,
		HandBackLeft,
		MenuDown,
		MenuRight,
		Send,
	},

	props: {
		/**
		 * This prop is only populated when the component is used in the participants editor
		 */
		name: {
			type: String,
			default: undefined,
		},

		/**
		 * This prop is only populated when the component is used in the right sidebar
		 */
		breakoutRoom: {
			type: Object,
			default: undefined,
		},

		/**
		 * This prop is only populated when the component is used in the right sidebar
		 */
		mainConversation: {
			type: Object,
			default: undefined,
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
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		participantType() {
			return this.breakoutRoom.participantType
		},

		roomName() {
			return this.isParticipantsEditor ? this.name : this.breakoutRoom.displayName
		},

		roomToken() {
			return this.breakoutRoom.token
		},

		showJoinButton() {
			return this.roomToken !== this.$store.getters.getToken()
		},

		canFullModerate() {
			return !this.isParticipantsEditor && (this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR)
		},

		canModerate() {
			if (this.isParticipantsEditor) {
				return false
			}
			return this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR
		},

		showAssistanceButton() {
			if (this.isParticipantsEditor) {
				return false
			}
			return this.canModerate && this.breakoutRoom.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STATUS_ASSISTANCE_REQUESTED
		},

		toggleParticipantsListLabel() {
			return this.showParticipants
				? t('spreed', 'Hide list of participants')
				: t('spreed', 'Show list of participants')
		},

		// True if this component is being used by the ParticipantsEditor component
		isParticipantsEditor() {
			return this.name !== undefined
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
		openSendMessageDialog() {
			this.isDialogOpened = true
		},

		closeSendMessageDialog() {
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
		margin : 0 var(--default-grid-baseline);
	}

	&__room-name {
		margin : 0 auto 0 var(--default-grid-baseline);
	}
}
</style>
