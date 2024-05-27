/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { setActivePinia, createPinia } from 'pinia'
import Vuex from 'vuex'

// import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import Reactions from './Reactions.vue'

import { ATTENDEE } from '../../../../../constants.js'
import {
	addReactionToMessage,
	removeReactionFromMessage,
	getReactionsDetails,
} from '../../../../../services/reactionsService.ts'
import vuexStore from '../../../../../store/index.js'
import storeConfig from '../../../../../store/storeConfig.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'
import { generateOCSResponse } from '../../../../../test-helpers.js'

jest.mock('../../../../../services/reactionsService', () => ({
	getReactionsDetails: jest.fn(),
	addReactionToMessage: jest.fn(),
	removeReactionFromMessage: jest.fn(),
}))

jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
}))

describe('Reactions.vue', () => {
	let reactionsStore
	let token
	let messageId
	let reactionsProps
	let testStoreConfig
	let store
	let localVue
	let messageMock
	let getActorTypeMock
	let getActorIdMock
	let reactionsStored
	let message

	beforeEach(() => {

		setActivePinia(createPinia())
		reactionsStore = useReactionsStore()
		localVue = createLocalVue()
		localVue.use(Vuex)

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
			token
		}
		messageMock = jest.fn().mockReturnValue(message)
		testStoreConfig.modules.messagesStore.getters.message = () => messageMock

		getActorTypeMock = jest.fn().mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
		testStoreConfig.modules.actorStore.getters.getActorType = getActorTypeMock

		getActorIdMock = jest.fn().mockReturnValue('admin')
		testStoreConfig.modules.actorStore.getters.getActorId = () => getActorIdMock

		store = new Vuex.Store(testStoreConfig)

		token = 'token1'
		messageId = 'parent-id'
		reactionsStored = {
			'🎄': [
				{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' },
				{ actorDisplayName: 'user2', actorId: 'actorId2', actorType: 'guests' }
			],
			'🔥': [
				{ actorDisplayName: 'user3', actorId: 'admin', actorType: 'users' },
				{ actorDisplayName: 'user4', actorId: 'actorId4', actorType: 'users' }
			],
			'🔒': [
				{ actorDisplayName: 'user3', actorId: 'actorId3', actorType: 'users' },
				{ actorDisplayName: 'user4', actorId: 'actorId4', actorType: 'users' }
			],
		}

		reactionsStore.updateReactions({
			token,
			messageId,
			reactionsDetails: reactionsStored
		})

		reactionsProps = {
			token,
			canReact: true,
			id: messageId,
		}

	})

	afterEach(() => {
		jest.clearAllMocks()
		reactionsStore.resetReactions(token, messageId)
	})

	describe('reactions buttons', () => {
		test('shows reaction buttons with count and emoji picker', async () => {
			// Arrange
			const wrapper = shallowMount(Reactions, {
				localVue,
				store,
				propsData: reactionsProps,
				stubs: {
					NcPopover,
				},
			})

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
			const wrapper = shallowMount(Reactions, {
				localVue,
				store,
				propsData: reactionsProps,
				stubs: {
					NcPopover,
				},
			})
			const reactionButtons = wrapper.findAllComponents(NcButton)
			const emojiPicker = wrapper.findAllComponents(NcEmojiPicker)
			// Act
			reactionButtons.at(1).vm.$emit('click') // 🎄

			// Assert
			// expect(showError).toHaveBeenCalled()
			expect(emojiPicker).toHaveLength(0)
			expect(reactionButtons).toHaveLength(3) // "🎄" + "🔥" + "🔒" buttons
			expect(reactionButtons.at(0).text()).toBe('🎄 2')
			expect(reactionButtons.at(1).text()).toBe('🔥 2')
			expect(reactionButtons.at(2).text()).toBe('🔒 2')
		})

		test('doesn\'t mount emoji picker when there are no reactions', () => {
			// Arrange
			reactionsStore.resetReactions(token, messageId)
			messageMock = jest.fn().mockReturnValue({
				actorId: 'admin',
				actorType: 'users',
				id: messageId,
				markdown: true,
				message: 'This is a message to have reactions on',
				reactions: {},
				reactionsSelf: [],
				timestamp: 1703668230,
				token
			})
			testStoreConfig.modules.messagesStore.getters.message = () => messageMock
			store = new Vuex.Store(testStoreConfig)
			const wrapper = shallowMount(Reactions, {
				propsData: reactionsProps,
				localVue,
				store,
				stubs: {
					NcEmojiPicker,
					NcPopover,
				},
			})

			// Assert
			const reactionButtons = wrapper.findAllComponents(NcPopover)
			expect(reactionButtons).toHaveLength(0)
			const emojiPicker = wrapper.findComponent(NcEmojiPicker)
			expect(emojiPicker.exists()).toBeFalsy()
		})

		test('dispatches store actions upon picking an emoji from the emojipicker', async () => {
			// Arrange
			jest.spyOn(reactionsStore, 'addReactionToMessage')
			vuexStore.dispatch('processMessage', { token, message })

			const wrapper = shallowMount(Reactions, {
				propsData: {
					...reactionsProps,
					showControls: true,
				},
				localVue,
				store,
				stubs: {
					NcEmojiPicker,
				},
			})

			const response = generateOCSResponse({ payload: Object.assign({}, reactionsStored, { '❤️': [{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' }] }) })
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
			jest.spyOn(reactionsStore, 'addReactionToMessage')
			jest.spyOn(reactionsStore, 'removeReactionFromMessage')

			vuexStore.dispatch('processMessage', { token, message })

			const wrapper = shallowMount(Reactions, {
				propsData: reactionsProps,
				localVue,
				store,
				stubs: {
					NcEmojiPicker,
					NcPopover,
				},
			})
			const addedReaction = {
				...reactionsStored,
				'🎄': [...reactionsStored['🎄'], { actorDisplayName: 'user3', actorId: 'admin', actorType: 'users' }],
			}
			const responseAdded = generateOCSResponse({ payload: addedReaction })
			addReactionToMessage.mockResolvedValue(responseAdded)

			const removedReaction = {
				...reactionsStored,
				'🔥': [...reactionsStored['🔥'].filter(obj => obj.actorId !== 'admin')] // remove the current user
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
			console.debug = jest.fn()
			jest.spyOn(reactionsStore, 'fetchReactions')

			const wrapper = shallowMount(Reactions, {
				propsData: reactionsProps,
				localVue,
				store,
				stubs: {
					NcPopover,
				},
			})
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
