/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { acceptShareResponse, Capabilities, Conversation, JoinRoomFullResponse } from '../types/index.ts'

import { getCapabilities as _getCapabilities } from '@nextcloud/capabilities'
import BrowserStorage from '../services/BrowserStorage.js'
import { useTalkHashStore } from '../stores/talkHash.js'
import { getRemoteCapabilities } from './federationService.ts'

type Config = Capabilities['spreed']['config']
type RemoteCapability = Capabilities & { hash?: string }
type RemoteCapabilities = Record<string, RemoteCapability>
type TokenMap = Record<string, string | undefined | null>

let remoteTokenMap: TokenMap = generateTokenMap()

export const localCapabilities: Capabilities = _getCapabilities() as Capabilities
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
	if (!cachedConversations?.length) {
		return {}
	}
	cachedConversations.forEach((conversation) => {
		tokenMap[conversation.token] = conversation.remoteServer || null
	})

	return tokenMap
}

/**
 * Patch token map with new / updated remote conversation
 *
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
 *
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
 *
 * @param token conversation token
 * @param key1 top-level key (e.g. 'attachments')
 * @param key2 second-level key (e.g. 'allowed')
 */
export function getTalkConfig<
	T extends keyof Config,
	K extends keyof Config[T],
>(token: string = 'local', key1: T, key2: K): Config[T][K] | undefined {
	const remoteCapabilities = getRemoteCapability(token)
	if (localCapabilities?.spreed?.['config-local']?.[key1]?.includes(String(key2))) {
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
 *
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
 *
 * @param joinRoomResponse server response
 */
export async function setRemoteCapabilities(joinRoomResponse: JoinRoomFullResponse): Promise<void> {
	const talkHashStore = useTalkHashStore()

	const token = joinRoomResponse.data.ocs.data.token
	const remoteServer = joinRoomResponse.data.ocs.data.remoteServer!

	// Check if remote capabilities have not changed since last check
	if (joinRoomResponse.headers['x-nextcloud-talk-proxy-hash'] === remoteCapabilities[remoteServer]?.hash) {
		talkHashStore.resetTalkProxyHashDirty(token)
		return
	}

	// Mark the hash as dirty to prevent any activity in the conversation
	talkHashStore.setTalkProxyHashDirty(token)

	const response = await getRemoteCapabilities(token)
	const newRemoteCapabilities = response.data.ocs.data as Capabilities['spreed']
	if (!Object.keys(newRemoteCapabilities).length) {
		// data: {} received from server, nothing to update with
		return
	}

	const shouldShowWarning = checkRemoteCapabilitiesHasChanged(newRemoteCapabilities, remoteCapabilities[remoteServer]?.spreed)
	remoteCapabilities[remoteServer] = {
		spreed: newRemoteCapabilities,
		hash: joinRoomResponse.headers['x-nextcloud-talk-proxy-hash'],
	}
	BrowserStorage.setItem('remoteCapabilities', JSON.stringify(remoteCapabilities))
	patchTokenMap(joinRoomResponse.data.ocs.data)

	if (shouldShowWarning) {
		// As normal capabilities update, requires a reload to take effect
		talkHashStore.showTalkProxyHashDirtyToast()
	} else {
		talkHashStore.resetTalkProxyHashDirty(token)
	}
}

/**
 * Fetch new capabilities if remote server is not yet known
 *
 * @param acceptShareResponse server response
 */
export async function setRemoteCapabilitiesIfEmpty(acceptShareResponse: Awaited<acceptShareResponse>): Promise<void> {
	const token = acceptShareResponse.data.ocs.data.token
	const remoteServer = acceptShareResponse.data.ocs.data.remoteServer!

	// Check if remote capabilities already exists
	if (remoteCapabilities[remoteServer]) {
		return
	}

	const response = await getRemoteCapabilities(token)
	const newRemoteCapabilities = response.data.ocs.data as Capabilities['spreed']
	if (!Object.keys(newRemoteCapabilities).length) {
		// data: {} received from server, nothing to update with
		return
	}

	remoteCapabilities[remoteServer] = { spreed: newRemoteCapabilities }
	BrowserStorage.setItem('remoteCapabilities', JSON.stringify(remoteCapabilities))
	patchTokenMap(acceptShareResponse.data.ocs.data)
}

/**
 * Deep comparison of remote capabilities, whether there are actual changes that require reload
 *
 * @param newObject new remote capabilities
 * @param oldObject old remote capabilities
 */
function checkRemoteCapabilitiesHasChanged(newObject: Capabilities['spreed'], oldObject: Capabilities['spreed']): boolean {
	if (!newObject || !oldObject) {
		return true
	}

	/**
	 * Returns remote config without local-only properties
	 *
	 * @param object remote capabilities object
	 */
	function getStrippedCapabilities(object: Capabilities['spreed']): { config: Partial<Config>, features: string[] } {
		const config = structuredClone(object.config)

		for (const key1 of Object.keys(object['config-local']) as Array<keyof Config>) {
			const keys2 = object['config-local'][key1]
			for (const key2 of keys2 as Array<keyof Config[keyof Config]>) {
				delete config[key1][key2]
			}
			if (!Object.keys(config[key1]).length) {
				delete config[key1]
			}
		}

		const features = object.features.filter((feature) => !object['features-local'].includes(feature)).sort()

		return { config, features }
	}

	return JSON.stringify(getStrippedCapabilities(newObject)) !== JSON.stringify(getStrippedCapabilities(oldObject))
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
