/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCapabilities as _getCapabilities } from '@nextcloud/capabilities'
import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import { getRemoteCapabilities } from './federationService.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import type { Capabilities, JoinRoomFullResponse } from '../types'

type Config = Capabilities['spreed']['config']
type RemoteCapabilities = Record<string, Capabilities & Partial<{ hash: string }>>

const localCapabilities: Capabilities = _getCapabilities() as Capabilities
const remoteCapabilities: RemoteCapabilities = restoreRemoteCapabilities()

/**
 * Check whether the feature is presented (in case of federation - on both servers)
 * @param token conversation token
 * @param feature feature capability in string format
 */
export function hasTalkFeature(token: string = 'local', feature: string): boolean {
	const hasLocalTalkFeature = localCapabilities?.spreed?.features?.includes(feature) ?? false
	if (localCapabilities?.spreed?.['features-local']?.includes(feature)) {
		return hasLocalTalkFeature
	} else if (token === 'local' || !remoteCapabilities[token]) {
		return hasLocalTalkFeature
	} else {
		return hasLocalTalkFeature && (remoteCapabilities[token]?.spreed?.features?.includes(feature) ?? false)
	}
}

/**
 * Get an according config value from local or remote capabilities
 * @param token conversation token
 * @param key1 top-level key (e.g. 'attachments')
 * @param key2 second-level key (e.g. 'allowed')
 */
export function getTalkConfig(token: string = 'local', key1: keyof Config, key2: keyof Config[keyof Config]) {
	if (localCapabilities?.spreed?.['config-local']?.[key1]?.[key2]) {
		return localCapabilities?.spreed?.config?.[key1]?.[key2]
	} else if (token === 'local' || !remoteCapabilities[token]) {
		return localCapabilities?.spreed?.config?.[key1]?.[key2]
	} else {
		// TODO discuss handling remote config (respect remote only / both / minimal)
		return remoteCapabilities[token]?.spreed?.config?.[key1]?.[key2]
	}
}

/**
 * Compares talk hash from remote instance and fetch new capabilities if it doesn't match
 * @param joinRoomResponse server response
 */
export async function setRemoteCapabilities(joinRoomResponse: JoinRoomFullResponse): Promise<void> {
	const token = joinRoomResponse.data.ocs.data.token

	// Check if remote capabilities have not changed since last check
	if (joinRoomResponse.headers['x-nextcloud-talk-proxy-hash'] === remoteCapabilities[token]?.hash) {
		return
	}

	const response = await getRemoteCapabilities(token)
	if (Array.isArray(response.data.ocs.data)) {
		// unknown[] received from server, nothing to update with
		return
	}

	remoteCapabilities[token] = { spreed: response.data.ocs.data }
	remoteCapabilities[token].hash = joinRoomResponse.headers['x-nextcloud-talk-proxy-hash']
	BrowserStorage.setItem('remoteCapabilities', JSON.stringify(remoteCapabilities))

	// As normal capabilities update, requires a reload to take effect
	showError(t('spreed', 'Nextcloud Talk Federation was updated, please reload the page'), {
		timeout: TOAST_PERMANENT_TIMEOUT,
	})
}

/**
 * Restores capabilities from BrowserStorage
 */
function restoreRemoteCapabilities(): RemoteCapabilities {
	const remoteCapabilities = BrowserStorage.getItem('remoteCapabilities')
	if (!remoteCapabilities?.length) {
		return {}
	}

	return JSON.parse(remoteCapabilities) as RemoteCapabilities
}
