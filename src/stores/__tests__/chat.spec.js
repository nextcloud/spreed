/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { createStore } from 'vuex'
import storeConfig from '../../store/storeConfig.js'
import { useChatStore } from '../chat.ts'

describe('chatStore', () => {
	const TOKEN = 'XXTOKENXX'
	let chatStore
	let vuexStore

	/*
	 * Resulting blocks should be sorted by the max message id, so recent messages are always in the first block.
	 * |       | A         |               | B         |       | C             |
	 * |-------|-----------|---------------|-----------|-------|---------------|
	 * |       | [109,108] |               | [106,105] |       | [103,102,101] |
	 * | D     |           | E             |           | F     |               |
	 * | [110] |           | [108,107,106] |           | [104] |               |
	 */

	const mockMessages = {
		101: { id: 101, message: 'Hello' },
		102: { id: 102, message: 'World' },
		103: { id: 103, message: '!' },
		104: { id: 104, message: 'Lorem ipsum' },
		105: { id: 105, message: 'dolor sit amet' },
		106: { id: 106, message: 'consectetur adipiscing elit' },
		107: { id: 107, message: 'Vestibulum quis' },
		108: { id: 108, message: 'sed diam nonumy' },
		109: { id: 109, message: 'eirmod tempor invidunt' },
		110: { id: 110, message: 'ut labore et dolore' },
	}

	const chatBlockA = [mockMessages[109], mockMessages[108]]
	const chatBlockB = [mockMessages[106], mockMessages[105]]
	const chatBlockC = [mockMessages[103], mockMessages[102], mockMessages[101]]
	const chatBlockD = [mockMessages[110]]
	const chatBlockE = [mockMessages[108], mockMessages[107], mockMessages[106]]
	const chatBlockF = [mockMessages[104]]

	function outputSet(messages, ...rest) {
		return new Set([...messages, ...rest.flat()].map((message) => message.id))
	}

	beforeEach(() => {
		vuexStore = createStore(storeConfig)
		jest.spyOn(require('vuex'), 'useStore').mockReturnValue(vuexStore)

		setActivePinia(createPinia())
		chatStore = useChatStore()
	})

	afterEach(() => {
		Object.keys(chatStore.chatBlocks).forEach((key) => {
			delete chatStore.chatBlocks[key]
		})
		jest.clearAllMocks()
	})

	describe('get a list of messages', () => {
		it('returns an array if both messages and blocks present', () => {
			// Arrange
			vuexStore.dispatch('processMessage', { token: TOKEN, message: mockMessages[110] })
			vuexStore.dispatch('processMessage', { token: TOKEN, message: mockMessages[109] })
			chatStore.processChatBlocks(TOKEN, [mockMessages[110], mockMessages[109]])

			// Assert
			expect(chatStore.getMessagesList(TOKEN)).toEqual([mockMessages[109], mockMessages[110]])
		})

		it('returns an empty array if no messages or blocks present', () => {
			// Arrange
			vuexStore.dispatch('processMessage', { token: 'token1', message: mockMessages[109] })
			chatStore.processChatBlocks('token2', [mockMessages[110]])

			// Assert
			expect(chatStore.getMessagesList('token1')).toEqual([]) // No chat blocks
			expect(chatStore.getMessagesList('token2')).toEqual([]) // No messages in store
			expect(chatStore.getMessagesList('token3')).toEqual([]) // Neither messages nor blocks
		})
	})
})
