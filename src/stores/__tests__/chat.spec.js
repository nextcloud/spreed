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

	describe('check for existence', () => {
		it('returns false if chat blocks are not yet available', () => {
			// Assert
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[109].id })).toBeFalsy()
		})

		it('returns boolean whether message is known by the store', () => {
			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockA)

			// Assert
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[109].id })).toBeTruthy()
			expect(chatStore.hasMessage(TOKEN, { messageId: mockMessages[101].id })).toBeFalsy()
		})
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

	describe('get first and last known messages', () => {
		it('returns given message id if chat blocks are not yet available', () => {
			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: mockMessages[109].id })).toBe(mockMessages[109].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: mockMessages[109].id })).toBe(mockMessages[109].id)
		})

		it('returns first / last known id of first block if no message id was given', () => {
			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN)).toBe(chatBlockA[0].id)
			expect(chatStore.getFirstKnownId(TOKEN)).toBe(chatBlockE[2].id)
		})

		it('returns first / last known id of first block if no message id was given', () => {
			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockC)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockA[0].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockA[1].id)
		})

		it('returns first / last known id of containing block if message id was given', () => {
			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.getLastKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockB[0].id)
			expect(chatStore.getFirstKnownId(TOKEN, { messageId: chatBlockB[0].id })).toBe(chatBlockB[1].id)
		})
	})

	describe('process messages chunks', () => {
		it('creates a new block, if not created yet', () => {
			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockA)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})

		it('extends an existing block, if messages overlap', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)

			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(1)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA, chatBlockE)])
		})

		it('creates a new block, if adjacent status to existing blocks is unknown', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)

			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockB)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(2)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA), outputSet(chatBlockB)])
		})

		it('extends an existing block, if messages are adjacent by options.mergeBy', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockB)

			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockD, { mergeBy: mockMessages[109].id })
			chatStore.processChatBlocks(TOKEN, chatBlockF, { mergeBy: mockMessages[105].id })

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(2)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockD, chatBlockA), outputSet(chatBlockB, chatBlockF)])
		})

		it('merges existing blocks, if resulting sets overlap', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockB)
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(2)

			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockF, { mergeBy: mockMessages[105].id })
			chatStore.processChatBlocks(TOKEN, chatBlockE)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(1)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA, chatBlockE, chatBlockB, chatBlockF)])
		})

		it('retains the correct order of blocks', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockC)

			// Act
			chatStore.processChatBlocks(TOKEN, chatBlockB)

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
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockB)

			// Act
			chatStore.addMessageToChatBlocks(TOKEN, chatBlockD[0])

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockD, chatBlockA), outputSet(chatBlockB)])
		})

		it('does nothing, if message is already present in the most recent block', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)

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
			chatStore.processChatBlocks(TOKEN, chatBlockA)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockD[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})

		it('removes a message id from all blocks', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockA[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet([chatBlockA[1]])])
		})

		it('removes a list of message ids and clears up empty blocks', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockB)

			// Act
			chatStore.removeMessagesFromChatBlocks(TOKEN, chatBlockB.map((message) => message.id))

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toHaveLength(1)
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA)])
		})

		it('clears up store after removing of all blocks', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockB)
			chatStore.processChatBlocks(TOKEN, chatBlockA)

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
			chatStore.processChatBlocks(TOKEN, chatBlockA)
			chatStore.processChatBlocks(TOKEN, chatBlockB)

			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockC[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet(chatBlockA), outputSet(chatBlockB)])
		})

		it('purges a store, if all blocks are behind id to delete', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockB)
			chatStore.processChatBlocks(TOKEN, chatBlockC)

			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockA[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toBeUndefined()
		})

		it('cleans up messages behind id to delete', () => {
			// Arrange
			chatStore.processChatBlocks(TOKEN, chatBlockB)
			chatStore.processChatBlocks(TOKEN, chatBlockC)

			// Act
			chatStore.clearMessagesHistory(TOKEN, chatBlockB[0].id)

			// Assert
			expect(chatStore.chatBlocks[TOKEN]).toEqual([outputSet([chatBlockB[0]])])
		})
	})
})
