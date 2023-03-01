<template>
	<!-- Series of buttons at the top of the tab, these affect all
		 breakout rooms -->
	<div v-if="canModerate || isInBreakoutRoom" class="breakout-rooms__actions">
		<div class="breakout-rooms__actions-group">
			<NcButton v-if="breakoutRoomsNotStarted && canModerate"
				:title="t('spreed', 'Start breakout rooms')"
				:aria-label="t('spreed', 'Start breakout rooms')"
				type="tertiary"
				:disabled="!isInCall"
				@click="startBreakoutRooms">
				<template #icon>
					<Play :size="20" />
				</template>
			</NcButton>
			<NcButton v-else-if="canModerate"
				:title="t('spreed', 'Stop breakout rooms')"
				:aria-label="t('spreed', 'Stop breakout rooms')"
				type="tertiary"
				@click="stopBreakoutRooms">
				<template #icon>
					<StopIcon :size="20" />
				</template>
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
			<NcButton v-if="canModerate"
				:title="t('spreed', 'Send message to breakout rooms')"
				:aria-label="t('spreed', 'Send message to breakout rooms')"
				type="tertiary"
				@click="openSendMessageDialog">
				<template #icon>
					<Message :size="18" />
				</template>
			</NcButton>
		</div>
		<div v-if="canModerate" class="breakout-rooms__actions-group right">
			<!-- Re-arrange participants button -->
			<NcButton type="tertiary"
				:title="moveParticipantsButtonTitle"
				:aria-label="moveParticipantsButtonTitle"
				@click="openParticipantsEditor">
				<template #icon>
					<AccountMultiple :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="breakoutRoomsConfigured"
				:title="t('spreed', 'Delete breakout rooms')"
				:aria-label="t('spreed', 'Delete breakout rooms')"
				type="tertiary"
				@click="deleteBreakoutRooms">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</div>
		<!-- Participants editor -->
		<NcModal v-if="showParticipantsEditor"
			@close="closeParticipantsEditor">
			<div class="breakout-rooms__editor">
				<h2> {{ moveParticipantsButtonTitle }} </h2>
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
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Message from 'vue-material-design-icons/Message.vue'
import Play from 'vue-material-design-icons/Play.vue'
import StopIcon from 'vue-material-design-icons/Stop.vue'

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

		// Icons
		Delete,
		Play,
		AccountMultiple,
		StopIcon,
		Message,
		ArrowLeft,

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

		moveParticipantsButtonTitle() {
			return t('spreed', 'Reorganize participants')
		},

		backToMainRoomLabel() {
			return t('spreed', 'Back to main room')
		},

		participantType() {
			return this.conversation.participantType
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

		deleteBreakoutRooms() {
			OC.dialogs.confirmDestructive(
				t('spreed', 'Current breakout rooms and settings will be lost'),
				t('spreed', 'Delete breakout rooms'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('spreed', 'Delete breakout rooms'),
					confirmClasses: 'error',
					cancel: t('spreed', 'Cancel'),
				},
				(decision) => {
					if (!decision) {
						return
					}
					this.$store.dispatch('deleteBreakoutRoomsAction', {
						token: this.token,
					})
				}
			)
		},
	},
}
</script>

<style lang="scss" scoped>
.breakout-rooms {
	&__actions {
		display: flex;
		justify-content: space-between;
		margin-bottom: calc(var(--default-grid-baseline) * 3);
	}

	&__actions-group {
		display: flex;
		gap: var(--default-grid-baseline);
		flex-grow: 1;
	}

	&__editor {
		padding: 20px;
	}
}

.right {
	justify-content: right;
}
</style>
