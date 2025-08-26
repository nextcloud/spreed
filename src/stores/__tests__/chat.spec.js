/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { createStore, useStore } from 'vuex'
import storeConfig from '../../store/storeConfig.js'
import { useChatStore } from '../chat.ts'

vi.mock('vuex', async () => {
	const vuex = await vi.importActual('vuex')
	return {
		...vuex,
		useStore: vi.fn(),
	}
})

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
	 *
	 * Threads are as follows:
	 * - 101, 103, 106 are in thread 101
	 * - 104, 107, 109 are in thread 104
	 * - 102, 105, 108, 110 are not in any thread
	 */

	const mockMessages = {
		101: { id: 101, threadId: 101, isThread: true, message: 'Hello' },
		102: { id: 102, threadId: 102, isThread: false, message: 'World' },
		103: { id: 103, threadId: 101, isThread: true, message: '!' },
		104: { id: 104, threadId: 104, isThread: true, message: 'Lorem ipsum' },
		105: { id: 105, threadId: 105, isThread: false, message: 'dolor sit amet' },
		106: { id: 106, threadId: 101, isThread: true, message: 'consectetur adipiscing elit' },
		107: { id: 107, threadId: 104, isThread: true, message: 'Vestibulum quis' },
		108: { id: 108, threadId: 108, isThread: false, message: 'sed diam nonumy' },
		109: { id: 109, threadId: 104, isThread: true, message: 'eirmod tempor invidunt' },
		110: { id: 110, threadId: 110, isThread: false, message: 'ut labore et dolore' },
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

	function processMessages(token, messages, chatBlockOptions = {}) {
		messages.forEach((message) => {
			vuexStore.dispatch('processMessage', { token, message })
		})
		chatStore.processChatBlocks(token, messages, chatBlockOptions)
	}

	beforeEach(() => {
		vuexStore = createStore(storeConfig)
		useStore.mockReturnValue(vuexStore)

		setActivePinia(createPinia())
		chatStore = useChatStore()
	})

	afterEach(() => {
		Object.keys(chatStore.chatBlocks).forEach((key) => {
			delete chatStore.chatBlocks[key]
		})
		vi.clearAllMocks()
	})

	describe('check for existence', () => {
		it('returns false if chat blocks are not yet available', () => {
			// Assert
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[109].id })).toBeFalsy()
		})

		it('returns boolean whether message is known by the store', () => {
			// Act
			processMessages(TOKEN, chatBlockA)

			// Assert
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[109].id })).toBeTruthy()
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[101].id })).toBeFalsy()
		})

		it('returns boolean whether thread message is known by the store', () => {
			// Act
			processMessages(TOKEN, chatBlockA)

			// Assert
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[109].id, threadId: 104 })).toBeTruthy()
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[108].id, threadId: 104 })).toBeFalsy()
		})
	})

	describe('get a list of messages', () => {
		it('returns an array if both messages and blocks present', () => {
			// Arrange
			processMessages(TOKEN, [mockMessages[110], mockMessages[109]])

			// Assert
			expect(chatStore.getMessagesList(TOKEN)).toEqual([mockMessages[109], mockMessages[110]])
		})

		it('returns an array of thread messages only', () => {
			// Arrange
			processMessages(TOKEN, chatBlockC)
			processMessages(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.getMessagesList(TOKEN, { messageId: 106, threadId: 101 })).toEqual([mockMessages[106]])
			expect(chatStore.getMessagesList(TOKEN, { messageId: 101, threadId: 101 })).toEqual([mockMessages[101], mockMessages[103]])
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

	describe('get first and last known messages', () => {
		it('returns given message id if chat blocks are not yet available', () => {
			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: mockMessages[109].id })).toBe(mockMessages[109].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: mockMessages[109].id })).toBe(mockMessages[109].id)
		})

		it('returns thread id of containing block if thread id was given and message is in the store', () => {
			// Act
			processMessages(TOKEN, chatBlockC)
			processMessages(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: chatBlockB[0].id, threadId: 101 })).toBe(chatBlockC[2].id)
		})

		it('returns first / last known id of first block if no message id was given', () => {
			// Act
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN)).toBe(chatBlockA[0].id)
			expect(chatStore.getFirstKnownId(TOKEN)).toBe(chatBlockE[2].id)
		})

		it('returns first / last known id of first block if no message id was given', () => {
			// Act
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockC)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockA[0].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockA[1].id)
		})

		it('returns first / last known id of containing block if message id was given', () => {
			// Act
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockB[0].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockB[1].id)
		})

		it('returns first / last known id of containing thread block if message id was given', () => {
			// Act
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: chatBlockB[0].id, threadId: 101 })).toBe(chatBlockB[0].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: chatBlockB[0].id, threadId: 101 })).toBe(chatBlockB[0].id)
		})
	})

	describe('process messages chunks', () => {
		it('creates a new block, if not created yet', () => {
			// Act
			processMessages(TOKEN, chatBlockA)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})

		it('extends an existing block, if messages overlap', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)

			// Act
			processMessages(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(1)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA, chatBlockE)])
		})

		it('creates a new block, if adjacent status to existing blocks is unknown', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)

			// Act
			processMessages(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(2)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA), outputSet(chatBlockB)])
		})

		it('extends an existing block, if messages are adjacent by options.mergeBy', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)

			// Act
			processMessages(TOKEN, chatBlockD, { mergeBy: mockMessages[109].id })
			processMessages(TOKEN, chatBlockF, { mergeBy: mockMessages[105].id })

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(2)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockD, chatBlockA), outputSet(chatBlockB, chatBlockF)])
		})

		it('merges existing blocks, if resulting sets overlap', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(2)

			// Act
			processMessages(TOKEN, chatBlockF, { mergeBy: mockMessages[105].id })
			processMessages(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(1)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA, chatBlockE, chatBlockB, chatBlockF)])
		})

		it('retains the correct order of blocks', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockC)

			// Act
			processMessages(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(3)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA), outputSet(chatBlockB), outputSet(chatBlockC)])
		})
	})

	describe('add messages', () => {
		it('creates a new block, if not created yet', () => {
			// Act
			chatStore.addMessageToChatBlocks(TOKEN, chatBlockD[0])

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockD)])
		})

		it('extends the most recent block', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)

			// Act
			chatStore.addMessageToChatBlocks(TOKEN, chatBlockD[0])

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockD, chatBlockA), outputSet(chatBlockB)])
		})

		it('does nothing, if message is already present in the most recent block', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)

			// Act
			chatStore.addMessageToChatBlocks(TOKEN, chatBlockA[0])

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})
	})

	describe('remove messages', () => {
		it('does nothing, if no blocks are created yet', () => {
			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockD[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toBeUndefined()
		})

		it('does nothing, if message is not present in existing blocks', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockD[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})

		it('removes a message id from all blocks', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockA[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet([chatBlockA[1]])])
		})

		it('removes a list of message ids and clears up empty blocks', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockB.map((message) => message.id))

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(1)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})

		it('clears up store after removing of all blocks', () => {
			// Arrange
			processMessages(TOKEN, chatBlockB)
			processMessages(TOKEN, chatBlockA)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockB.map((message) => message.id))
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockA.map((message) => message.id))

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toBeUndefined()
		})
	})

	describe('cleanup messages', () => {
		it('does nothing, if no blocks are created yet', () => {
			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockA[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toBeUndefined()
		})

		it('does nothing, if no blocks are behind id to delete', () => {
			// Arrange
			processMessages(TOKEN, chatBlockA)
			processMessages(TOKEN, chatBlockB)

			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockC[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA), outputSet(chatBlockB)])
		})

		it('purges a store, if all blocks are behind id to delete', () => {
			// Arrange
			processMessages(TOKEN, chatBlockB)
			processMessages(TOKEN, chatBlockC)

			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockA[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toBeUndefined()
		})

		it('cleans up messages behind id to delete', () => {
			// Arrange
			processMessages(TOKEN, chatBlockB)
			processMessages(TOKEN, chatBlockC)

			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockB[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet([chatBlockB[0]])])
		})
	})
})
