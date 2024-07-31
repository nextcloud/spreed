/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCapabilities as _getCapabilities } from '@nextcloud/capabilities'
import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import { getRemoteCapabilities } from './federationService.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { useTalkHashStore } from '../stores/talkHash.js'
import type { Capabilities, JoinRoomFullResponse } from '../types'

type Config = Capabilities['spreed']['config']
type RemoteCapability = Capabilities & Partial<{ hash: string, tokens: string[] }>
type RemoteCapabilities = Record<string, RemoteCapability>

const localCapabilities: Capabilities = _getCapabilities() as Capabilities
const remoteCapabilities: RemoteCapabilities = restoreRemoteCapabilities()
let remoteTokenMap: Record<string, string> = generateTokenMap()

/**
 * Generate new token map based on remoteCapabilities
 */
function generateTokenMap() {
	const tokenMap: Record<string, string> = {}
	Object.keys(remoteCapabilities).forEach(remoteServer => {
		remoteCapabilities[remoteServer].tokens?.forEach(token => {
			tokenMap[token] = remoteServer
		})
	})
	return tokenMap
}

/**
 * Check whether the feature is presented (in case of federation - on both servers)
 * @param token conversation token
 * @param feature feature capability in string format
 */
export function hasTalkFeature(token: string = 'local', feature: string): boolean {
	const hasLocalTalkFeature = localCapabilities?.spreed?.features?.includes(feature) ?? false
	const remoteCapabilities = getRemoteCapability(token)
	if (localCapabilities?.spreed?.['features-local']?.includes(feature)) {
		return hasLocalTalkFeature
	} else if (token === 'local' || !remoteCapabilities) {
		return hasLocalTalkFeature
	} else {
		return hasLocalTalkFeature && (remoteCapabilities?.spreed?.features?.includes(feature) ?? false)
	}
}

/**
 * Get an according config value from local or remote capabilities
 * @param token conversation token
 * @param key1 top-level key (e.g. 'attachments')
 * @param key2 second-level key (e.g. 'allowed')
 */
export function getTalkConfig(token: string = 'local', key1: keyof Config, key2: keyof Config[keyof Config]) {
	const remoteCapabilities = getRemoteCapability(token)
	if (localCapabilities?.spreed?.['config-local']?.[key1]?.includes(key2)) {
		return localCapabilities?.spreed?.config?.[key1]?.[key2]
	} else if (token === 'local' || !remoteCapabilities) {
		return localCapabilities?.spreed?.config?.[key1]?.[key2]
	} else {
		// TODO discuss handling remote config (respect remote only / both / minimal)
		return remoteCapabilities?.spreed?.config?.[key1]?.[key2]
	}
}

/**
 * Returns capability for specified token (if already matches from one of remote servers)
 * @param token token of the conversation
 */
function getRemoteCapability(token: string): RemoteCapability | null {
	if (remoteCapabilities[remoteTokenMap[token]]) {
		return remoteCapabilities[remoteTokenMap[token]]
	}

	const cachedConversations = BrowserStorage.getItem('cachedConversations')
	if (!cachedConversations?.length) {
		return null
	}

	const remoteServer = JSON.parse(cachedConversations)?.[token]?.remoteServer

	if (remoteServer && remoteCapabilities[remoteServer]) {
		console.debug(`Reuse remote capabilities from another conversation (same remote server ${remoteServer})`)
		remoteCapabilities[remoteServer].tokens = [...new Set((remoteCapabilities[remoteServer].tokens || []).concat(token))]
		BrowserStorage.setItem('remoteCapabilities', JSON.stringify(remoteCapabilities))
		remoteTokenMap = generateTokenMap()
		return remoteCapabilities[remoteServer]
	}

	return null
}

/**
 * Compares talk hash from remote instance and fetch new capabilities if it doesn't match
 * @param joinRoomResponse server response
 */
export async function setRemoteCapabilities(joinRoomResponse: JoinRoomFullResponse): Promise<void> {
	const token = joinRoomResponse.data.ocs.data.token
	const remoteServer = joinRoomResponse.data.ocs.data.remoteServer as string

	// Check if remote capabilities have not changed since last check
	if (joinRoomResponse.headers['x-nextcloud-talk-proxy-hash'] === remoteCapabilities[remoteServer]?.hash) {
		return
	}

	// Mark the hash as dirty to prevent any activity in the conversation
	const talkHashStore = useTalkHashStore()
	talkHashStore.setTalkProxyHashDirty(token)

	const response = await getRemoteCapabilities(token)
	if (Array.isArray(response.data.ocs.data)) {
		// unknown[] received from server, nothing to update with
		return
	}

	remoteCapabilities[remoteServer] = { spreed: response.data.ocs.data }
	remoteCapabilities[remoteServer].hash = joinRoomResponse.headers['x-nextcloud-talk-proxy-hash']
	remoteCapabilities[remoteServer].tokens = [...new Set((remoteCapabilities[remoteServer].tokens || []).concat(token))]
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
	const storageValue = BrowserStorage.getItem('remoteCapabilities')
	if (!storageValue) {
		return {}
	}

	// Migration step: remove capabilities not based on remoteServer
	const remoteCapabilities = JSON.parse(storageValue) as RemoteCapabilities
	Object.keys(remoteCapabilities).forEach(remoteServer => {
		if (!remoteCapabilities[remoteServer].tokens) {
			delete remoteCapabilities[remoteServer]
		}
	})

	return remoteCapabilities
}
