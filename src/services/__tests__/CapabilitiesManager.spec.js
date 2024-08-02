/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import flushPromises from 'flush-promises'
import { createPinia, setActivePinia } from 'pinia'

import { mockedCapabilities, mockedRemotes } from '../../__mocks__/capabilities.ts'
import { useTalkHashStore } from '../../stores/talkHash.js'
import { generateOCSResponse } from '../../test-helpers.js'
import BrowserStorage from '../BrowserStorage.js'
import {
	hasTalkFeature,
	getTalkConfig,
	setRemoteCapabilities,
} from '../CapabilitiesManager.ts'
import { getRemoteCapabilities } from '../federationService.ts'

const mockedConversations = {
	TOKEN1: { token: 'TOKEN1', remoteServer: undefined },
	TOKEN2: { token: 'TOKEN2', remoteServer: undefined },
	TOKEN3FED1: { token: 'TOKEN3FED1', remoteServer: 'https://nextcloud1.local' },
	TOKEN4FED1: { token: 'TOKEN4FED1', remoteServer: 'https://nextcloud1.local' },
	TOKEN5FED2: { token: 'TOKEN5FED2', remoteServer: 'https://nextcloud2.local' },
	TOKEN6FED2: { token: 'TOKEN6FED2', remoteServer: 'https://nextcloud2.local' },
}

jest.mock('../../composables/useStore', () => ({
	useStore: jest.fn().mockReturnValue({
		getters: {
			conversation: jest.fn(token => mockedConversations[token])
		}
	}),
}))

jest.mock('../BrowserStorage', () => ({
	getItem: jest.fn(key => {
		if (key === 'remoteCapabilities') {
			return JSON.stringify(mockedRemotes)
		} else if (key === 'cachedConversations') {
			return JSON.stringify(mockedConversations)
		}
		return null
	}),
	setItem: jest.fn(),
	removeItem: jest.fn(),
}))

jest.mock('../federationService', () => ({
	getRemoteCapabilities: jest.fn(),
}))

describe('CapabilitiesManager', () => {
	let talkHashStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		talkHashStore = useTalkHashStore()
	})

	afterEach(async () => {
		jest.clearAllMocks()
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
		it('should return true for known remoteServer and unknown token capabilities', async () => {
			expect(hasTalkFeature('TOKEN4FED1', 'ban-v1')).toBeTruthy()
			await flushPromises()
			expect(hasTalkFeature('TOKEN4FED1', 'federation-v1')).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(1)
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
			const responseMock = generateOCSResponse({ payload: [] })
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(0)
		})

		it('should update capabilities from server response and mark talk proxy hash as dirty', async () => {
			const joinRoomResponseMock = generateOCSResponse({
				headers: { 'x-nextcloud-talk-proxy-hash': `${remoteCapabilities.hash}002` },
				payload: { token, remoteServer }
			})
			const responseMock = generateOCSResponse({ payload: mockedCapabilities.spreed })
			getRemoteCapabilities.mockReturnValue(responseMock)
			await setRemoteCapabilities(joinRoomResponseMock)
			expect(talkHashStore.isNextcloudTalkProxyHashDirty[token]).toBeTruthy()
			expect(BrowserStorage.setItem).toHaveBeenCalledTimes(1)
		})
	})
})
