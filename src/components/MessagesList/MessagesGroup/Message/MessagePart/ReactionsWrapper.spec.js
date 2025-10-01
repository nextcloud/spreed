/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError } from '@nextcloud/dialogs'
import { mount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import { createStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import ReactionsWrapper from './ReactionsWrapper.vue'
import router from '../../../../../__mocks__/router.js'
import {
	addReactionToMessage,
	getReactionsDetails,
	removeReactionFromMessage,
} from '../../../../../services/reactionsService.ts'
import vuexStore from '../../../../../store/index.js'
import storeConfig from '../../../../../store/storeConfig.js'
import { useActorStore } from '../../../../../stores/actor.ts'
import { useReactionsStore } from '../../../../../stores/reactions.js'
import { generateOCSResponse } from '../../../../../test-helpers.js'

vi.mock('../../../../../services/reactionsService', () => ({
	getReactionsDetails: vi.fn(),
	addReactionToMessage: vi.fn(),
	removeReactionFromMessage: vi.fn(),
}))

describe('ReactionsWrapper.vue', () => {
	let reactionsStore
	let token
	let messageId
	let reactionsProps
	let testStoreConfig
	let store
	let messageMock
	let reactionsStored
	let message

	beforeEach(() => {
		setActivePinia(createPinia())
		reactionsStore = useReactionsStore()
		const actorStore = useActorStore()

		testStoreConfig = cloneDeep(storeConfig)
		token = 'token1'
		messageId = 'parent-id'
		message = {
			actorId: 'admin',
			actorType: 'users',
			id: messageId,
			markdown: true,
			message: 'This is a message to have reactions on',
			reactions: { '🎄': 2, '🔥': 2, '🔒': 2 },
			reactionsSelf: ['🔥'],
			timestamp: 1703668230,
			token,
		}
		messageMock = vi.fn().mockReturnValue(message)
		testStoreConfig.modules.messagesStore.getters.message = () => messageMock

		actorStore.setCurrentUser({ uid: 'admin' })

		store = createStore(testStoreConfig)

		token = 'token1'
		messageId = 'parent-id'
		reactionsStored = {
			'🎄': [
				{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' },
				{ actorDisplayName: 'user2', actorId: 'actorId2', actorType: 'guests' },
			],
			'🔥': [
				{ actorDisplayName: 'user3', actorId: 'admin', actorType: 'users' },
				{ actorDisplayName: 'user4', actorId: 'actorId4', actorType: 'users' },
			],
			'🔒': [
				{ actorDisplayName: 'user3', actorId: 'actorId3', actorType: 'users' },
				{ actorDisplayName: 'user4', actorId: 'actorId4', actorType: 'users' },
			],
		}

		reactionsStore.updateReactions({
			token,
			messageId,
			reactionsDetails: reactionsStored,
		})

		reactionsProps = {
			token,
			canReact: true,
			id: messageId,
		}
	})

	afterEach(() => {
		vi.clearAllMocks()
		reactionsStore.resetReactions(token, messageId)
	})

	const ComponentTriggerStub = {
		template: '<div><slot name="trigger" /></div>',
	}

	/**
	 * Shared function to mount component
	 */
	function mountReactions(props) {
		return mount(ReactionsWrapper, {
			global: {
				plugins: [router, store],
				stubs: {
					NcEmojiPicker: ComponentTriggerStub,
					NcPopover: ComponentTriggerStub,
				},
			},
			props,
		})
	}

	describe('reactions buttons', () => {
		test('shows reaction buttons with count and emoji picker', async () => {
			// Arrange
			const wrapper = mountReactions(reactionsProps)
			// Assert
			const reactionButtons = wrapper.findAllComponents(NcPopover)
			expect(reactionButtons).toHaveLength(3)
			expect(reactionButtons.at(0).text()).toBe('🎄 2')
			expect(reactionButtons.at(1).text()).toBe('🔥 2')

			// Assert dropdown contains "You" when you have reacted
			const summary = wrapper.vm.getReactionSummary('🔥')
			expect(summary).toContain('You')
		})

		test('shows reaction buttons with count but without emoji picker when no react permission', () => {
			// Arrange
			reactionsProps.canReact = false
			const wrapper = mountReactions(reactionsProps)
			const reactionButtons = wrapper.findAllComponents(NcButton)
			const emojiPicker = wrapper.findAllComponents(NcEmojiPicker)
			// Act
			reactionButtons.at(1).vm.$emit('click') // 🎄

			// Assert
			expect(showError).toHaveBeenCalled()
			expect(emojiPicker).toHaveLength(0)
			expect(reactionButtons).toHaveLength(3) // "🎄" + "🔥" + "🔒" buttons
			expect(reactionButtons.at(0).text()).toBe('🎄 2')
			expect(reactionButtons.at(1).text()).toBe('🔥 2')
			expect(reactionButtons.at(2).text()).toBe('🔒 2')
		})

		test('doesn\'t mount emoji picker when there are no reactions', () => {
			// Arrange
			reactionsStore.resetReactions(token, messageId)
			messageMock = vi.fn().mockReturnValue({
				actorId: 'admin',
				actorType: 'users',
				id: messageId,
				markdown: true,
				message: 'This is a message to have reactions on',
				reactions: {},
				reactionsSelf: [],
				timestamp: 1703668230,
				token,
			})
			testStoreConfig.modules.messagesStore.getters.message = () => messageMock
			store = createStore(testStoreConfig)
			const wrapper = mountReactions(reactionsProps)

			// Assert
			const reactionButtons = wrapper.findAllComponents(NcPopover)
			expect(reactionButtons).toHaveLength(0)
			const emojiPicker = wrapper.findComponent(NcEmojiPicker)
			expect(emojiPicker.exists()).toBeFalsy()
		})

		test('dispatches store actions upon picking an emoji from the emojipicker', async () => {
			// Arrange
			vi.spyOn(reactionsStore, 'addReactionToMessage')
			vuexStore.dispatch('processMessage', { token, message })

			reactionsProps.showControls = true
			const wrapper = mountReactions(reactionsProps)

			const response = generateOCSResponse({ payload: { ...reactionsStored, '❤️': [{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' }] } })
			addReactionToMessage.mockResolvedValue(response)
			// Act
			const emojiPicker = wrapper.findComponent(NcEmojiPicker)
			emojiPicker.vm.$emit('select', '❤️')
			await wrapper.vm.$nextTick()

			// Assert
			expect(reactionsStore.addReactionToMessage).toHaveBeenCalledWith({
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '❤️',
			})
		})

		test('dispatches store actions upon clicking a reaction buttons', async () => {
			// Arrange
			vi.spyOn(reactionsStore, 'addReactionToMessage')
			vi.spyOn(reactionsStore, 'removeReactionFromMessage')

			vuexStore.dispatch('processMessage', { token, message })

			const wrapper = mountReactions(reactionsProps)

			const addedReaction = {
				...reactionsStored,
				'🎄': [...reactionsStored['🎄'], { actorDisplayName: 'user3', actorId: 'admin', actorType: 'users' }],
			}
			const responseAdded = generateOCSResponse({ payload: addedReaction })
			addReactionToMessage.mockResolvedValue(responseAdded)

			const removedReaction = {
				...reactionsStored,
				'🔥': [...reactionsStored['🔥'].filter((obj) => obj.actorId !== 'admin')], // remove the current user
			}
			const responseRemoved = generateOCSResponse({ payload: removedReaction })
			removeReactionFromMessage.mockResolvedValue(responseRemoved)

			// Act
			const reactionButtons = wrapper.findAllComponents(NcButton)
			reactionButtons.at(0).vm.$emit('click') // 🎄
			reactionButtons.at(1).vm.$emit('click') // 🔥

			// Assert
			expect(reactionsStore.addReactionToMessage).toHaveBeenCalledWith({
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '🎄',
			})
			expect(reactionsStore.removeReactionFromMessage).toHaveBeenCalledWith({
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '🔥',
			})
		})
	})
	describe('reactions fetching', () => {
		test('fetches reactions details when they are not available', async () => {
			// Arrange
			reactionsStore.resetReactions(token, messageId)
			console.debug = vi.fn()
			vi.spyOn(reactionsStore, 'fetchReactions')

			const wrapper = mountReactions(reactionsProps)

			const response = generateOCSResponse({ payload: reactionsStored })
			getReactionsDetails.mockResolvedValue(response)

			// Assert
			const reactionButtons = wrapper.findAllComponents(NcPopover)
			expect(reactionButtons).toHaveLength(3)

			// Act
			reactionButtons.at(0).vm.$emit('after-show')
			await wrapper.vm.$nextTick()

			// Assert
			expect(reactionsStore.fetchReactions).toHaveBeenCalled()
		})
	})
})
