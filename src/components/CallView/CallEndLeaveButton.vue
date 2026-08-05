<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import IconPhoneHangupOutline from 'vue-material-design-icons/PhoneHangupOutline.vue'
import IconPhoneOffOutline from 'vue-material-design-icons/PhoneOffOutline.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { isConversationPhoneRoom } from '../../utils/conversation.ts'

const props = defineProps<{
	/** Whether to render button as tertiary (to reduce drawn attention) */
	isScreensharing?: boolean
	/** Whether to use text on button (e.g. at sidebar) */
	hideText?: boolean
	/** Whether to use text on button at mobile view */
	shrinkOnMobile?: boolean
}>()

const endCallLabel = t('spreed', 'End call')
const leaveCallLabel = t('spreed', 'Leave call')
const leaveCallActionsLabel = t('spreed', 'More actions')
const backToMainRoomLabel = t('spreed', 'Back to main room')

const actorStore = useActorStore()
const breakoutRoomsStore = useBreakoutRoomsStore()
const callViewStore = useCallViewStore()

const router = useRouter()
const vuexStore = useStore()

const token = useGetToken()
const isMobile = useIsMobile()

const loading = ref(false)

const conversation = computed(() => vuexStore.getters.conversation(token.value) || vuexStore.getters.dummyConversation)
const showButtonText = computed(() => !props.hideText && (!isMobile.value || !props.shrinkOnMobile))
const canEndForAll = computed(() => !isBreakoutRoom.value
	&& [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(conversation.value.participantType))
const isBreakoutRoom = computed(() => conversation.value.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
const isPhoneRoom = computed(() => isConversationPhoneRoom(conversation.value))
const isVoiceRoom = computed(() => Boolean(conversation.value.attributes & CONVERSATION.ATTRIBUTE.VOICE_ROOM))
const leaveCallButtonVariant = computed(() => {
	if (props.isScreensharing) {
		return 'tertiary'
	}
	return isBreakoutRoom.value ? 'primary' : 'error'
})

/**
 * Leave or end the call
 *
 * @param endMeetingForAll - whether to kick all participants from the call
 */
async function leaveCall(endMeetingForAll = false) {
	if (endMeetingForAll) {
		console.info('End meeting for everyone')
	} else {
		console.info('Leaving call')
	}

	if (isVoiceRoom.value) {
		router.push({ name: 'root' })
		// Call ending is handled in App.vue
		return
	}

	// Remove selected participant
	callViewStore.setSelectedVideoPeerId(null)
	loading.value = true

	// Open navigation
	if (!isMobile.value) {
		emit('toggle-navigation', {
			open: true,
		})
	}
	await vuexStore.dispatch('leaveCall', {
		token: token.value,
		participantIdentifier: actorStore.participantIdentifier,
		all: endMeetingForAll,
	})
	loading.value = false
}

/**
 * Switch back from breakout room to main (parent) room
 */
function switchToParentRoom() {
	EventBus.emit('switch-to-conversation', {
		token: breakoutRoomsStore.getParentRoomToken(token.value)!,
	})
}
</script>

<template>
	<NcButton
		v-if="canEndForAll && isPhoneRoom"
		:aria-label="endCallLabel"
		class="leave-call"
		variant="error"
		:disabled="loading"
		@click="leaveCall(true)">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="20" />
			<IconPhoneHangupOutline v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ endCallLabel }}
		</template>
	</NcButton>
	<NcButton
		v-else-if="(!canEndForAll || isVoiceRoom) && !isBreakoutRoom"
		:aria-label="leaveCallLabel"
		class="leave-call"
		:variant="isScreensharing ? 'tertiary' : 'error'"
		:disabled="loading"
		@click="leaveCall(false)">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="20" />
			<IconPhoneHangupOutline v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ leaveCallLabel }}
		</template>
	</NcButton>
	<NcActions
		v-else-if="(canEndForAll || isBreakoutRoom)"
		class="leave-call leave-call-actions--split"
		:disabled="loading"
		:forceName="showButtonText"
		placement="top-end"
		:aria-label="leaveCallActionsLabel"
		:inline="1"
		:variant="leaveCallButtonVariant">
		<template #icon>
			<IconChevronUp :size="20" />
		</template>
		<NcActionButton
			v-if="isBreakoutRoom"
			:aria-label="backToMainRoomLabel"
			@click="switchToParentRoom">
			<template #icon>
				<IconArrowLeft class="bidirectional-icon" :size="20" />
			</template>
			<template v-if="showButtonText" #default>
				{{ backToMainRoomLabel }}
			</template>
		</NcActionButton>
		<NcActionButton
			class="leave-call-button--split"
			:aria-label="leaveCallLabel"
			@click="leaveCall(false)">
			<template #icon>
				<NcLoadingIcon v-if="loading" :size="20" />
				<IconPhoneHangupOutline v-else :size="20" />
			</template>
			<template v-if="showButtonText || isBreakoutRoom" #default>
				{{ leaveCallLabel }}
			</template>
		</NcActionButton>
		<NcActionButton v-if="canEndForAll && !isVoiceRoom" @click="leaveCall(true)">
			<template #icon>
				<IconPhoneOffOutline :size="20" />
			</template>
			{{ t('spreed', 'End call for everyone') }}
		</NcActionButton>
	</NcActions>
</template>

<style lang="scss" scoped>
.leave-call.button-vue--error,
.leave-call :deep(.button-vue--error) {
	// Overwrite default button colors for leaving call
	background-color: #FF3333 !important; // Nextcloud 31 --color-error
	color: var(--color-primary-text) !important;

	&:hover:not(:disabled) {
		background-color: var(--color-error-hover) !important;
	}
}

.leave-call-actions--split {
	gap: 1px !important;
}

.leave-call-actions--split :deep(.action-item--single) {
	border-start-end-radius: 2px;
	border-end-end-radius: 2px;
}

.leave-call-actions--split :deep(.action-item__menutoggle) {
	--button-size: var(--clickable-area-small);
	height: var(--default-clickable-area);
	border-start-start-radius: 2px;
	border-end-start-radius: 2px;
}
</style>
