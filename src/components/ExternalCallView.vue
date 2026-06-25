<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, useTemplateRef } from 'vue'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'

const { token } = defineProps<{
	token: string
}>()

const actorStore = useActorStore()
const callViewStore = useCallViewStore()

const externalWrapper = useTemplateRef<HTMLDivElement>('externalWrapper')
let externalWrapperObserver: MutationObserver

const externalCallServiceUrl = computed(() => {
	if (callViewStore.externalCallServiceUrl) {
		return callViewStore.externalCallServiceUrl
	}

	// Fallback to provided URL by the config
	const url = new URL(getTalkConfig('local', 'call', 'external-call-service')!)
	url.searchParams.set('room', token)
	url.searchParams.set('userId', actorStore.actorId!)

	return url.toString()
})

onMounted(() => {
	externalWrapperObserver = new MutationObserver(() => {
		const iframe = externalWrapper.value!.querySelector('iframe')
		if (!iframe) {
			onIframeRemoved()
			externalWrapperObserver.disconnect()
		}
	})

	externalWrapperObserver.observe(externalWrapper.value!, { childList: true, subtree: true })
	window.addEventListener('message', onMessage)
})

onBeforeUnmount(() => {
	window.removeEventListener('message', onMessage)
	onIframeRemoved()
})

/**
 * Handle messages from the external call service iframe.
 * Ignores messages from origins that don't match the service URL.
 *
 * @param event
 */
function onMessage(event: MessageEvent) {
	const serviceOrigin = new URL(externalCallServiceUrl.value).origin
	if (event.origin !== serviceOrigin) {
		return
	}

	if (!event.data) {
		return
	}

	switch (event.data.type) {
		case 'externalCallJoined':
			// TODO: handle side-effects, e.g.:
			// - fake inCall flag via joinCall(token, PARTICIPANT.CALL_FLAG.IN_CALL, silent, ...) from callsService
			// - set Nextcloud user status to DND/busy via OCA.UserStatus API
			// - post a system message to the Talk chat via postNewMessage() from messagesService
			break
		case 'externalCallLeft':
			// TODO: handle side-effects, e.g.:
			// - clear faked inCall flag via leaveCall(token) from callsService
			// - restore Nextcloud user status to previous value
			// - post a system message to the Talk chat via postNewMessage() from messagesService
			onIframeRemoved()
			break
		default:
			// TODO: log unknown message types for debugging during integration
			break
	}
}

/**
 * Handle cleanup after iframe is unmounted / removed:
 * - by navigating to other room (warnLeaving to leave call)
 * - by 3rd-party destroying an iframe (e.g. with `iframe.parentNode.removeChild(iframe)`)
 * - by the iframe sending a 'externalCallLeft' postMessage
 */
function onIframeRemoved() {
	externalWrapperObserver?.disconnect()
	callViewStore.setExternalCallServiceUrl(null)
	callViewStore.setForceCallView(false)
}
</script>

<template>
	<div ref="externalWrapper" class="external-call-view">
		<iframe
			:src="externalCallServiceUrl"
			title="External Call Service"
			class="external-call-view__iframe"
			sandbox="allow-same-origin allow-scripts allow-forms allow-popups allow-presentation"
			allow="camera; microphone; display-capture; fullscreen"
			loading="eager"
			referrerpolicy="no-referrer"
			crossorigin="anonymous" />
	</div>
</template>

<style lang="scss" scoped>
.external-call-view {
	width: 100%;
	height: 100%;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	position: relative;
	background: var(--color-main-background);

	&__iframe {
		width: 100%;
		height: 100%;
		border: none;
		flex-grow: 1;
	}
}
</style>
