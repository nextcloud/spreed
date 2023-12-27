import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { setActivePinia, createPinia } from 'pinia'
import Vuex from 'vuex'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import Reactions from './Reactions.vue'

import { ATTENDEE } from '../../../../../constants.js'
import storeConfig from '../../../../../store/storeConfig.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'

jest.mock('../../../../../services/messagesService', () => ({
	getReactionsDetails: jest.fn(),
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

	beforeEach(() => {

		setActivePinia(createPinia())
		reactionsStore = useReactionsStore()
		localVue = createLocalVue()
		localVue.use(Vuex)

		testStoreConfig = cloneDeep(storeConfig)
		token = 'token1'
		messageId = 'parent-id'
		messageMock = jest.fn().mockReturnValue({
			actorId: 'admin',
			actorType: 'users',
			id: messageId,
			markdown: true,
			message: 'This is a message to have reactions on',
			reactions: { '🎄': 2, '🔥': 2, '🔒': 2 },
			reactionsSelf: ['🔥'],
			timestamp: 1703668230,
			token
		})
		testStoreConfig.modules.messagesStore.getters.message = () => messageMock

		getActorTypeMock = jest.fn().mockReturnValue(() => ATTENDEE.ACTOR_TYPE.USERS)
		testStoreConfig.modules.actorStore.getters.getActorType = getActorTypeMock

		getActorIdMock = jest.fn().mockReturnValue('admin')
		testStoreConfig.modules.actorStore.getters.getActorId = () => getActorIdMock

		store = new Vuex.Store(testStoreConfig)

		token = 'token1'
		messageId = 'parent-id'
		const reactionsExpected = {
			'🎄': [
				{ actorDisplayName: 'user1', actorId: 'actorId1', actorType: 'users' },
				{ actorDisplayName: 'user2', actorId: 'actorId2', actorType: 'guests' }
			],
			'🔥': [
				{ actorDisplayName: 'user3', actorId: 'actorId3', actorType: 'users' },
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
			reactionsDetails: reactionsExpected
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

	describe('reactions', () => {
		test('shows reaction buttons with count and emoji picker', () => {
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
			expect(reactionButtons).toHaveLength(3) // 3 for reactions
			expect(reactionButtons.at(0).text()).toBe('🎄 2')
			expect(reactionButtons.at(1).text()).toBe('🔥 2')
		})

		test('shows reaction buttons with count but without emoji picker when no chat permission', () => {
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

			// Assert
			const reactionButtons = wrapper.findAllComponents(NcPopover)
			const emojiPicker = wrapper.findAllComponents(NcEmojiPicker)
			expect(emojiPicker).toHaveLength(0)
			expect(reactionButtons).toHaveLength(3) // 2 for reactions
			expect(reactionButtons.at(0).text()).toBe('🎄 2')
			expect(reactionButtons.at(1).text()).toBe('🔥 2')
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
			expect(emojiPicker.vm).toBeUndefined()
		})

		test('dispatches store actions upon picking an emoji from the emojipicker', async () => {
			// Arrange
			const addReactionToMessageAction = jest.fn()
			const removeReactionFromMessageAction = jest.fn()
			testStoreConfig.modules.messagesStore.actions.addReactionToMessage = addReactionToMessageAction
			testStoreConfig.modules.messagesStore.actions.removeReactionFromMessage = removeReactionFromMessageAction
			store = new Vuex.Store(testStoreConfig)

			const wrapper = shallowMount(Reactions, {
				propsData: reactionsProps,
				localVue,
				store,
				stubs: {
					NcEmojiPicker,
				},
			})
			// Act
			const emojiPicker = wrapper.findComponent(NcEmojiPicker)
			emojiPicker.vm.$emit('select', '❤️')
			emojiPicker.vm.$emit('select', '🔥')
			await wrapper.vm.$nextTick()

			// Assert
			expect(addReactionToMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '❤️',
			})
			expect(removeReactionFromMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '🔥',
			})
		})

		test('dispatches store actions upon clicking a reaction buttons', async () => {
			// Arrange
			const addReactionToMessageAction = jest.fn()
			const removeReactionFromMessageAction = jest.fn()
			testStoreConfig.modules.messagesStore.actions.addReactionToMessage = addReactionToMessageAction
			testStoreConfig.modules.messagesStore.actions.removeReactionFromMessage = removeReactionFromMessageAction
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

			// Act
			const reactionButtons = wrapper.findAllComponents(NcButton)
			reactionButtons.at(0).vm.$emit('click') // 🎄
			reactionButtons.at(1).vm.$emit('click') // 🔥

			// Assert
			expect(addReactionToMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '🎄',
			})
			expect(removeReactionFromMessageAction).toHaveBeenCalledWith(expect.anything(), {
				token: reactionsProps.token,
				messageId: reactionsProps.id,
				selectedEmoji: '🔥',
			})
		})
	})
})
