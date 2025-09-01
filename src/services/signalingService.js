/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetches the signaling settings for a conversation
 *
 * @param {string} token The token of the conversation to be signaled.
 * @param {object} options options
 */
async function fetchSignalingSettings({ token }, options) {
	return axios.get(generateOcsUrl('apps/spreed/api/v3/signaling/settings'), Object.assign(options, {
		params: {
			token,
		},
	}))
}

/**
 *
 * @param token
 * @param options
 */
async function pullSignalingMessages(token, options) {
	return axios.get(generateOcsUrl('apps/spreed/api/v3/signaling/{token}', { token }), options)
}

/**
 *
 * @param serverId
 */
async function getWelcomeMessage(serverId) {
	return axios.get(generateOcsUrl('apps/spreed/api/v3/signaling/welcome/{serverId}', { serverId }))
}

export {
	fetchSignalingSettings,
	getWelcomeMessage,
	pullSignalingMessages,
}
