<!--
- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import { computed, watch } from 'vue'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import CallButton from '../TopBar/CallButton.vue'
import ReactionMenu from '../TopBar/ReactionMenu.vue'
import TopBarMediaControls from '../TopBar/TopBarMediaControls.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { localCallParticipantModel, localMediaModel } from '../../utils/webrtc/index.js'

const { isSidebar = false } = defineProps<{
	isSidebar: boolean
}>()
const AUTO_LOWER_HAND_THRESHOLD = 3000
const disableKeyboardShortcuts = OCP.Accessibility.disableKeyboardShortcuts()

const store = useStore()
const token = useGetToken()
const actorStore = useActorStore()
const breakoutRoomsStore = useBreakoutRoomsStore()

const conversation = computed(() => {
	return store.getters.conversation(token.value) || store.getters.dummyConversation
})

const supportedReactions = computed(() => getTalkConfig(token.value, 'call', 'supported-reactions') || [])

const hasReactionSupport = computed(() => supportedReactions.value && supportedReactions.value.length > 0)

const canModerate = computed(() => [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]
	.includes(conversation.value.participantType))

const isHandRaised = computed(() => localMediaModel.attributes.raisedHand.state === true)

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

const userIsInBreakoutRoomAndInCall = computed(() => conversation.value.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)

let lowerHandDelay = AUTO_LOWER_HAND_THRESHOLD
let speakingTimestamp: number | null = null
let lowerHandTimeout: ReturnType<typeof setTimeout> | null = null

// Hand raising functionality
/**
 *
 */
function toggleHandRaised() {
	const newState = !isHandRaised.value
	localMediaModel.toggleHandRaised(newState)
	store.dispatch('setParticipantHandRaised', {
		sessionId: actorStore.sessionId,
		raisedHand: localMediaModel.attributes.raisedHand,
	})

	// Handle breakout room assistance requests
	if (userIsInBreakoutRoomAndInCall.value && !canModerate.value) {
		const hasRaisedHands = Object.keys(store.getters.participantRaisedHandList)
			.filter((sessionId) => sessionId !== actorStore.sessionId)
			.length !== 0

		if (hasRaisedHands) {
			return // Assistance is already requested by someone in the room
		}

		const hasAssistanceRequested = conversation.value.breakoutRoomStatus === CONVERSATION.BREAKOUT_ROOM_STATUS.STATUS_ASSISTANCE_REQUESTED
		if (newState && !hasAssistanceRequested) {
			breakoutRoomsStore.requestAssistance(token.value)
		} else if (!newState && hasAssistanceRequested) {
			breakoutRoomsStore.dismissRequestAssistance(token.value)
		}
	}
}

// Auto-lower hand when speaking
watch(() => localMediaModel.attributes.speaking, (speaking) => {
	if (lowerHandTimeout !== null && !speaking) {
		lowerHandDelay = Math.max(0, lowerHandDelay - (Date.now() - speakingTimestamp!))
		clearTimeout(lowerHandTimeout)
		lowerHandTimeout = null
		return
	}

	// User is not speaking OR timeout is already running OR hand is not raised
	if (!speaking || lowerHandTimeout !== null || !isHandRaised.value) {
		return
	}

	speakingTimestamp = Date.now()
	lowerHandTimeout = setTimeout(() => {
		lowerHandTimeout = null
		speakingTimestamp = null
		lowerHandDelay = AUTO_LOWER_HAND_THRESHOLD

		if (isHandRaised.value) {
			toggleHandRaised()
		}
	}, lowerHandDelay)
})

// Keyboard shortcuts
useHotKey('r', toggleHandRaised)
</script>

<template>
	<div class="bottom-bar" data-theme-dark>
		<!--  fullscreen and grid view buttons -->

		<div class="bottom-bar-call-controls">
			<!-- Local media controls -->
			<TopBarMediaControls
				:token="token"
				:model="localMediaModel"
				:is-sidebar="isSidebar"
				:local-call-participant-model="localCallParticipantModel" />

			<!-- Reactions menu -->
			<ReactionMenu v-if="hasReactionSupport"
				:token="token"
				:supported-reactions="supportedReactions"
				:local-call-participant-model="localCallParticipantModel" />

			<NcButton
				:title="raiseHandButtonLabel"
				:aria-label="raiseHandButtonLabel"
				:variant="isHandRaised ? 'secondary' : 'tertiary'"
				@click="toggleHandRaised">
				<!-- The following icon is much bigger than all the others
					so we reduce its size -->
				<template #icon>
					<IconHandBackLeft :size="16" />
				</template>
			</NcButton>
		</div>

		<CallButton shrink-on-mobile
			:hide-text="isSidebar"
			:is-screensharing="!!localMediaModel.attributes.localScreen" />
	</div>
</template>

<style lang="scss" scoped>
.bottom-bar {
	position: absolute;
	bottom: 0;
	inset-inline: 0;
	height: 56px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0 calc(var(--default-grid-baseline) * 2);
	z-index: 10;
}

.bottom-bar-call-controls {
	display: flex;
	align-items: center;
	flex-direction: row;
	gap: var(--default-grid-baseline);
}
</style>
