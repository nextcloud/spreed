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
import type { Capabilities, Conversation, JoinRoomFullResponse } from '../types/index.ts'

type Config = Capabilities['spreed']['config']
type RemoteCapability = Capabilities & Partial<{ hash: string }>
type RemoteCapabilities = Record<string, RemoteCapability>
type TokenMap = Record<string, string|undefined|null>

let remoteTokenMap: TokenMap = generateTokenMap()

const localCapabilities: Capabilities = _getCapabilities() as Capabilities
const remoteCapabilities: RemoteCapabilities = restoreRemoteCapabilities()

/**
 * Generate new token map based on remoteCapabilities and cachedConversation
 */
function generateTokenMap() {
	const tokenMap: TokenMap = {}
	const storageValue = BrowserStorage.getItem('cachedConversations')
	if (!storageValue?.length) {
		return {}
	}
	const cachedConversations = JSON.parse(storageValue) as Conversation[]
	cachedConversations.forEach(conversation => {
		tokenMap[conversation.token] = conversation.remoteServer || null
	})

	return tokenMap
}

/**
 * Patch token map with new / updated remote conversation
 * @param conversation conversation object from join response
 */
function patchTokenMap(conversation: Conversation) {
	if (conversation.remoteServer) {
		remoteTokenMap[conversation.token] = conversation.remoteServer
	}
}

/**
 * Get current Talk version in string format
 */
export function getTalkVersion(): string {
	return localCapabilities?.spreed?.version ?? ''
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
export function getTalkConfig(token: string = 'local', key1: keyof Config, key2: string) {
	const remoteCapabilities = getRemoteCapability(token)
	const locals = localCapabilities?.spreed?.config?.[key1]
	if (localCapabilities?.spreed?.['config-local']?.[key1]?.includes(key2)) {
		// @ts-expect-error Vue: Element implicitly has an any type because expression of type string can't be used to index type
		return localCapabilities?.spreed?.config?.[key1]?.[key2]
	} else if (token === 'local' || !remoteCapabilities) {
		// @ts-expect-error Vue: Element implicitly has an any type because expression of type string can't be used to index type
		return localCapabilities?.spreed?.config?.[key1]?.[key2]
	} else {
		// TODO discuss handling remote config (respect remote only / both / minimal)
		// @ts-expect-error Vue: Element implicitly has an any type because expression of type string can't be used to index type
		return remoteCapabilities?.spreed?.config?.[key1]?.[key2]
	}
}

/**
 * Returns capability for specified token (if already matches from one of remote servers)
 * @param token token of the conversation
 */
function getRemoteCapability(token: string): RemoteCapability | null {
	if (remoteTokenMap[token] === undefined) {
		// Unknown conversation, attempt to get remoteServer from cached conversations
		remoteTokenMap = generateTokenMap()
	}

	const remoteServer = remoteTokenMap[token]
	if (!token || token === 'local' || !remoteServer) {
		// Local or no conversation opened
		return null
	}

	return remoteCapabilities[remoteServer] ?? null
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
	BrowserStorage.setItem('remoteCapabilities', JSON.stringify(remoteCapabilities))
	patchTokenMap(joinRoomResponse.data.ocs.data)

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
	const remoteCapabilities = JSON.parse(storageValue) as RemoteCapabilities

	// Migration step for capabilities based on token
	let hasMigrated = false
	const knownRemoteServers = Object.values(remoteTokenMap).filter(Boolean)

	for (const key of Object.keys(remoteCapabilities)) {
		if (knownRemoteServers.includes(key)) {
			continue
		}
		const remoteServer = remoteTokenMap[key]
		if (remoteServer) {
			remoteCapabilities[remoteServer] = remoteCapabilities[key]
		}

		delete remoteCapabilities[key]
		hasMigrated = true
	}
	if (hasMigrated) {
		BrowserStorage.setItem('remoteCapabilities', JSON.stringify(remoteCapabilities))
	}

	return remoteCapabilities
}
