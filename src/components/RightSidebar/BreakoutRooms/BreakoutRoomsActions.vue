<template>
	<!-- Series of buttons at the top of the tab, these affect all
		 breakout rooms -->
	<div v-if="canModerate || isInBreakoutRoom" class="breakout-rooms-actions">
		<div class="breakout-rooms-actions__row">
			<NcButton v-if="breakoutRoomsNotStarted && canModerate"
				:title="startLabel"
				:aria-label="startLabel"
				type="primary"
				:wide="true"
				:disabled="!isInCall"
				@click="startBreakoutRooms">
				<template #icon>
					<Play :size="20" />
				</template>
				{{ startLabel }}
			</NcButton>
			<NcButton v-else-if="canModerate"
				:title="stopLabel"
				:aria-label="stopLabel"
				type="error"
				:wide="true"
				@click="stopBreakoutRooms">
				<template #icon>
					<StopIcon :size="20" />
				</template>
				{{ stopLabel }}
			</NcButton>
		</div>
		<div class="breakout-rooms-actions__row">
			<NcButton v-if="canModerate && !isInBreakoutRoom"
				:title="sendMessageLabel"
				:aria-label="sendMessageLabel"
				type="secondary"
				:wide="true"
				@click="openSendMessageDialog">
				<template #icon>
					<Send :size="18" />
				</template>
				{{ sendMessageLabel }}
			</NcButton>
			<NcButton v-if="isInBreakoutRoom"
				:title="backToMainRoomLabel"
				:aria-label="backToMainRoomLabel"
				:wide="true"
				type="secondary"
				@click="switchToParentRoom">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
				{{ backToMainRoomLabel }}
			</NcButton>
			<NcActions v-if="canModerate" class="right">
				<NcActionButton v-if="canModerate && isInBreakoutRoom"
					:title="sendMessageLabel"
					:aria-label="sendMessageLabel"
					@click="openSendMessageDialog">
					<template #icon>
						<Send :size="20" />
					</template>
				</NcActionButton>
				<NcActionButton v-if="canModerate"
					:title="manageBreakoutRoomsTitle"
					:aria-label="manageBreakoutRoomsTitle"
					@click="openParticipantsEditor">
					<template #icon>
						<Cog :size="20" />
					</template>
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Participants editor -->
		<NcModal v-if="showParticipantsEditor"
			@close="closeParticipantsEditor">
			<div class="breakout-rooms-actions__editor">
				<h2> {{ manageBreakoutRoomsTitle }} </h2>
				<BreakoutRoomsParticipantsEditor :token="token"
					:breakout-rooms="breakoutRooms"
					:is-creating-rooms="false"
					@close="closeParticipantsEditor"
					v-on="$listeners" />
			</div>
		</NcModal>

		<!-- Send message dialog -->
		<SendMessageDialog v-if="sendMessageDialogOpened"
			:token="token"
			:broadcast="true"
			@close="closeSendMessageDialog" />
	</div>
</template>

<script>
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Play from 'vue-material-design-icons/Play.vue'
import Send from 'vue-material-design-icons/Send.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import BreakoutRoomsParticipantsEditor from '../../BreakoutRoomsEditor/BreakoutRoomsParticipantsEditor.vue'
import SendMessageDialog from '../../BreakoutRoomsEditor/SendMessageDialog.vue'

import { CONVERSATION, PARTICIPANT } from '../../../constants.js'
import isInCall from '../../../mixins/isInCall.js'
import { EventBus } from '../../../services/EventBus.js'

export default {
	name: 'BreakoutRoomsActions',

	components: {
		// Components
		NcButton,
		BreakoutRoomsParticipantsEditor,
		SendMessageDialog,
		NcModal,
		NcActions,
		NcActionButton,

		// Icons
		Play,
		Cog,
		StopIcon,
		ArrowLeft,
		Send,
	},

	mixins: [isInCall],

	props: {
		token: {
			type: String,
			required: true,
		},

		conversation: {
			type: Object,
			required: true,
		},

		breakoutRooms: {
			type: Array,
			required: true,
		},

		breakoutRoomsConfigured: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			showParticipantsEditor: false,
			sendMessageDialogOpened: false,
		}
	},

	computed: {
		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		canModerate() {
			return !this.isOneToOne && (this.canFullModerate || this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		breakoutRoomsNotStarted() {
			if (this.isInBreakoutRoom) {
				return this.parentRoom.breakoutRoomStatus !== CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
			} else {
				return this.conversation.breakoutRoomStatus !== CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
			}
		},

		isInBreakoutRoom() {
			return this.conversation.objectType === 'room'
		},

		parentRoom() {
			if (!this.isInBreakoutRoom) {
				return undefined
			} else {
				return this.$store.getters.conversation(this.conversation.objectId)
			}
		},

		manageBreakoutRoomsTitle() {
			return t('spreed', 'Manage breakout rooms')
		},

		backToMainRoomLabel() {
			return t('spreed', 'Back to main room')
		},

		participantType() {
			return this.conversation.participantType
		},

		sendMessageLabel() {
			return t('spreed', 'Message all rooms')
		},

		startLabel() {
			return t('spreed', 'Start session')
		},

		stopLabel() {
			return t('spreed', 'Stop session')
		},
	},

	methods: {
		startBreakoutRooms() {
			if (this.isInBreakoutRoom) {
				this.$store.dispatch('startBreakoutRoomsAction', this.parentRoom.token)
			} else {
				this.$store.dispatch('startBreakoutRoomsAction', this.token)
			}

		},

		stopBreakoutRooms() {
			if (this.isInBreakoutRoom) {
				this.$store.dispatch('stopBreakoutRoomsAction', this.parentRoom.token)
			} else {
				this.$store.dispatch('stopBreakoutRoomsAction', this.token)
			}
		},

		openSendMessageDialog() {
			this.sendMessageDialogOpened = true
		},

		closeSendMessageDialog() {
			this.sendMessageDialogOpened = false
		},

		openParticipantsEditor() {
			this.showParticipantsEditor = true
		},

		closeParticipantsEditor() {
			this.showParticipantsEditor = false
		},

		async switchToParentRoom() {
			EventBus.$emit('switch-to-conversation', {
				token: this.parentRoom.token,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.breakout-rooms-actions {
	display: flex;
	flex-direction: column;
	margin-bottom: calc(var(--default-grid-baseline) * 3);

	&__row {
		display: flex;
		margin-bottom: var(--default-grid-baseline);
		gap: var(--default-grid-baseline);
	}

	&__editor {
		padding: 20px;
	}
}

.right {
	justify-content: right;
}
</style>
