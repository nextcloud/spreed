<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { onBeforeUnmount } from 'vue'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { useCallViewStore } from '../stores/callView.ts'

const { token } = defineProps<{
	token: string
}>()

const callViewStore = useCallViewStore()

const externalCallServiceUrl = getTalkConfig('local', 'call', 'external-call-service') ?? ''

onBeforeUnmount(() => {
	callViewStore.setForceCallView(false)
})
</script>

<template>
	<div class="external-call-view">
		<iframe
			:src="externalCallServiceUrl"
			title="External Call Service"
			class="external-call-view__iframe"
			sandbox="allow-scripts allow-forms allow-popups allow-presentation"
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
