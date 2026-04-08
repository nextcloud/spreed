<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="floatingCallContainer" class="floating-call-overlay__container">
		<VueDraggableResizable
			ref="floatingCallResizable"
			parent
			class="floating-call-overlay"
			:w="overlayWidth"
			:h="overlayHeight"
			:x="overlayX"
			:y="overlayY"
			:minWidth="overlayWidth"
			:minHeight="overlayHeight"
			:resizable="false"
			:class="{ dragging: isDragging }"
			@dragging="isDragging = true"
			@dragstop="isDragging = false">
			<div id="talk-sidebar">
				<TopBar v-if="isInCall" isInCall isSidebar />
				<CallView v-if="isInCall" :token="token" isSidebar />
				<div v-else>
					Loading...
				</div>
				<CallFailedDialog v-if="connectionFailed" :token="token" />
				<MediaSettings v-model:recordingConsentGiven="recordingConsentGiven" />
			</div>
		</VueDraggableResizable>
	</div>
</template>

<script setup lang="ts">
import { getCurrentUser } from '@nextcloud/auth'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import {computed, ref, useTemplateRef, watch, onMounted, onBeforeMount, onBeforeUnmount } from 'vue'
import VueDraggableResizable from 'vue-draggable-resizable'
import CallFailedDialog from './components/CallView/CallFailedDialog.vue'
import CallView from './components/CallView/CallView.vue'
import MediaSettings from './components/MediaSettings/MediaSettings.vue'
import TopBar from './components/TopBar/TopBar.vue'
import { useGetMessagesProvider } from './composables/useGetMessages.ts'
import { useHashCheck } from './composables/useHashCheck.js'
import { useIsInCall } from './composables/useIsInCall.js'
import { useSessionIssueHandler } from './composables/useSessionIssueHandler.ts'
import {PARTICIPANT, SIGNALING} from './constants.ts'
import { EventBus } from './services/EventBus.ts'
import {
	leaveConversationSync,
} from './services/participantsService.js'
import { useActorStore } from './stores/actor.ts'
import { useTokenStore } from './stores/token.ts'
import { checkBrowser } from './utils/browserCheck.ts'
import { signalingKill } from './utils/webrtc/index.js'
import {useStore} from "vuex";
import {useRouter} from "vue-router";
import {getTalkConfig} from "./services/CapabilitiesManager.ts";

const	props = defineProps<{
	token: string,
}>()

const vuexStore = useStore()
const router = useRouter()
useHashCheck()
useGetMessagesProvider()

const isInCall = useIsInCall()
const isLeavingAfterSessionIssue = useSessionIssueHandler()
const actorStore = useActorStore()
const tokenStore = useTokenStore()

const overlayWidth = 300
const overlayHeight = 200

let fetchCurrentConversationIntervalId: NodeJS.Timeout | number | undefined
const joiningConversation = ref(false)
const recordingConsentGiven = ref(false)
const overlayX = ref(Math.max(0, window.innerWidth - overlayWidth - 20))
const overlayY = ref(Math.max(0, window.innerHeight - overlayHeight - 20))
const isDragging = ref(false)
const resizeObserver = ref<ResizeObserver | null>(null)

const floatingCallContainer = useTemplateRef('floatingCallContainer')
const floatingCallResizable = useTemplateRef<InstanceType<typeof VueDraggableResizable>>('floatingCallResizable')

const conversation = computed(() => vuexStore.getters.conversation(props.token))
const warnLeaving = computed(() => isLeavingAfterSessionIssue.value && isInCall.value)
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
})

onBeforeMount(() => {
	window.addEventListener('unload', () => {
		if (props.token) {
			// We have to do this synchronously, because in unload and beforeunload
			// Promises, async and await are prohibited.
			signalingKill()
			if (!isLeavingAfterSessionIssue.value) {
				leaveConversationSync(props.token)
			}
		}
	})
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
})

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

function preventUnload(event: Event) {
	if (!warnLeaving.value) {
		return
	}

	event.preventDefault()
}

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

<style lang="scss" scoped>
.floating-call-overlay__container {
	position: fixed;
	inset: 0;
	pointer-events: none;
	z-index: 2000;

	& > * {
		pointer-events: auto;
	}
}

.floating-call-overlay {
	&:hover {
		cursor: grab;
	}

	&.dragging {
		cursor: grabbing;
	}
}

:deep(div) {
	// prevent VueDraggableResizable default cursor from overriding
	cursor: inherit;
}

/* Properties based on the app-sidebar */
#talk-sidebar {
	height: 100%;
	width: 100%;
	position: relative;

	background: var(--color-main-background);
	border: 1px solid var(--color-border);

	overflow-x: hidden;
	overflow-y: auto;

	display: flex;
	flex-direction: column;
	justify-content: center;
}

#talk-sidebar > .emptycontent {
	/* Remove default margin-top as it is unneeded when showing only the empty
	 * content in a flex sidebar. */
	margin-top: 0;
}

#talk-sidebar .call-button {
	margin: calc(var(--default-grid-baseline) * 2) auto;
}

#talk-sidebar .button-centered {
	/*
	 * When there is an icon the servers empty-content rule
	 * .emptycontent [class*="icon-"] is matching button-vue--icon-and-text
	 * setting the height to 64px, so we need to reset this.
	 */
	height: var(--default-clickable-area) !important;
	margin: 0 auto;
}

#talk-sidebar #call-container {
	position: relative;

	flex-grow: 1;

	/* Prevent shadows of videos from leaking on other elements. */
	overflow: hidden;

	/* Show the call container in a 16/9 proportion based on the sidebar
	 * width. This is the same proportion used for previews of images by the
	 * SidebarPreviewManager. */
	padding-bottom: 56.25%;
	max-height: 56.25%;

	/* Override the call container height so it properly adjusts to the 16/9
	 * proportion. */
	height: unset;
}

#talk-sidebar #call-container :deep(.videoContainer) {
	/* The video container has some small padding to prevent the video from
	 * reaching the edges, but it also uses "width: 100%", so the padding should
	 * be included in the full width of the element. */
	box-sizing: border-box;
}

#talk-sidebar #call-container :deep(.videoContainer.promoted video) {
	/* Base the size of the video on its width instead of on its height;
	 * otherwise the video could appear in full height but cropped on the sides
	 * due to the space available in the sidebar being typically larger in
	 * vertical than in horizontal. */
	width: 100%;
	height: auto;
}

#talk-sidebar #call-container :deep(.nameIndicator) {
	/* The name indicator has some small padding to prevent the name from
	 * reaching the edges, but it also uses "width: 100%", so the padding should
	 * be included in the full width of the element. */
	box-sizing: border-box;
}

#talk-sidebar .chatView {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	position: relative;

	flex-grow: 1;

	/* Distribute available height between call container and chat view. */
	height: 50%;
}
</style>
