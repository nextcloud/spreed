<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- Series of buttons at the top of the tab, these affect all
		 breakout rooms -->
	<div v-if="canModerate || isInBreakoutRoom" class="breakout-rooms-actions">
		<div class="breakout-rooms-actions__row">
			<NcButton v-if="breakoutRoomsNotStarted && canModerate"
				:title="startLabelTitle"
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
					<Check :size="20" />
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
				@click="isSendMessageDialogOpened = true">
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
					<IconArrowLeft class="bidirectional-icon" :size="20" />
				</template>
				{{ backToMainRoomLabel }}
			</NcButton>
			<NcButton v-else-if="!canModerate"
				:title="backToBreakoutRoomLabel"
				:aria-label="backToBreakoutRoomLabel"
				:wide="true"
				type="secondary"
				@click="switchToBreakoutRoom">
				<template #icon>
					<ArrowRight class="bidirectional-icon" :size="20" />
				</template>
				{{ backToBreakoutRoomLabel }}
			</NcButton>
			<NcActions v-if="canModerate" class="right">
				<NcActionButton v-if="canModerate && isInBreakoutRoom"
					:aria-label="sendMessageLabel"
					@click="isSendMessageDialogOpened = true">
					<template #icon>
						<Send :size="20" />
					</template>
					{{ sendMessageLabel }}
				</NcActionButton>
				<NcActionButton v-if="canModerate"
					:aria-label="manageBreakoutRoomsTitle"
					@click="openParticipantsEditor">
					<template #icon>
						<Cog :size="20" />
					</template>
					{{ manageBreakoutRoomsTitle }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Participants editor -->
		<NcModal v-if="showParticipantsEditor"
			:label-id="dialogHeaderId"
			@close="closeParticipantsEditor">
			<div class="breakout-rooms-actions__editor">
				<h2 :id="dialogHeaderId" class="nc-dialog-alike-header">
					{{ manageBreakoutRoomsTitle }}
				</h2>
				<BreakoutRoomsParticipantsEditor :token="mainToken"
					:breakout-rooms="breakoutRooms"
					:is-creating-rooms="false"
					@close="closeParticipantsEditor"
					v-on="$listeners" />
			</div>
		</NcModal>

		<!-- Send message dialog -->
		<SendMessageDialog v-if="isSendMessageDialogOpened"
			:token="mainToken"
			:dialog-title="t('spreed', 'Send a message to all breakout rooms')"
			:broadcast="true"
			@submit="broadcastMessage"
			@close="isSendMessageDialogOpened = false" />
	</div>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import Check from 'vue-material-design-icons/Check.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Play from 'vue-material-design-icons/Play.vue'
import Send from 'vue-material-design-icons/Send.vue'
import BreakoutRoomsParticipantsEditor from '../../BreakoutRoomsEditor/BreakoutRoomsParticipantsEditor.vue'
import SendMessageDialog from '../../BreakoutRoomsEditor/SendMessageDialog.vue'
import { useId } from '../../../composables/useId.ts'
import { useIsInCall } from '../../../composables/useIsInCall.js'
import { CONVERSATION, PARTICIPANT } from '../../../constants.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useBreakoutRoomsStore } from '../../../stores/breakoutRooms.ts'

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
		Check,
		IconArrowLeft,
		ArrowRight,
		Send,
	},

	props: {
		mainToken: {
			type: String,
			required: true,
		},

		mainConversation: {
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

	setup() {
		const showParticipantsEditor = ref(false)
		const isSendMessageDialogOpened = ref(false)
		const dialogHeaderId = `breakout-rooms-actions-header-${useId()}`

		return {
			isInCall: useIsInCall(),
			breakoutRoomsStore: useBreakoutRoomsStore(),
			showParticipantsEditor,
			isSendMessageDialogOpened,
			dialogHeaderId,
		}
	},

	computed: {
		canFullModerate() {
			return this.mainConversation.participantType === PARTICIPANT.TYPE.OWNER || this.mainConversation.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		isOneToOne() {
			return this.mainConversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.mainConversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		canModerate() {
			return !this.isOneToOne && (this.canFullModerate || this.mainConversation.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		breakoutRoomsNotStarted() {
			return this.mainConversation.breakoutRoomStatus !== CONVERSATION.BREAKOUT_ROOM_STATUS.STARTED
		},

		isInBreakoutRoom() {
			return this.mainToken !== this.$store.getters.getToken()
		},

		manageBreakoutRoomsTitle() {
			return t('spreed', 'Manage breakout rooms')
		},

		backToMainRoomLabel() {
			return t('spreed', 'Back to main room')
		},

		backToBreakoutRoomLabel() {
			return t('spreed', 'Back to your room')
		},

		sendMessageLabel() {
			return t('spreed', 'Message all rooms')
		},

		startLabel() {
			return t('spreed', 'Start session')
		},

		startLabelTitle() {
			if (this.isInCall) {
				return this.startLabel
			}
			return t('spreed', 'Start a call before you start a breakout room session')
		},

		stopLabel() {
			return t('spreed', 'Stop session')
		},
	},

	methods: {
		t,
		startBreakoutRooms() {
			this.breakoutRoomsStore.startBreakoutRooms(this.mainToken)
		},

		stopBreakoutRooms() {
			this.breakoutRoomsStore.stopBreakoutRooms(this.mainToken)
		},

		openParticipantsEditor() {
			this.showParticipantsEditor = true
		},

		closeParticipantsEditor() {
			this.showParticipantsEditor = false
		},

		async switchToParentRoom() {
			EventBus.emit('switch-to-conversation', {
				token: this.mainToken,
			})
		},

		async switchToBreakoutRoom() {
			EventBus.emit('switch-to-conversation', {
				token: this.mainToken,
			})
		},

		async broadcastMessage({ token, temporaryMessage, options }) {
			await this.breakoutRoomsStore.broadcastMessageToBreakoutRooms({ token, message: temporaryMessage.message })
			showSuccess(t('spreed', 'The message was sent to all breakout rooms'))
			this.isSendMessageDialogOpened = false
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
		height: 100%;
		padding: 20px;
	}
}

.right {
	justify-content: right;
}
</style>
