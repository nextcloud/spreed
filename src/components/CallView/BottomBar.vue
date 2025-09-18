<!--
- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import {
	showError,
} from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import { useResizeObserver } from '@vueuse/core'
import debounce from 'debounce'
import { computed, onUnmounted, ref, toValue, useTemplateRef, watch } from 'vue'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconFullscreen from 'vue-material-design-icons/Fullscreen.vue'
import IconFullscreenExit from 'vue-material-design-icons/FullscreenExit.vue'
import IconHandBackLeft from 'vue-material-design-icons/HandBackLeft.vue' // Filled for better indication
import IconHandBackLeftOutline from 'vue-material-design-icons/HandBackLeftOutline.vue'
import IconSubtitles from 'vue-material-design-icons/Subtitles.vue'
import IconSubtitlesOutline from 'vue-material-design-icons/SubtitlesOutline.vue'
import IconViewGalleryOutline from 'vue-material-design-icons/ViewGalleryOutline.vue'
import IconViewGridOutline from 'vue-material-design-icons/ViewGridOutline.vue'
import CallButton from '../TopBar/CallButton.vue'
import ReactionMenu from '../TopBar/ReactionMenu.vue'
import TopBarMediaControls from '../TopBar/TopBarMediaControls.vue'
import {
	toggleFullscreen,
	useDocumentFullscreen,
} from '../../composables/useDocumentFullscreen.ts'
import { useGetToken } from '../../composables/useGetToken.ts'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { useLiveTranscriptionStore } from '../../stores/liveTranscription.ts'
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
const isFullscreen = !isSidebar && useDocumentFullscreen()
const callViewStore = useCallViewStore()
const liveTranscriptionStore = useLiveTranscriptionStore()

const isLiveTranscriptionLoading = ref(false)
const bottomBar = useTemplateRef('bottomBar')

const conversation = computed(() => {
	return store.getters.conversation(token.value) || store.getters.dummyConversation
})

const supportedReactions = computed(() => getTalkConfig(token.value, 'call', 'supported-reactions') || [])

const hasReactionSupport = computed(() => supportedReactions.value && supportedReactions.value.length > 0)

const canModerate = computed(() => [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]
	.includes(conversation.value.participantType))

const isLiveTranscriptionSupported = computed(() => getTalkConfig(token.value, 'call', 'live-transcription') || false)

const liveTranscriptionButtonLabel = computed(() => {
	if (!callViewStore.isLiveTranscriptionEnabled) {
		return t('spreed', 'Enable live transcription')
	}

	return t('spreed', 'Disable live transcription')
})

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

const fullscreenLabel = computed(() => {
	return toValue(isFullscreen)
		? t('spreed', 'Exit full screen (F)')
		: t('spreed', 'Full screen (F)')
})

const changeViewLabel = computed(() => {
	return isGrid.value
		? t('spreed', 'Speaker view')
		: t('spreed', 'Grid view')
})

const showCallLayoutSwitch = computed(() => !callViewStore.isEmptyCallView)
const isGrid = computed(() => callViewStore.isGrid)
const userIsInBreakoutRoomAndInCall = computed(() => conversation.value.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)

const hidingList = ref({ forceMobileCallButton: false, fullscreen: false, callLayout: false, raiseHand: false, liveTranscription: false, virtualBackground: false })
const hasHiddenItems = computed(() => Object.values(hidingList.value).some((hidden) => hidden === true))
/**
 * Adjust the layout of the bottom bar based on the available width.
 *
 */
function adjustLayout() {
	const bottomBarElement = toValue(bottomBar) as HTMLElement
	if (!bottomBarElement) {
		return
	}

	const availableWidth = bottomBarElement.clientWidth
	let requiredWidth = 0

	// Calculate the required width for the left controls
	const leftControls = bottomBarElement.querySelector('.layout-options') as HTMLElement
	if (leftControls && isSidebar === false) {
		requiredWidth += leftControls.clientWidth
	}

	// Calculate the required width for the right controls
	const rightControls = bottomBarElement.querySelector('.interaction-options') as HTMLElement
	if (rightControls) {
		requiredWidth += rightControls.clientWidth
	}

	const callButton = bottomBarElement.querySelector('.call-options') as HTMLElement
	if (callButton) {
		requiredWidth += callButton.clientWidth
	}

	// Add some margin for spacing between the two control groups
	requiredWidth += 10 * 2 + 8 // 10px margin on each side + 4px gap between the 3 groups
	const diff = requiredWidth - availableWidth
	const buttonWidth = /* clickable area + gaps */ 38

	if (diff > 0) {
		// Add items from hiding list to the menu
		// If there is still not enough space, keep adding items to the menu
		// until there is enough space
		// Start with the least important items and move to the more important ones
		// order: fullscreen, callLayout, raiseHand, liveTranscription, reactions
		const buttonsNumToHide = Math.ceil(Math.abs(diff) / buttonWidth) + (!hasHiddenItems.value ? 1 : 0)
		let hiddenCount = 0
		while (hiddenCount < buttonsNumToHide) {
			if (!hidingList.value.forceMobileCallButton) {
				hidingList.value.forceMobileCallButton = true
			} else if (!hidingList.value.fullscreen && !isSidebar) {
				hidingList.value.fullscreen = true
			} else if (!hidingList.value.callLayout && showCallLayoutSwitch.value) {
				hidingList.value.callLayout = true
			} else if (!hidingList.value.raiseHand) {
				hidingList.value.raiseHand = true
			} else if (!hidingList.value.liveTranscription && isLiveTranscriptionSupported.value) {
				hidingList.value.liveTranscription = true
			} else if (!hidingList.value.virtualBackground) {
				hidingList.value.virtualBackground = true
			} else {
				// all items are already hidden, nothing more to do
				break
			}
			hiddenCount++
		}
	} else if (diff < 0 && hasHiddenItems.value) {
		// Expand the menu if there is enough space
		// Start with the most important items and move to the less important ones
		const buttonsNumToshow = Math.floor(Math.abs(diff) / buttonWidth)
		const hiddenButtonsNum = Object.values(hidingList.value).filter((hidden) => hidden === true).length
		let shownCounter = Math.min(buttonsNumToshow, hiddenButtonsNum)
		while (shownCounter > 0) {
			if (hidingList.value.virtualBackground) {
				hidingList.value.virtualBackground = false
			} else if (hidingList.value.liveTranscription && isLiveTranscriptionSupported.value) {
				hidingList.value.liveTranscription = false
			} else if (hidingList.value.raiseHand) {
				hidingList.value.raiseHand = false
			} else if (hidingList.value.callLayout && showCallLayoutSwitch.value) {
				hidingList.value.callLayout = false
			} else if (hidingList.value.fullscreen && !isSidebar) {
				hidingList.value.fullscreen = false
			} else if (hidingList.value.forceMobileCallButton) {
				hidingList.value.forceMobileCallButton = false
			} else {
				// all items are already visible, nothing more to do
				break
			}
			shownCounter--
		}
	}
}

const debounceAdjustLayout = debounce(adjustLayout, 200)

useResizeObserver(bottomBar, () => {
	debounceAdjustLayout()
})

onUnmounted(() => {
	debounceAdjustLayout.clear?.()
})

/**
 * Toggle live transcriptions.
 */
async function toggleLiveTranscription() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	isLiveTranscriptionLoading.value = true

	if (!callViewStore.isLiveTranscriptionEnabled) {
		await enableLiveTranscription()
	} else {
		await disableLiveTranscription()
	}

	isLiveTranscriptionLoading.value = false
}

/**
 * Enable live transcriptions.
 */
async function enableLiveTranscription() {
	// Strictly speaking it would be the responsibility of the components using
	// the language metadata to ensure that it is loaded before using it, but
	// for simplicity it is done here and enabling the live transcription is
	// tied to having said metadata.
	try {
		await liveTranscriptionStore.loadLiveTranscriptionLanguages()
	} catch (exception) {
		showError(t('spreed', 'Error when trying to load the available live transcription languages'))

		return
	}

	try {
		await callViewStore.enableLiveTranscription(token.value)
	} catch (error) {
		showError(t('spreed', 'Failed to enable live transcription'))
	}
}

/**
 * Disable live transcriptions.
 */
async function disableLiveTranscription() {
	try {
		await callViewStore.disableLiveTranscription(token.value)
	} catch (error) {
		// Not being able to disable the live transcription is not really
		// relevant for the user, as the transcript will be no longer visible in
		// the UI anyway, so no error is shown in that case.
	}
}

let lowerHandDelay = AUTO_LOWER_HAND_THRESHOLD
let speakingTimestamp: number | null = null
let lowerHandTimeout: ReturnType<typeof setTimeout> | null = null

// Hand raising functionality
/**
 * Toggle the hand raised state for the local media model and update the store.
 * If the user is in a breakout room, it also handles the request for assistance.
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

/**
 * Switches the call view mode between grid and speaker view.
 */
function changeView() {
	callViewStore.setCallViewMode({ token: token.value, isGrid: !isGrid.value, clearLast: false })
	callViewStore.setSelectedVideoPeerId(null)
}

// Keyboard shortcuts
useHotKey('r', toggleHandRaised)
</script>

<template>
	<div ref="bottomBar" class="bottom-bar" data-theme-dark>
		<div v-if="!isSidebar" class="bottom-bar-call-controls layout-options">
			<!-- Fullscreen -->
			<NcButton
				v-if="!hidingList.fullscreen"
				:aria-label="fullscreenLabel"
				:variant="isFullscreen ? 'secondary' : 'tertiary'"
				:title="fullscreenLabel"
				@click="toggleFullscreen">
				<template #icon>
					<IconFullscreen v-if="!isFullscreen" :size="20" />
					<IconFullscreenExit v-else :size="20" />
				</template>
			</NcButton>
			<!-- Call layout switcher -->
			<NcButton
				v-if="showCallLayoutSwitch && !hidingList.callLayout"
				variant="tertiary"
				:aria-label="changeViewLabel"
				:title="changeViewLabel"
				@click="changeView">
				<template #icon>
					<IconViewGridOutline v-if="!isGrid" :size="20" />
					<IconViewGalleryOutline v-else :size="20" />
				</template>
			</NcButton>
		</div>

		<div class="bottom-bar-call-controls interaction-options">
			<!-- Local media controls -->
			<TopBarMediaControls
				:token="token"
				:model="localMediaModel"
				:is-sidebar="isSidebar"
				:hide-virtual-background-shortcut="hidingList.virtualBackground"
				:local-call-participant-model="localCallParticipantModel" />

			<!-- Reactions menu -->
			<ReactionMenu
				v-if="hasReactionSupport"
				:token="token"
				:supported-reactions="supportedReactions"
				:local-call-participant-model="localCallParticipantModel" />

			<NcButton
				v-if="isLiveTranscriptionSupported && !hidingList.liveTranscription"
				:title="liveTranscriptionButtonLabel"
				:aria-label="liveTranscriptionButtonLabel"
				:variant="callViewStore.isLiveTranscriptionEnabled ? 'secondary' : 'tertiary'"
				:disabled="isLiveTranscriptionLoading"
				@click="toggleLiveTranscription">
				<template #icon>
					<NcLoadingIcon
						v-if="isLiveTranscriptionLoading"
						:size="20" />
					<IconSubtitles
						v-else-if="callViewStore.isLiveTranscriptionEnabled"
						:size="20" />
					<IconSubtitlesOutline
						v-else
						:size="20" />
				</template>
			</NcButton>

			<NcButton
				v-if="!isSidebar && !hidingList.raiseHand"
				:title="raiseHandButtonLabel"
				:aria-label="raiseHandButtonLabel"
				:variant="isHandRaised ? 'secondary' : 'tertiary'"
				@click="toggleHandRaised">
				<!-- The following icon is much bigger than all the others
					so we reduce its size -->
				<template #icon>
					<IconHandBackLeft v-if="isHandRaised" :size="18" />
					<IconHandBackLeftOutline v-else :size="18" />
				</template>
			</NcButton>
		</div>
		<div class="bottom-bar-options call-options">
			<NcActions v-if="hasHiddenItems" force-menu>
				<!-- Fullscreen -->
				<NcActionButton
					v-if="!isSidebar && hidingList.fullscreen"
					:aria-label="fullscreenLabel"
					:variant="isFullscreen ? 'secondary' : 'tertiary'"
					:title="fullscreenLabel"
					@click="toggleFullscreen">
					<template #icon>
						<IconFullscreen v-if="!isFullscreen" :size="20" />
						<IconFullscreenExit v-else :size="20" />
					</template>
					{{ fullscreenLabel }}
				</NcActionButton>
				<!-- Call layout switcher -->
				<NcActionButton
					v-if="hidingList.callLayout && showCallLayoutSwitch"
					variant="tertiary"
					:aria-label="changeViewLabel"
					:title="changeViewLabel"
					@click="changeView">
					<template #icon>
						<IconViewGridOutline v-if="!isGrid" :size="20" />
						<IconViewGalleryOutline v-else :size="20" />
					</template>
					{{ changeViewLabel }}
				</NcActionButton>
				<NcActionButton
					v-if="isLiveTranscriptionSupported && hidingList.liveTranscription"
					:title="liveTranscriptionButtonLabel"
					:aria-label="liveTranscriptionButtonLabel"
					:variant="callViewStore.isLiveTranscriptionEnabled ? 'secondary' : 'tertiary'"
					:disabled="isLiveTranscriptionLoading"
					@click="toggleLiveTranscription">
					<template #icon>
						<NcLoadingIcon
							v-if="isLiveTranscriptionLoading"
							:size="20" />
						<IconSubtitles
							v-else-if="callViewStore.isLiveTranscriptionEnabled"
							:size="20" />
						<IconSubtitlesOutline
							v-else
							:size="20" />
					</template>
					{{ liveTranscriptionButtonLabel }}
				</NcActionButton>
				<NcButton
					v-if="!isSidebar && hidingList.raiseHand"
					:title="raiseHandButtonLabel"
					:aria-label="raiseHandButtonLabel"
					:variant="isHandRaised ? 'secondary' : 'tertiary'"
					@click="toggleHandRaised">
					<!-- The following icon is much bigger than all the others
						so we reduce its size -->
					<template #icon>
						<IconHandBackLeft v-if="isHandRaised" :size="18" />
						<IconHandBackLeftOutline v-else :size="18" />
					</template>
					{{ raiseHandButtonLabel }}
				</NcButton>
			</NcActions>

			<CallButton
				class="call-button"
				:hide-text="isSidebar || hidingList.forceMobileCallButton"
				:is-screensharing="!!localMediaModel.attributes.localScreen" />
		</div>
	</div>
</template>

<style lang="scss" scoped>
.bottom-bar {
	position: absolute;
	bottom: 0;
	inset-inline: 0;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: var(--wrapper-padding);
	z-index: 10;

	:deep(.button-vue--tertiary) {
		background-color: var(--color-primary-light);
	}
}

.bottom-bar-call-controls {
	display: flex;
	align-items: center;
	flex-direction: row;
	gap: var(--default-grid-baseline);
	margin-inline-end: var(--default-grid-baseline);
}

.call-options {
	display: flex;
	align-items: center;
	flex-direction: row;
	gap: 4px;
}
</style>
