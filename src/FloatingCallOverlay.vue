<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from './types/index.ts'

import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import VueDraggableResizable from 'vue-draggable-resizable'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconDragHorizontal from 'vue-material-design-icons/DragHorizontal.vue'
import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import CallFailedDialog from './components/CallView/CallFailedDialog.vue'
import CallView from './components/CallView/CallView.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import CallTime from './components/TopBar/CallTime.vue'
import { useHashCheck } from './composables/useHashCheck.js'
import { useIsInCall } from './composables/useIsInCall.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import { PARTICIPANT, SIGNALING } from './constants.ts'
import { getTalkConfig } from './services/CapabilitiesManager.ts'
import { EventBus } from './services/EventBus.ts'
import {
	leaveConversationSync,
} from './services/participantsService.js'
import SessionStorage from './services/SessionStorage.js'
import { useActorStore } from './stores/actor.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'
import { generateAbsoluteUrl } from './utils/handleUrl.ts'
import { signalingKill, signalingWebRtcKill } from './utils/webrtc/index.js'

const props = defineProps<{
	token: string
}>()

const vuexStore = useStore()
const router = useRouter()
useHashCheck()

const isInCall = useIsInCall()
const isLeavingAfterSessionIssue = useSessionIssueHandler()
const actorStore = useActorStore()
const tokenStore = useTokenStore()

const overlayWidth = 400
const overlayHeight = 300

let fetchCurrentConversationIntervalId: NodeJS.Timeout | number | undefined
const joiningConversation = ref(false)
const recordingConsentGiven = ref(false)
const overlayX = ref(20)
const overlayY = ref(20)
const resizeObserver = ref<ResizeObserver | null>(null)

const floatingCallContainer = useTemplateRef<HTMLDivElement>('floatingCallContainer')
const floatingCallResizable = useTemplateRef<InstanceType<typeof VueDraggableResizable>>('floatingCallResizable')

const conversation = computed<Conversation>(() => vuexStore.getters.conversation(props.token))
const warnLeaving = computed(() => !isLeavingAfterSessionIssue.value && isInCall.value)
const connectionFailed = computed(() => vuexStore.getters.connectionFailed(props.token))

watch(isInCall, (newValue) => {
	if (!newValue) {
		// end the call, unmount app
		window.OCA.Talk.unmountInstance!()
	}
})

window.addEventListener('beforeunload', preventUnload)

onMounted(() => {
	resizeObserver.value = new ResizeObserver(updateOverlayBounds)
	resizeObserver.value.observe(floatingCallContainer.value!)

	window.addEventListener('unload', syncLeaveConversation)
	joinConversation()
})

onBeforeUnmount(() => {
	if (resizeObserver.value) {
		resizeObserver.value.disconnect()
	}
	window.clearInterval(fetchCurrentConversationIntervalId)
	EventBus.off('should-refresh-conversations', fetchCurrentConversation)
	EventBus.off('signaling-participant-list-changed', fetchCurrentConversation)
	fetchCurrentConversationIntervalId = undefined
	window.removeEventListener('beforeunload', preventUnload)
	syncLeaveConversation()
})

/**
 *
 */
function syncLeaveConversation() {
	if (props.token) {
		// We have to do this synchronously, because in unload and beforeunload
		// Promises, async and await are prohibited.
		signalingKill()
		signalingWebRtcKill()
		if (!isLeavingAfterSessionIssue.value) {
			leaveConversationSync(props.token)
		}
	}
}

/**
 *
 */
function openInNewTab() {
	const url = generateAbsoluteUrl('/call/{token}#direct-call', { token: props.token })
	// Talk app is opened with the same origin, so SessionStorage will persist -> 'x-nextcloud-talk-session-tab-id' conflicts
	SessionStorage.setItem('force-new-talk-session-tab-id', 'true')
	window.open(url, '_blank')

	// FIXME should drop the floating call once main call is joined
}

/**
 *
 */
function updateOverlayBounds() {
	if (!floatingCallResizable.value) {
		return
	}
	// FIXME: inner method should be triggered to re-parent element
	floatingCallResizable.value.checkParentSize()
	// FIXME: if it stays out of bounds (right and bottom), bring it back
	if (floatingCallResizable.value.right < 0 && floatingCallResizable.value.parentWidth > /* props.w */overlayWidth) {
		floatingCallResizable.value.moveHorizontally(floatingCallResizable.value.parentWidth - /* props.w */overlayWidth)
	}
	if (floatingCallResizable.value.bottom < 0 && floatingCallResizable.value.parentHeight > /* props.h */overlayHeight) {
		floatingCallResizable.value.moveVertically(floatingCallResizable.value.parentHeight - /* props.h */overlayHeight)
	}
}

/**
 *
 * @param event
 */
function preventUnload(event: Event) {
	if (!warnLeaving.value) {
		return
	}

	event.preventDefault()
}

/**
 *
 */
async function joinConversation() {
	checkBrowser()

	joiningConversation.value = true

	try {
		tokenStore.updateToken(props.token)
		actorStore.setCurrentUser(getCurrentUser())

		await router.push({ name: 'conversation', params: { token: props.token } })
		await vuexStore.dispatch('joinConversation', { token: props.token })
	} catch (exception) {
		joiningConversation.value = false

		showError(t('spreed', 'Error occurred when joining the conversation'))
		console.error(exception)
		window.OCA.Talk.unmountInstance!()

		return
	}

	// No need to wait for it, but fetching the conversation needs to be
	// done once the user has joined the conversation (otherwise only
	// limited data would be received if the user was not a participant
	// of the conversation yet).
	fetchCurrentConversation()

	// FIXME The participant will not be updated with the server data
	// when the conversation is got again (as "addParticipantOnce" is
	// used), although that should not be a problem given that only the
	// "inCall" flag (which is locally updated when joining and leaving
	// a call) is currently used.
	if (getTalkConfig(props.token, 'signaling', 'mode') !== SIGNALING.MODE.INTERNAL) {
		EventBus.on('should-refresh-conversations', fetchCurrentConversation)
		EventBus.on('signaling-participant-list-changed', fetchCurrentConversation)
	} else {
		// The "should-refresh-conversations" event is triggered only when
		// the external signaling server is used; when the internal
		// signaling server is used periodic polling has to be used
		// instead.
		fetchCurrentConversationIntervalId = window.setInterval(fetchCurrentConversation, 30000)
	}

	let flags = PARTICIPANT.CALL_FLAG.IN_CALL
	if (conversation.value.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO) {
		flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
	}
	if (conversation.value.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO) {
		flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
	}

	await vuexStore.dispatch('joinCall', {
		token: props.token,
		participantIdentifier: actorStore.participantIdentifier,
		flags,
		silent: false,
		recordingConsent: false,
	})
}

/**
 *
 */
async function fetchCurrentConversation() {
	if (!props.token) {
		return
	}

	try {
		await vuexStore.dispatch('fetchConversation', { token: props.token })

		// Although the current participant is automatically added to
		// the participants store it must be explicitly set in the
		// actors store.
		if (!actorStore.userId) {
			// Set the current actor/participant for guests
			const conversation = vuexStore.getters.conversation(props.token)

			// Setting a guest only uses "sessionId" and "participantType".
			actorStore.setCurrentParticipant(conversation)
		}
	} catch (exception) {
		window.clearInterval(fetchCurrentConversationIntervalId)
		fetchCurrentConversationIntervalId = undefined

		vuexStore.dispatch('deleteConversation', props.token)
		tokenStore.updateToken('')
	}

	joiningConversation.value = false
}
</script>

<template>
	<div ref="floatingCallContainer" class="floating-call__container">
		<VueDraggableResizable
			ref="floatingCallResizable"
			parent
			class="floating-call"
			classNameDragging="floating-call--dragging"
			:w="overlayWidth"
			:h="overlayHeight"
			:x="overlayX"
			:y="overlayY"
			:minWidth="overlayWidth"
			:minHeight="overlayHeight"
			:resizable="false">
			<div class="floating-call__bar">
				<span class="floating-call__name">{{ conversation.displayName }}</span>
				<IconDragHorizontal :size="30" />
				<div class="floating-call__controls">
					<CallTime v-if="isInCall" :start="conversation.callStartTime" />
					<NcButton
						:title="t('spreed', 'Open in full application')"
						variant="tertiary-no-background"
						@click="openInNewTab">
						<template #icon>
							<IconOpenInNew :size="20" fillColor="var(--color-primary-text)" />
						</template>
					</NcButton>
				</div>
			</div>
			<div class="floating-call__app">
				<CallView :token="token" isSidebar />
				<CallFailedDialog v-if="connectionFailed" :token="token" />
				<MediaSettings v-model:recordingConsentGiven="recordingConsentGiven" />
			</div>
		</VueDraggableResizable>
	</div>
</template>

<style lang="scss" scoped>
@use './assets/variables.scss' as *;

.floating-call__container {
	position: fixed;
	inset: 0;
	z-index: 2000;

	// Make container transparent to user events
	pointer-events: none;

	& .floating-call__bar,
	& .floating-call__bar *,
	& .floating-call__app,
	& .floating-call__app * {
		pointer-events: auto;
		box-sizing: border-box;
	}
}

:deep(div) {
	// prevent default cursor
	cursor: inherit;
}

.floating-call {
	border-radius: var(--border-radius-element);
	box-shadow: 0 0 4px 0 var(--color-box-shadow);

	&:hover {
		cursor: grab;
	}

	&--dragging {
		cursor: grabbing;
	}
}

.floating-call__bar {
	display: grid;
	grid-template-columns: 1fr var(--default-clickable-area) 1fr;
	gap: var(--default-grid-baseline);
	align-items: center;

	height: calc(var(--default-clickable-area) + var(--default-grid-baseline));
	padding: calc(0.5 * var(--default-grid-baseline)) calc(2 * var(--default-grid-baseline));
	border-start-start-radius: var(--border-radius-element);
	border-start-end-radius: var(--border-radius-element);

	color: var(--color-primary-text);
	background-color: var(--color-primary);
}

.floating-call__name {
	font-weight: 700;

	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
}

.floating-call__controls {
	display: flex;
	justify-content: flex-end;
	gap: var(--default-grid-baseline);
}

.floating-call__app {
	position: relative;
	display: flex;
	flex-direction: column;
	justify-content: center;

	height: calc(100% - 34px);
	width: 100%;
	border-end-start-radius: var(--border-radius-element);
	border-end-end-radius: var(--border-radius-element);

	background: $color-call-background;

	overflow: hidden;
}
</style>
