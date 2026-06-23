/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { onBeforeUnmount } from 'vue'
import { useStore } from 'vuex'
import { CALL, PARTICIPANT } from '../constants.ts'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import { useTokenStore } from '../stores/token.ts'

/**
 * Composable for registering and cleaning up global EventBus listeners
 */
export function useRecordingStatusSync() {
	const tokenStore = useTokenStore()
	const vuexStore = useStore()

	if (getTalkConfig('local', 'signaling', 'mode') !== 'internal') {
		EventBus.on('signaling-recording-status-changed', handleSignalingRecordingStatusChanged)
	}

	onBeforeUnmount(() => {
		EventBus.off('signaling-recording-status-changed', handleSignalingRecordingStatusChanged)
	})

	/**
	 * Handle signaling 'processRoomMessageEvent -> recording' event for status change
	 *
	 * @param payload
	 * @param payload."0" - conversation token
	 * @param payload."1" - recording status
	 */
	function handleSignalingRecordingStatusChanged([token, status]: [string, number]) {
		vuexStore.dispatch('setConversationProperties', { token, properties: { callRecording: status } })

		if (status !== CALL.RECORDING.FAILED) {
			return
		}

		if (!vuexStore.getters.isInCall(tokenStore.token)) {
			return
		}

		const conversation = vuexStore.getters.conversation(tokenStore.token)
		if (conversation?.participantType === PARTICIPANT.TYPE.OWNER
			|| conversation?.participantType === PARTICIPANT.TYPE.MODERATOR) {
			showError(t('spreed', 'The recording failed. Please contact your administrator.'))
		}
	}
}
