<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { onBeforeMount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import CallView from './components/CallView/CallView.vue'
import { useGetToken } from './composables/useGetToken.ts'
import { useSoundsStore } from './stores/sounds.js'
import { useTokenStore } from './stores/token.ts'
import { signalingKill } from './utils/webrtc/index.js'

const router = useRouter()
const route = useRoute()

const soundsStore = useSoundsStore()
const token = useGetToken()
const tokenStore = useTokenStore()

onBeforeMount(async () => {
	await router.isReady()
	if (route.name === 'recording') {
		tokenStore.updateToken(route.params.token as string)
		await soundsStore.setShouldPlaySounds(false)
	}

	// This should not be strictly needed, as the recording server is
	// expected to clean up before leaving, but just in case.
	window.addEventListener('unload', () => {
		console.info('Navigating away, leaving conversation')
		if (token.value) {
			// We have to do this synchronously, because in unload and
			// beforeunload Promises, async and await are prohibited.
			signalingKill()
		}
	})
})
</script>

<template>
	<CallView :token="token" is-recording />
</template>

<style lang="scss">
/** Hide public footer gap from recording */
#body-public {
	--footer-height: 0 !important;
}

/** Hide public interface from recording */
#header .header-end {
	display: none !important;
}

/* The CallView descendants expect border-box to be set, as in the normal UI the
 * CallView is a descendant of NcContent, which applies the border-box to all
 * its descendants.
 */
#call-container {
	--wrapper-padding: 0 !important;

	* {
		box-sizing: border-box;
	}

	#videos {
		inset: 0;
		height: 100%;
	}

	.video-container {
		width: 100%;
	}
}
</style>
