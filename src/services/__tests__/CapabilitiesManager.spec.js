/*
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { mockedCapabilities, mockedRemotes } from '../../__mocks__/capabilities.ts'
import { useTalkHashStore } from '../../stores/talkHash.js'
import { generateOCSResponse } from '../../test-helpers.js'
import BrowserStorage from '../BrowserStorage.js'
import {
	getTalkConfig,
	hasTalkFeature,
	setRemoteCapabilities,
	setRemoteCapabilitiesIfEmpty,
} from '../CapabilitiesManager.ts'
import { getRemoteCapabilities } from '../federationService.ts'

vi.mock('../BrowserStorage.js', () => ({
	default: {
		getItem: vi.fn((key) => {
			const mockedConversations = [
				{ token: 'TOKEN1', remoteServer: undefined },
				{ token: 'TOKEN2', remoteServer: undefined },
				{ token: 'TOKEN3FED1', remoteServer: 'https://nextcloud1.local' },
				{ token: 'TOKEN4FED1', remoteServer: 'https://nextcloud1.local' },
				{ token: 'TOKEN5FED2', remoteServer: 'https://nextcloud2.local' },
				{ token: 'TOKEN6FED2', remoteServer: 'https://nextcloud2.local' },
			]

			if (key === 'remoteCapabilities') {
				return JSON.stringify(mockedRemotes)
			} else if (key === 'cachedConversations') {
				return JSON.stringify(mockedConversations)
			}
			return null
		}),
		setItem: vi.fn(),
		removeItem: vi.fn(),
	},
}))

vi.mock('../federationService', () => ({
	getRemoteCapabilities: vi.fn(),
}))

describe('CapabilitiesManager', () => {
	let talkHashStore

	beforeEach(() => {
		setActivePinia(createPinia())
		talkHashStore = useTalkHashStore()
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('hasTalkFeature - local conversation', () => {
		it('should return false if the feature is not in the capabilities', () => {
			expect(hasTalkFeature('TOKEN1', 'never-existed')).toBeFalsy()
		})

		it('should return true if the feature is in the capabilities', () => {
			expect(hasTalkFeature('TOKEN1', 'federation-v1')).toBeTruthy()
		})

		it('should return true if the feature is in the local capabilities', () => {
			expect(hasTalkFeature('local', 'favorites')).toBeTruthy()
		})

		it('should return true if the feature is in the features-local list', () => {
			expect(hasTalkFeature('TOKEN1', 'favorites')).toBeTruthy()
		})
	})

	describe('hasTalkFeature - remote conversation', () => {
		it('should return false if the feature is not in the capabilities', () => {
			expect(hasTalkFeature('TOKEN3FED1', 'never-existed')).toBeFalsy()
		})

		it('should return true if the feature is in the capabilities', () => {
			expect(hasTalkFeature('TOKEN3FED1', 'federation-v1')).toBeTruthy()
		})
	})

	describe('getTalkConfig - local conversation', () => {
		it('should return false if the feature is not in the capabilities', () => {
			expect(getTalkConfig('TOKEN1', 'never', 'existed')).toBeFalsy()
		})

		it('should return true if the feature is in the capabilities', () => {
			expect(getTalkConfig('TOKEN1', 'call', 'enabled')).toBeTruthy()
		})

		it('should return true if the feature is in the local capabilities', () => {
			expect(getTalkConfig('local', 'call', 'enabled')).toBeTruthy()
		})

		it('should return true if the feature is in the features-local list', () => {
			expect(getTalkConfig('TOKEN1', 'attachments', 'allowed')).toBeTruthy()
		})
	})

	describe('getTalkConfig - remote conversation', () => {
		it('should return false if the feature is not in the capabilities', () => {
			expect(getTalkConfig('TOKEN3FED1', 'never', 'existed')).toBeFalsy()
		})

		it('should return true if the feature is in the capabilities', () => {
			expect(getTalkConfig('TOKEN3FED1', 'call', 'enabled')).toBeTruthy()
		})
	})

	describe('getRemoteCapability', () => {
		it('should return true for known remoteServer and unknown token capabilities', () => {
			expect(hasTalkFeature('TOKEN4FED1', 'ban-v1')).toBeTruthy()
		})
		it('should try to regenerate tokenMap for unknown token', () => {
			hasTalkFeature('TOKEN7FED1', 'ban-v1')
			expect(BrowserStorage.getItem).toHaveBeenCalledTimes(1) // retry once
			expect(BrowserStorage.getItem).toHaveBeenCalledWith('cachedConversations')
		})
	})

	describe('setRemoteCapability', () => {
		const [remoteServer, remoteCapabilities] = Object.entries(mockedRemotes)[0]
		const token = remoteCapabilities.tokens[0]

		it('should early return if proxy hash unchanged', async () => {
			const joinRoomResponseMock = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': remoteCapabilities.hash },
				payload: { token, remoteServer },
			})
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeUndefined()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(0)
		})

		it('should early return if no capabilities received from server', async () => {
			const joinRoomResponseMock = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': `${remoteCapabilities.hash}001` },
				payload: { token, remoteServer },
			})
			const responseMock = generateOCSResponse({ payload: {} })
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(0)
		})

		it('should set capabilities for new server and mark talk proxy hash as dirty', async () => {
			const token = 'TOKEN7FED3'
			const remoteServer = 'https://nextcloud3.local'
			const remoteHash = 'abc123'
			const joinRoomResponseMock = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': remoteHash },
				payload: { token, remoteServer },
			})
			const responseMock = generateOCSResponse({ payload: mockedCapabilities.spreed })
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(1)
		})

		it('should update capabilities from server response and mark talk proxy hash as dirty', async () => {
			const joinRoomResponseMock = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': `${remoteCapabilities.hash}002` },
				payload: { token, remoteServer },
			})
			const responseMock = generateOCSResponse({
				payload: {
					...mockedCapabilities.spreed,
					features: [...mockedCapabilities.spreed.features, 'new-feature'],
				},
			})
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(1)
		})

		it('should reset dirty proxy hash after second fetch and negative check for changes', async () => {
			const joinRoomResponseMock = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': `${remoteCapabilities.hash}003` },
				payload: { token, remoteServer },
			})
			const joinRoomResponseMock2 = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': `${remoteCapabilities.hash}004` },
				payload: { token, remoteServer },
			})
			const responseMock = generateOCSResponse({
				payload: {
					...mockedCapabilities.spreed,
					features: [...mockedCapabilities.spreed.features, 'new-feature', 'new-feature-2'],
				},
			})
			const responseMock2 = generateOCSResponse({
				payload: {
					...mockedCapabilities.spreed,
					features: [...mockedCapabilities.spreed.features, 'new-feature', 'new-feature-2'],
				},
			})
			getRemoteCapabilities.mockReturnValueOnce(responseMock).mockReturnValueOnce(responseMock2)
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(1)

			await setRemoteCapabilities(joinRoomResponseMock2)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).not.toBeDefined()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(2)
		})
	})

	describe('setRemoteCapabilitiesIfEmpty', () => {
		const token = 'TOKEN8FED4'
		const remoteServer = 'https://nextcloud4.local'

		it('should early return if already has capabilities', async () => {
			const [remoteServer, remoteCapabilities] = Object.entries(mockedRemotes)[0]
			const token = remoteCapabilities.tokens[0]
			const acceptShareResponseMock = generateOCSResponse({
				payload: { token, remoteServer },
			})
			await setRemoteCapabilitiesIfEmpty(acceptShareResponseMock)
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(0)
		})

		it('should early return if no capabilities received from server', async () => {
			const acceptShareResponseMock = generateOCSResponse({
				payload: { token, remoteServer },
			})
			const responseMock = generateOCSResponse({ payload: {} })
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilitiesIfEmpty(acceptShareResponseMock)
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(0)
		})

		it('should set capabilities for new server', async () => {
			const acceptShareResponseMock = generateOCSResponse({
				payload: { token, remoteServer },
			})
			const responseMock = generateOCSResponse({ payload: mockedCapabilities.spreed })
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilitiesIfEmpty(acceptShareResponseMock)
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(1)
		})
	})
})
