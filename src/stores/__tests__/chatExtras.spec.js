/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import BrowserStorage from '../../services/BrowserStorage.js'
import { EventBus } from '../../services/EventBus.ts'
import { useChatExtrasStore } from '../chatExtras.ts'

describe('chatExtrasStore', () => {
	const token = 'TOKEN'
	let chatExtrasStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		chatExtrasStore = useChatExtrasStore()
	})

	afterEach(async () => {
		vi.clearAllMocks()
	})

	describe('reply message', () => {
		it('adds reply message id to the store', () => {
			// Act
			chatExtrasStore.setParentIdToReply({ token, id: 101 })

			// Assert
			expect(chatExtrasStore.getParentIdToReply(token)).toBe(101)
		})

		it('clears reply message', () => {
			// Arrange
			chatExtrasStore.setParentIdToReply({ token, id: 101 })

			// Act
			chatExtrasStore.removeParentIdToReply(token)

			// Assert
			expect(chatExtrasStore.getParentIdToReply(token)).not.toBeDefined()
		})
	})

	describe('current input message', () => {
		it('sets current input message', () => {
			// Act
			chatExtrasStore.setChatInput({ token: 'token-1', text: 'message-1' })

			// Assert
			expect(chatExtrasStore.getChatInput('token-1')).toStrictEqual('message-1')
			expect(BrowserStorage.getItem('chatInput_token-1')).toBe('message-1')
		})

		it('clears current input message', () => {
			// Arrange
			chatExtrasStore.setChatInput({ token: 'token-1', text: 'message-1' })

			// Act
			chatExtrasStore.removeChatInput('token-1')

			// Assert
			expect(chatExtrasStore.chatInput['token-1']).not.toBeDefined()
			expect(chatExtrasStore.getChatInput('token-1')).toBe('')
			expect(BrowserStorage.getItem('chatInput_token-1')).toBe(null)
		})

		it('restores chat input from the browser storage if any', () => {
			// Arrange
			BrowserStorage.setItem('chatInput_token-1', 'message draft')

			// Act
			chatExtrasStore.restoreChatInput('token-1')

			// Assert
			expect(chatExtrasStore.getChatInput('token-1')).toStrictEqual('message draft')

			// Arrange 2 - no chat input in the browser storage
			chatExtrasStore.removeChatInput('token-1')
			// Act
			chatExtrasStore.restoreChatInput('token-1')
			// Assert
			expect(chatExtrasStore.getChatInput('token-1')).toBe('')
		})
	})

	describe('current edit input message', () => {
		it('sets current edit input message', () => {
			// Act
			chatExtrasStore.setChatEditInput({ token: 'token-1', text: 'This is an edited message' })
			chatExtrasStore.setMessageIdToEdit('token-1', 'id-1')

			// Assert
			expect(chatExtrasStore.getChatEditInput('token-1')).toStrictEqual('This is an edited message')
			expect(chatExtrasStore.getMessageIdToEdit('id-1')).toBe(undefined)
		})

		it('clears current edit input message', () => {
			// Arrange
			chatExtrasStore.setChatEditInput({ token: 'token-1', text: 'This is an edited message' })
			chatExtrasStore.setMessageIdToEdit('token-1', 'id-1')

			// Act
			chatExtrasStore.removeMessageIdToEdit('token-1')

			// Assert
			expect(chatExtrasStore.chatEditInput['token-1']).not.toBeDefined()
			expect(chatExtrasStore.getChatEditInput('token-1')).toBe('')
		})
	})

	describe('purge store', () => {
		it('clears store for provided token', async () => {
			// Arrange
			chatExtrasStore.setParentIdToReply({ token: 'token-1', id: 101 })
			chatExtrasStore.setChatInput({ token: 'token-1', text: 'message-1' })

			// Act
			chatExtrasStore.purgeChatExtras('token-1')

			// Assert
			expect(chatExtrasStore.parentToReply['token-1']).not.toBeDefined()
			expect(chatExtrasStore.chatInput['token-1']).not.toBeDefined()
		})
	})

	describe('text parsing', () => {
		it('should render mentions properly when editing message', () => {
			// Arrange
			const parameters = {
				'mention-call1': { type: 'call', name: 'Conversation101', 'mention-id': 'all' },
				'mention-user1': { type: 'user', name: 'Alice Joel', id: 'alice', 'mention-id': 'alice' },
			}
			// Act
			chatExtrasStore.setChatEditInput({
				token: 'token-1',
				text: 'Hello {mention-call1} and {mention-user1}',
				parameters,
			})
			// Assert
			expect(chatExtrasStore.getChatEditInput('token-1')).toBe('Hello @"all" and @"alice"')
		})

		it('should store chat input without escaping special symbols', () => {
			// Arrange
			const message = 'These are special symbols &amp; &lt; &gt; &sect;'
			// Act
			chatExtrasStore.setChatInput({ token: 'token-1', text: message })
			// Assert
			expect(chatExtrasStore.getChatInput('token-1')).toBe('These are special symbols & < > §')
		})
		it('should remove leading/trailing whitespaces', () => {
			// Arrange
			const message = '   Many whitespaces   '
			// Act
			chatExtrasStore.setChatInput({ token: 'token-1', text: message })
			// Assert
			expect(chatExtrasStore.getChatInput('token-1')).toBe('Many whitespaces')
		})
	})

	describe('initiateEditingMessage', () => {
		it('should set the message ID to edit, set the chat edit input, and emit an event', () => {
			// Arrange
			const payload = {
				token: 'token-1',
				id: 'id-1',
				message: 'Hello, world!',
				messageParameters: {},
			}
			const emitSpy = vi.spyOn(EventBus, 'emit')

			// Act
			chatExtrasStore.initiateEditingMessage(payload)

			// Assert
			expect(chatExtrasStore.getMessageIdToEdit('token-1')).toBe('id-1')
			expect(chatExtrasStore.getChatEditInput('token-1')).toEqual('Hello, world!')
			expect(emitSpy).toHaveBeenCalledWith('editing-message')
		})

		it('should set the chat edit input text to empty if the message is a file share only', () => {
			// Arrange
			const payload = {
				token: 'token-1',
				id: 'id-1',
				message: '{file}',
				messageParameters: { file0: 'file-path' },
			}

			// Act
			chatExtrasStore.initiateEditingMessage(payload)

			// Assert
			expect(chatExtrasStore.getChatEditInput('token-1')).toEqual('')
		})
	})
})
