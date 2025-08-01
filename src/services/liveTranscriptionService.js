/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Enable live transcription
 *
 * @param {string} token conversation token
 */
const enableLiveTranscription = async (token) => {
	const signalingSessionId = window.signaling.getSessionId()

	await axios.post(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}', { token }), {
		signalingSessionId,
	})
}

/**
 * Disable live transcription
 *
 * @param {string} token conversation token
 */
const disableLiveTranscription = async (token) => {
	const signalingSessionId = window.signaling.getSessionId()

	await axios.delete(generateOcsUrl('apps/spreed/api/v1/live-transcription/{token}?signalingSessionId={signalingSessionId}', { token, signalingSessionId }))
}

export {
	enableLiveTranscription,
	disableLiveTranscription,
}
