/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import {
	generateOcsUrl,
	generateUrl,
} from '@nextcloud/router'

/**
 * Edit the bridge of a room
 *
 * @param {token} token the conversation token.
 * @param {string} enabled state of the bridge
 * @param {string} parts parts of the bridge, where it has to connect
 */
const editBridge = async function(token, enabled, parts) {
	const response = await axios.put(generateOcsUrl('apps/spreed/api/v1/bridge/{token}', { token }), {
		token,
		enabled,
		parts,
	})
	return response
}

/**
 * Get the bridge of a room
 *
 * @param {token} token the conversation token.
 */
const getBridge = async function(token) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/bridge/{token}', { token }))
	return response
}

/**
 * Get the bridge binary state for a room
 *
 * @param {token} token the conversation token.
 */
const getBridgeProcessState = async function(token) {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/bridge/{token}/process', { token }))
	return response
}

/**
 * Ask to stop all bridges (and kill all related processes)
 */
const stopAllBridges = async function() {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1/bridge'))
	return response
}

const enableMatterbridgeApp = async function() {
	const response = await axios.post(generateUrl('settings/apps/enable/talk_matterbridge'))
	return response
}

const getMatterbridgeVersion = async function() {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/bridge/version'))
	return response
}

export {
	editBridge,
	enableMatterbridgeApp,
	getBridge,
	getBridgeProcessState,
	getMatterbridgeVersion,
	stopAllBridges,
}
