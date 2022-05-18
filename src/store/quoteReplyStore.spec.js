import Vuex from 'vuex'
import { cloneDeep } from 'lodash'
import { createLocalVue } from '@vue/test-utils'

import quoteReplyStore from './quoteReplyStore.js'

describe('quoteReplyStore', () => {
	let localVue = null
	let store = null

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(cloneDeep(quoteReplyStore))
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('message to be replied to per token', () => {
		test('adds message to be replied to', () => {
			store.dispatch('addMessageToBeReplied', { token: 'token-1', id: 101 })
			store.dispatch('addMessageToBeReplied', { token: 'token-2', id: 201 })

			expect(store.getters.getMessageToBeReplied('token-1'))
				.toStrictEqual({ token: 'token-1', id: 101 })
			expect(store.getters.getMessageToBeReplied('token-2'))
				.toStrictEqual({ token: 'token-2', id: 201 })
		})

		test('override message to be replied to', () => {
			store.dispatch('addMessageToBeReplied', { token: 'token-1', id: 101 })
			store.dispatch('addMessageToBeReplied', { token: 'token-1', id: 201 })

			expect(store.getters.getMessageToBeReplied('token-1'))
				.toStrictEqual({ token: 'token-1', id: 201 })
		})

		test('removes message to be replied to', () => {
			store.dispatch('addMessageToBeReplied', { token: 'token-1', id: 101 })
			store.dispatch('addMessageToBeReplied', { token: 'token-2', id: 201 })

			store.dispatch('removeMessageToBeReplied', 'token-1')

			expect(store.getters.getMessageToBeReplied('token-1'))
				.not.toBeDefined()
			expect(store.getters.getMessageToBeReplied('token-2'))
				.toStrictEqual({ token: 'token-2', id: 201 })
		})
	})

	describe('current input message per token', () => {
		test('set current input message', () => {
			store.dispatch('setCurrentMessageInput', { token: 'token-1', text: 'message-1' })
			store.dispatch('setCurrentMessageInput', { token: 'token-2', text: 'message-2' })

			expect(store.getters.currentMessageInput('token-1'))
				.toStrictEqual('message-1')
			expect(store.getters.currentMessageInput('token-2'))
				.toStrictEqual('message-2')
		})

		test('override current input message', () => {
			store.dispatch('setCurrentMessageInput', { token: 'token-1', text: 'message-1' })
			store.dispatch('setCurrentMessageInput', { token: 'token-1', text: 'message-2' })

			expect(store.getters.currentMessageInput('token-1'))
				.toStrictEqual('message-2')
		})

		test('removes current input message', () => {
			store.dispatch('setCurrentMessageInput', { token: 'token-1', text: 'message-1' })

			store.dispatch('setCurrentMessageInput', { token: 'token-1', text: null })

			expect(store.getters.getMessageToBeReplied('token-1'))
				.not.toBeDefined()
		})
	})
})
