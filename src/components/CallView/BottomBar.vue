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
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { useResizeObserver } from '@vueuse/core'
import debounce from 'debounce'
import { computed, onMounted, onUnmounted, ref, toValue, useTemplateRef, watch } from 'vue'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
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
import { useSettingsStore } from '../../stores/settings.ts'
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
const settingsStore = useSettingsStore()

const isLiveTranscriptionLoading = ref(false)
const bottomBar = useTemplateRef('bottomBar')
const callButtonWithActions = useTemplateRef('callButtonWithActions')
const isMobile = useIsMobile()

const conversation = computed(() => {
	return store.getters.conversation(token.value) || store.getters.dummyConversation
})

const supportedReactions = computed(() => getTalkConfig(token.value, 'call', 'supported-reactions') || [])

const hasReactionSupport = computed(() => supportedReactions.value && supportedReactions.value.length > 0)

const canModerate = computed(() => [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR]
	.includes(conversation.value.participantType))

const isLiveTranscriptionSupported = computed(() => getTalkConfig(token.value, 'call', 'live-transcription') || false)
const isLiveTranslationSupported = computed(() => getTalkConfig(token.value, 'call', 'live-translation') || false)

const liveTranscriptionButtonLabel = computed(() => {
	if (callViewStore.isLiveTranscriptionEnabled && languageType.value === LanguageType.Original) {
		return t('spreed', 'Disable live transcription')
	}

	return t('spreed', 'Enable live transcription')
})

const liveTranslationButtonLabel = computed(() => {
	if (callViewStore.isLiveTranscriptionEnabled && languageType.value === LanguageType.Target) {
		return t('spreed', 'Disable live translation')
	}

	return t('spreed', 'Enable live translation')
})

const originalLanguageButtonLabel = computed(() => {
	const languageId = conversation.value.liveTranscriptionLanguageId || 'en'

	const languageName = liveTranscriptionStore.getLiveTranscriptionLanguages()?.[languageId]?.name ?? languageId

	return t('spreed', 'Original language: {languageName}', {
		languageName,
	})
})

const targetLanguageButtonLabel = computed(() => {
	const languageId = targetLanguageId.value
	if (!languageId) {
		return t('spreed', 'Translated language')
	}

	const languageName = liveTranscriptionStore.getLiveTranscriptionTargetLanguages()?.[languageId]?.name ?? languageId

	return t('spreed', 'Translated language: {languageName}', {
		languageName,
	})
})

const targetLanguageId = computed(() => {
	const languageId = settingsStore.liveTranscriptionTargetLanguageId

	if (languageId) {
		return languageId
	}

	return liveTranscriptionStore.getLiveTranscriptionDefaultTargetLanguageId()
})

const targetLanguageAvailable = computed(() => {
	const liveTranscriptionTargetLanguages = liveTranscriptionStore.getLiveTranscriptionTargetLanguages()

	return targetLanguageId.value
		&& targetLanguageId.value !== conversation.value.liveTranscriptionLanguageId
		&& liveTranscriptionTargetLanguages && liveTranscriptionTargetLanguages[targetLanguageId.value]
})

const LanguageType = {
	Original: 'original',
	Target: 'target',
} as const

const languageType = ref<typeof LanguageType[keyof typeof LanguageType]>(LanguageType.Original)

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

const COLLAPSIBLE_BUTTONS = ['virtualBackground', 'liveTranscription', 'raiseHand', 'callLayout', 'fullscreen'] as const
type CollapsibleButtons = Record<typeof COLLAPSIBLE_BUTTONS[number], boolean>
const isActionAvailableMask = computed<CollapsibleButtons>(() => ({
	fullscreen: !isSidebar,
	callLayout: showCallLayoutSwitch.value,
	raiseHand: true,
	liveTranscription: isLiveTranscriptionSupported.value,
	virtualBackground: !isSidebar,
}))
const hidingList = ref<CollapsibleButtons>({ ...isActionAvailableMask.value })
const hasHiddenItems = computed(() => Object.values(hidingList.value).some(Boolean))
const BUTTON_WITH_GAP_WIDTH = 38 // var(--default-clickable-area) + var--default-grid-baseline)
const MINIMAL_MEDIA_CONTROLS_WIDTH = 236 // Minimal width to show media controls properly
/**
 * Adjust the layout of the bottom bar based on the available width.
 *
 */
function adjustLayout() {
	if (!bottomBar.value) {
		return
	}
	// 20px is for side paddings of the bottom bar, 8px is for the gap between the call button and the options
	const availableWidth = bottomBar.value.clientWidth - callButtonWithActions.value!.clientWidth - 28
	if (availableWidth <= MINIMAL_MEDIA_CONTROLS_WIDTH) {
		// Not enough space to show anything, hide all buttons
		COLLAPSIBLE_BUTTONS.forEach((button) => {
			hidingList.value[button as keyof typeof hidingList.value] = true
		})
		return
	}

	const buttonsToRender = Math.floor((availableWidth - MINIMAL_MEDIA_CONTROLS_WIDTH) / BUTTON_WITH_GAP_WIDTH)
	// make the first n buttons visible, hide the rest
	const buttonsToCollapse = COLLAPSIBLE_BUTTONS.filter((button) => isActionAvailableMask.value[button])
	buttonsToCollapse.forEach((button, index) => {
		hidingList.value[button] = index >= buttonsToRender
	})
}

const debounceAdjustLayout = debounce(adjustLayout, 200)

useResizeObserver(bottomBar, () => {
	debounceAdjustLayout()
})

onMounted(() => {
	adjustLayout()
})

onUnmounted(() => {
	debounceAdjustLayout.clear?.()
})

/**
 * Load live transcription and translation languages.
 */
function handleLiveTranscriptionLanguageSelectorOpen() {
	liveTranscriptionStore.loadLiveTranscriptionLanguages()
	liveTranscriptionStore.loadLiveTranscriptionTranslationLanguages()
}

/**
 * Toggle live transcriptions.
 *
 * Live translations are enabled again if needed when live transcriptions are
 * toggled on.
 */
async function toggleLiveTranscriptionAndTranslation() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	isLiveTranscriptionLoading.value = true

	if (!callViewStore.isLiveTranscriptionEnabled) {
		await enableLiveTranscription()

		if (languageType.value === LanguageType.Target) {
			await enableLiveTranslation()
		}
	} else {
		await disableLiveTranscription()
	}

	isLiveTranscriptionLoading.value = false
}

/**
 * Toggle live transcriptions.
 */
async function toggleLiveTranscription() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	if (callViewStore.isLiveTranscriptionEnabled && languageType.value === LanguageType.Original) {
		isLiveTranscriptionLoading.value = true

		await disableLiveTranscription()

		isLiveTranscriptionLoading.value = false
	} else {
		await switchToOriginalLanguage()
	}
}

/**
 * Toggle live translations.
 *
 * Disabling live translations disables live transcriptions as well.
 */
async function toggleLiveTranslation() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	if (callViewStore.isLiveTranscriptionEnabled && languageType.value === LanguageType.Target) {
		isLiveTranscriptionLoading.value = true

		await disableLiveTranscription()

		isLiveTranscriptionLoading.value = false
	} else {
		await switchToTargetLanguage()
	}
}

/**
 * Enable live transcriptions, disabling live translations if they were already
 * enabled.
 */
async function switchToOriginalLanguage() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	isLiveTranscriptionLoading.value = true

	if (!callViewStore.isLiveTranscriptionEnabled && !(await enableLiveTranscription())) {
		isLiveTranscriptionLoading.value = false

		return
	}

	await disableLiveTranslation()

	isLiveTranscriptionLoading.value = false
}

/**
 * Enable live translations, enabling live transcriptions first if they were not
 * enabled yet.
 */
async function switchToTargetLanguage() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	isLiveTranscriptionLoading.value = true

	if (!callViewStore.isLiveTranscriptionEnabled && !(await enableLiveTranscription())) {
		isLiveTranscriptionLoading.value = false

		return
	}

	await enableLiveTranslation()

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

		return false
	}

	try {
		await callViewStore.enableLiveTranscription(token.value)
	} catch (error) {
		showError(t('spreed', 'Failed to enable live transcription'))

		return false
	}

	return true
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

/**
 * Enable live translations.
 *
 * Live transcriptions need to have been enabled first before enabling live
 * translations.
 */
async function enableLiveTranslation() {
	if (languageType.value === LanguageType.Target) {
		return
	}

	try {
		await callViewStore.setLiveTranscriptionTargetLanguage(token.value, targetLanguageId.value as string)
	} catch (error) {
		showError(t('spreed', 'Failed to enable live translations'))

		return
	}

	languageType.value = LanguageType.Target
}

/**
 * Disable live translations.
 *
 * This does not disable live transcriptions, so the transcription will continue
 * in the original language.
 */
async function disableLiveTranslation() {
	if (languageType.value === LanguageType.Original) {
		return
	}

	try {
		await callViewStore.setLiveTranscriptionTargetLanguage(token.value, null)
	} catch (error) {
		showError(t('spreed', 'Failed to disable live translations'))

		return
	}

	languageType.value = LanguageType.Original
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
		<div v-if="!isSidebar" class="bottom-bar-call-controls">
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

		<div class="bottom-bar-call-controls">
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

			<div
				v-if="isLiveTranscriptionSupported && !hidingList.liveTranscription"
				class="live-transcription-button-wrapper">
				<NcButton
					:title="liveTranscriptionButtonLabel"
					:aria-label="liveTranscriptionButtonLabel"
					:variant="callViewStore.isLiveTranscriptionEnabled ? 'secondary' : 'tertiary'"
					:disabled="isLiveTranscriptionLoading"
					:class="{
						'translation-button': isLiveTranslationSupported,
					}"
					@click="toggleLiveTranscriptionAndTranslation">
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

				<NcActions
					v-if="isLiveTranslationSupported"
					class="language-selector-button"
					@open="handleLiveTranscriptionLanguageSelectorOpen">
					<template #icon>
						<IconChevronUp :size="16" />
					</template>
					<NcActionButton
						class="language-selector__action"
						type="radio"
						:disabled="isLiveTranscriptionLoading"
						:model-value="languageType"
						:value="LanguageType.Original"
						:title="originalLanguageButtonLabel"
						close-after-click
						@click="switchToOriginalLanguage">
						{{ originalLanguageButtonLabel }}
					</NcActionButton>
					<NcActionSeparator />
					<NcActionButton
						class="language-selector__action"
						type="radio"
						:disabled="isLiveTranscriptionLoading || !targetLanguageAvailable"
						:model-value="languageType"
						:value="LanguageType.Target"
						:title="targetLanguageButtonLabel"
						close-after-click
						@click="switchToTargetLanguage">
						{{ targetLanguageButtonLabel }}
					</NcActionButton>
				</NcActions>
			</div>

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
		<div ref="callButtonWithActions" class="bottom-bar-options call-options">
			<!-- Collapsed actions -->
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
					:variant="(callViewStore.isLiveTranscriptionEnabled && languageType === LanguageType.Target) ? 'secondary' : 'tertiary'"
					:disabled="isLiveTranscriptionLoading"
					@click="toggleLiveTranscription">
					<template #icon>
						<NcLoadingIcon
							v-if="isLiveTranscriptionLoading && languageType === LanguageType.Original"
							:size="20" />
						<IconSubtitles
							v-else-if="callViewStore.isLiveTranscriptionEnabled && languageType === LanguageType.Original"
							:size="20" />
						<IconSubtitlesOutline
							v-else
							:size="20" />
					</template>
					{{ liveTranscriptionButtonLabel }}
				</NcActionButton>
				<NcActionButton
					v-if="isLiveTranslationSupported && hidingList.liveTranscription"
					:title="liveTranslationButtonLabel"
					:aria-label="liveTranslationButtonLabel"
					:variant="(callViewStore.isLiveTranscriptionEnabled && languageType === LanguageType.Target) ? 'secondary' : 'tertiary'"
					:disabled="isLiveTranscriptionLoading"
					@click="toggleLiveTranslation">
					<template #icon>
						<NcLoadingIcon
							v-if="isLiveTranscriptionLoading && languageType === LanguageType.Target"
							:size="20" />
						<IconSubtitles
							v-else-if="callViewStore.isLiveTranscriptionEnabled && languageType === LanguageType.Target"
							:size="20" />
						<IconSubtitlesOutline
							v-else
							:size="20" />
					</template>
					{{ liveTranslationButtonLabel }}
				</NcActionButton>
				<NcActionButton
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
				</NcActionButton>
			</NcActions>

			<CallButton
				class="call-button"
				:hide-text="isSidebar || isMobile"
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
	overflow: hidden;

	:deep(.button-vue--tertiary) {
		background-color: var(--color-primary-light);
	}
}

.bottom-bar-call-controls {
	display: flex;
	align-items: center;
	flex-direction: row;
	gap: var(--default-grid-baseline);
}

.bottom-bar-call-controls:not(:has(*)) {
	display: none
}

.call-options {
	display: flex;
	align-items: center;
	flex-direction: row;
	gap: 4px;
}

.live-transcription-button-wrapper {
	display: flex;
	align-items: center;
	gap: 1px;

	// Overwriting NcButton styles
	.translation-button {
		border-start-end-radius: 2px;
		border-end-end-radius: 2px;
	}
}

.language-selector-button :deep(.action-item__menutoggle) {
	--button-size: var(--clickable-area-small);
	height: var(--default-clickable-area);
	border-start-start-radius: 2px;
	border-end-start-radius: 2px;
}

.language-selector__action {
	// Overwriting NcActionButton styles
	:deep(.action-button__longtext) {
		display: -webkit-box;
		-webkit-line-clamp: 1;
		-webkit-box-orient: vertical;
		overflow: hidden;
		text-overflow: ellipsis;
		padding: 0;
		max-width: 350px;
	}

	:deep(.action-button__longtext-wrapper) {
		max-width: 350px;
	}

	:deep(.action-button__icon) {
		width: 0;
		margin-inline-start: calc(var(--default-grid-baseline) * 3);
	}

	:deep(.action-button > span) {
		height: var(--default-clickable-area);
	}
}
</style>
