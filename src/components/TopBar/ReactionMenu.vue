<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { LocalCallParticipantModel, LocalMediaModel } from '../../types/index.ts'

import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import { computed, watch } from 'vue'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionButtonGroup from '@nextcloud/vue/components/NcActionButtonGroup'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import IconHandBackLeftOutline from 'vue-material-design-icons/HandBackLeftOutline.vue'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useParticipantActivityStore } from '../../stores/participantActivity.ts'

const props = defineProps<{
	/* The conversation token */
	token: string
	/* Signaling participant model */
	localMediaModel: LocalMediaModel
	/* Signaling participant model */
	localCallParticipantModel: LocalCallParticipantModel
}>()

const actorStore = useActorStore()
const breakoutRoomsStore = useBreakoutRoomsStore()
const participantActivityStore = useParticipantActivityStore()
const vuexStore = useStore()

const AUTO_LOWER_HAND_THRESHOLD = 3_000
const disableKeyboardShortcuts = OCP.Accessibility.disableKeyboardShortcuts()
useHotKey('r', toggleHandRaised)

let throttleTimer: ReturnType<typeof setTimeout> | undefined
let lowerHandDelay = AUTO_LOWER_HAND_THRESHOLD
let speakingTimestamp: number | null = null
let lowerHandTimeout: ReturnType<typeof setTimeout> | undefined

const supportedReactions = computed(() => getTalkConfig(props.token, 'call', 'supported-reactions') || [])
const hasReactionSupport = computed(() => supportedReactions.value && supportedReactions.value.length > 0)
const reactionsInSingleRow = computed(() => Math.ceil(supportedReactions.value.length / 2))

const conversation = computed(() => {
	return vuexStore.getters.conversation(props.token) || vuexStore.getters.dummyConversation
})
const canModerate = computed(() => [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]
	.includes(conversation.value.participantType))
const userIsInBreakoutRoomAndInCall = computed(() => conversation.value.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
const isHandRaised = computed(() => props.localMediaModel.attributes.raisedHand.state === true)
const raiseHandButtonLabel = computed(() => {
	if (!isHandRaised.value) {
		return disableKeyboardShortcuts
			? t('spreed', 'Raise hand')
			: t('spreed', 'Raise hand (R)')
	}
	return disableKeyboardShortcuts
		? t('spreed', 'Lower hand')
		: t('spreed', 'Lower hand (R)')
})

const actionsLabel = computed(() => hasReactionSupport.value ? t('spreed', 'Send a reaction') : raiseHandButtonLabel.value)

/**
 * Throttle reaction sending from a single use (to 1 reaction every 2 seconds)
 *
 * @param reaction emoji character to send
 */
function throttledSendReaction(reaction: string) {
	if (throttleTimer) {
		return
	}

	sendReaction(reaction)
	throttleTimer = setTimeout(() => {
		throttleTimer = undefined
	}, 2_000)
}

/**
 * Relay reaction via WebRTC and render on sender screen
 *
 * @param reaction emoji character to send
 */
function sendReaction(reaction: string) {
	// send reaction to other participants
	props.localCallParticipantModel.sendReaction(reaction)

	// show reaction to yourself
	emit('send-reaction', {
		model: props.localCallParticipantModel,
		reaction,
	})
}

/**
 * Toggle the hand raised state for the local media model and update the store.
 * If the user is in a breakout room, it also handles the request for assistance.
 */
function toggleHandRaised() {
	const newState = !isHandRaised.value
	props.localMediaModel.toggleHandRaised(newState)
	participantActivityStore.setParticipantHandRaised({
		sessionId: actorStore.sessionId!,
		raisedHand: props.localMediaModel.attributes.raisedHand,
	})

	// Handle breakout room assistance requests
	if (userIsInBreakoutRoomAndInCall.value && !canModerate.value) {
		const hasRaisedHands = Object.keys(participantActivityStore.raisedHands)
			.filter((sessionId) => sessionId !== actorStore.sessionId)
			.length !== 0

		if (hasRaisedHands) {
			return // Assistance is already requested by someone in the room
		}

		const hasAssistanceRequested = conversation.value.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STATUS_ASSISTANCE_REQUESTED
		if (newState && !hasAssistanceRequested) {
			breakoutRoomsStore.requestAssistance(props.token)
		} else if (!newState && hasAssistanceRequested) {
			breakoutRoomsStore.dismissRequestAssistance(props.token)
		}
	}
}

/* Auto-lower hand when speaking */
watch(() => props.localMediaModel.attributes.speaking, (speaking: boolean) => {
	if (lowerHandTimeout !== undefined && !speaking) {
		lowerHandDelay = Math.max(0, lowerHandDelay - (Date.now() - speakingTimestamp!))
		clearTimeout(lowerHandTimeout)
		lowerHandTimeout = undefined
		return
	}

	// User is not speaking OR timeout is already running OR hand is not raised
	if (!speaking || lowerHandTimeout !== undefined || !isHandRaised.value) {
		return
	}

	speakingTimestamp = Date.now()
	lowerHandTimeout = setTimeout(() => {
		lowerHandTimeout = undefined
		speakingTimestamp = null
		lowerHandDelay = AUTO_LOWER_HAND_THRESHOLD

		if (isHandRaised.value) {
			toggleHandRaised()
		}
	}, lowerHandDelay)
})
</script>

<template>
	<NcActions
		variant="tertiary"
		:title="actionsLabel"
		:aria-label="actionsLabel"
		class="reaction">
		<template #icon>
			<IconEmoticonOutline :size="20" />
		</template>

		<NcActionButtonGroup
			v-if="hasReactionSupport"
			class="reaction__group"
			:style="{ '--reactions-in-single-row': reactionsInSingleRow }">
			<NcActionButton
				v-for="(reaction, index) in supportedReactions"
				:key="index"
				:aria-label="t('spreed', 'React with {reaction}', { reaction })"
				class="reaction__button"
				@click="throttledSendReaction(reaction)">
				<template #icon>
					{{ reaction }}
				</template>
			</NcActionButton>
		</NcActionButtonGroup>

		<NcActionSeparator v-if="hasReactionSupport" />

		<NcActionButton
			:title="raiseHandButtonLabel"
			:aria-label="raiseHandButtonLabel"
			class="raise-hand__button"
			:variant="isHandRaised ? 'secondary' : 'tertiary'"
			@click="toggleHandRaised">
			<!-- The following icon is much bigger than all the others
				so we reduce its size -->
			<template #icon>
				<IconHandBackLeft v-if="isHandRaised" :size="18" />
				<IconHandBackLeftOutline v-else :size="18" />
			</template>
			{{ raiseHandButtonLabel }}
		</NcActionButton>
	</NcActions>
</template>

<style lang="scss" scoped>
.reaction {
	&__group {
		// Override NcActionButtonGroup styles to fit reactions in a compact way
		:deep(.nc-button-group-content) {
			flex-wrap: wrap;
			justify-content: flex-start;
			gap: 0 !important;
			min-width: calc(var(--reactions-in-single-row) * var(--default-clickable-area))
		}
	}

	&__button {
		flex: 0 0 calc(100% / var(--reactions-in-single-row)) !important;
	}
}

.raise-hand__button :deep(.action-button) {
	justify-content: center;
}
</style>
