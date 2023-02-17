<!--
  - @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<CallView :token="token"
		:is-recording="true" />
</template>

<script>
import {
	PARTICIPANT,
} from './constants.js'
import {
	joinCall,
} from './services/callsService.js'
import {
	leaveConversationSync,
} from './services/participantsService.js'
import {
	mediaDevicesManager,
	signalingKill,
} from './utils/webrtc/index.js'
import CallView from './components/CallView/CallView.vue'

export default {
	name: 'Recording',

	components: {
		CallView,
	},

	computed: {
		/**
		 * The current conversation token
		 *
		 * @return {string} The token.
		 */
		token() {
			return this.$store.getters.getToken()
		},
	},

	async beforeMount() {
		if (this.$route.name === 'recording') {
			await this.$store.dispatch('updateToken', this.$route.params.token)

			await this.$store.dispatch('setPlaySounds', false)

			await this.$store.dispatch('joinConversation', { token: this.token })

			mediaDevicesManager.set('audioInputId', null)
			mediaDevicesManager.set('videoInputId', null)

			await joinCall(this.token, PARTICIPANT.CALL_FLAG.IN_CALL, false)
		}

		window.addEventListener('unload', () => {
			console.info('Navigating away, leaving conversation')
			if (this.token) {
				// We have to do this synchronously, because in unload and
				// beforeunload Promises, async and await are prohibited.
				signalingKill()

				leaveConversationSync(this.token)
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
}
</style>
