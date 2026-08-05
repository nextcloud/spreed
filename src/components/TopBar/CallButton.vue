<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconPhone from 'vue-material-design-icons/Phone.vue' // Filled used for non-silent calls
import IconPhoneDialOutline from 'vue-material-design-icons/PhoneDialOutline.vue'
import IconPhoneOutline from 'vue-material-design-icons/PhoneOutline.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { useJoinCall } from '../../composables/useJoinCall.ts'
import { CALL, CONVERSATION } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { useSettingsStore } from '../../stores/settings.ts'
import { useSoundsStore } from '../../stores/sounds.js'
import { useTalkHashStore } from '../../stores/talkHash.js'
import { useTokenStore } from '../../stores/token.ts'
import { isAxiosErrorResponse } from '../../types/guards.ts'
import { blockCalls, unsupportedWarning } from '../../utils/browserCheck.ts'
import { hasExternalCallService, isConversationPhoneRoom } from '../../utils/conversation.ts'
import { messagePleaseReload } from '../../utils/talkDesktopUtils.ts'

const props = defineProps<{
	disabled?: boolean
	/** Whether the component is used in MediaSettings or not (click directly starts a call) */
	isMediaSettings?: boolean
	/** Whether the call should trigger a notifications and sound for other participants or not */
	silentCall?: boolean
	/** Whether to trigger recording to start with the call */
	isRecordingFromStart?: boolean
	/** Pass through of recording consent */
	recordingConsentGiven?: boolean
	/** Whether to use text on button (e.g. at sidebar) */
	hideText?: boolean
	/** Whether to use text on button at mobile view */
	shrinkOnMobile?: boolean
}>()

const { joinCall } = useJoinCall()

const tokenStore = useTokenStore()
const isInCall = useIsInCall()
const callViewStore = useCallViewStore()
const talkHashStore = useTalkHashStore()
const settingsStore = useSettingsStore()
const soundsStore = useSoundsStore()

const router = useRouter()
const vuexStore = useStore()

const token = useGetToken()
const isMobile = useIsMobile()

const loading = ref(false)

const conversation = computed(() => vuexStore.getters.conversation(token.value) || vuexStore.getters.dummyConversation)
const showButtonText = computed(() => !props.hideText && (!isMobile.value || !props.shrinkOnMobile))
const hasCall = computed(() => conversation.value.hasCall)
const isPhoneRoom = computed(() => isConversationPhoneRoom(conversation.value))
const isInLobby = computed(() => vuexStore.getters.isInLobby)
const isJoiningCall = computed(() => vuexStore.getters.isJoiningCall(token.value))
const isNextcloudTalkHashDirty = computed(() => {
	return talkHashStore.isNextcloudTalkHashDirty
		// @ts-expect-error talkHashStore is js
		|| talkHashStore.isNextcloudTalkProxyHashDirty[token.value]
})

const showStartCallButton = computed(() => {
	return (getTalkConfig(token.value, 'call', 'enabled') || hasExternalCallService(conversation.value))
		&& conversation.value.type !== CONVERSATION.TYPE.NOTE_TO_SELF
		&& conversation.value.readOnly === CONVERSATION.STATE.READ_WRITE
		&& (!conversation.value.remoteServer || hasTalkFeature(token.value, 'federation-v2'))
		&& !isInCall.value
})
const startCallButtonDisabled = computed(() => {
	return props.disabled
		|| (callViewStore.callHasJustEnded && !hasCall.value)
		|| (!conversation.value.canStartCall && !hasExternalCallService(conversation.value) && !hasCall.value)
		|| isInLobby.value
		|| conversation.value.readOnly
		|| isNextcloudTalkHashDirty.value
		|| !tokenStore.currentConversationIsJoined
		|| blockCalls
})
const showRecordingWarning = computed(() => {
	return [
		CALL.RECORDING.VIDEO_STARTING,
		CALL.RECORDING.AUDIO_STARTING,
		CALL.RECORDING.VIDEO,
		CALL.RECORDING.AUDIO,
	].includes(conversation.value.callRecording)
	|| conversation.value.recordingConsent === CALL.RECORDING_CONSENT.ENABLED
})

const startCallLabel = computed(() => {
	if (hasCall.value && !isInLobby.value) {
		return t('spreed', 'Join call')
	}
	if (isJoiningCall.value) {
		return t('spreed', 'Connecting …')
	}
	return props.silentCall ? t('spreed', 'Start call silently') : t('spreed', 'Start call')
})
const startCallTitle = computed(() => {
	if (isNextcloudTalkHashDirty.value) {
		return t('spreed', 'The server was updated, you cannot start or join a call.') + ' ' + messagePleaseReload
	}
	if (callViewStore.callHasJustEnded) {
		return t('spreed', 'This call has just ended')
	}
	if (blockCalls) {
		return unsupportedWarning
	}
	if (!conversation.value.canStartCall && !hasCall.value) {
		return t('spreed', 'You will be able to join the call only after a moderator starts it.')
	}
	return ''
})

watch(token, (newValue, oldValue) => {
	callViewStore.resetCallHasJustEnded()
	talkHashStore.resetTalkProxyHashDirty(oldValue)
})

/**
 * Start or join the call
 */
async function handleJoinCall() {
	loading.value = true
	await joinCall(token.value, {
		silent: hasCall.value ? true : props.silentCall,
		recordingConsent: props.recordingConsentGiven,
		shouldStartRecording: props.isRecordingFromStart,
	})
	loading.value = false
}

/**
 * Run pre-checks before starting/joining the call
 */
function handleClick() {
	if (hasExternalCallService(conversation.value)) {
		// Another service is in charge, trigger iframe rendering in MainView
		handleExternalCall()
		return
	}

	// Create audio objects as a result of a user interaction to allow playing sounds in Safari
	soundsStore.initAudioObjects()

	if (props.isMediaSettings || isPhoneRoom.value) {
		handleJoinCall()
		return
	}

	if (showRecordingWarning.value || settingsStore.showMediaSettings) {
		emit('talk:media-settings:show')
	} else {
		handleJoinCall()
	}
}

/**
 * Initiate a call at external service (iframe embedded)
 */
async function handleExternalCall() {
	try {
		loading.value = true
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/external-call', { token: token.value }))

		// Check for successful response (200, 302, 303)
		if ([200, 302, 303].includes(response.status)) {
			const callUrl = response.data.ocs.data.url
			if (callUrl) {
				callViewStore.setExternalCallServiceUrl(callUrl)
			}
			callViewStore.setForceCallView(true)
		}
	} catch (error) {
		if (isAxiosErrorResponse(error) && error.response?.status === 403) {
			router.push({ name: 'forbidden' })
			return
		}
		console.error('Failed to initialize external call service:', error)
		showError(t('spreed', 'Connection failed'))
	} finally {
		loading.value = false
	}
}
</script>

<template>
	<NcButton
		v-if="showStartCallButton"
		:title="startCallTitle"
		:aria-label="startCallLabel"
		:disabled="startCallButtonDisabled || loading || isJoiningCall"
		class="join-call"
		:variant="hasCall ? 'success' : 'primary'"
		@click="handleClick">
		<template #icon>
			<NcLoadingIcon v-if="isJoiningCall || loading" :size="20" />
			<IconPhoneDialOutline v-else-if="isPhoneRoom" :size="20" />
			<IconPhoneOutline v-else-if="silentCall" :size="20" />
			<IconPhone v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ startCallLabel }}
		</template>
	</NcButton>
</template>

<style lang="scss" scoped>
.join-call.button-vue--success {
	// Overwrite default button colors for joining call
	--join-call-background-color: var(--color-border-success);
	--join-call-border-color: var(--color-success-text);
	border-color: var(--join-call-border-color);
	background-color: var(--join-call-background-color);
	color: var(--color-primary-element-text) !important;

	// Do not overwrite for dark theme
	body[data-theme-dark] & {
		--join-call-border-color: var(--color-success-hover);
	}
	@media (prefers-color-scheme: dark) {
		body[data-theme-default] & {
			--join-call-border-color: var(--color-success-hover);
		}
	}

	&:hover:not(:disabled) {
		background-color: var(--join-call-border-color);
	}
}
</style>
