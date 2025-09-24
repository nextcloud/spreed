<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<CallView :token="token" is-recording />
</template>

<script>
import CallView from './components/CallView/CallView.vue'
import { useGetToken } from './composables/useGetToken.ts'
import { useSoundsStore } from './stores/sounds.js'
import { useTokenStore } from './stores/token.ts'
import { signalingKill } from './utils/webrtc/index.js'

export default {
	name: 'Recording',

	components: {
		CallView,
	},

	setup() {
		return {
			soundsStore: useSoundsStore(),
			token: useGetToken(),
			tokenStore: useTokenStore(),
		}
	},

	async beforeMount() {
		await this.$router.isReady()
		if (this.$route.name === 'recording') {
			this.tokenStore.updateToken(this.$route.params.token)

			await this.soundsStore.setShouldPlaySounds(false)
		}

		// This should not be strictly needed, as the recording server is
		// expected to clean up before leaving, but just in case.
		window.addEventListener('unload', () => {
			console.info('Navigating away, leaving conversation')
			if (this.token) {
				// We have to do this synchronously, because in unload and
				// beforeunload Promises, async and await are prohibited.
				signalingKill()
			}
		})
	},
}
</script>

<style lang="scss" scoped>
/* The CallView descendants expect border-box to be set, as in the normal UI the
 * CallView is a descendant of NcContent, which applies the border-box to all
 * its descendants.
 */
#call-container {
	:deep(*) {
		box-sizing: border-box;
	}

	:deep(#videos) {
		inset: 0;
		height: 100%;
	}
}
</style>
