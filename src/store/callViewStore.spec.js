/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import Vuex from 'vuex'

import storeConfig from './storeConfig.js'
import { CONVERSATION } from '../constants.js'

describe('callViewStore', () => {
	let localVue = null
	let store = null

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)

		const testStoreConfig = cloneDeep(storeConfig)

		// remove participant store to avoid participant interaction
		testStoreConfig.modules.participantsStore = {}

		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)

		// to fully reset the state between tests, clear the storage
		localStorage.clear()

		// and reset all mocks
		jest.clearAllMocks()
	})

	describe('raised hand', () => {
		test('get whether participants raised hands with single session id', () => {
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-1',
				raisedHand: { state: true, timestamp: 1 },
			})
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 2 },
			})

			expect(store.getters.getParticipantRaisedHand(['session-id-1']))
				.toStrictEqual({ state: true, timestamp: 1 })

			expect(store.getters.getParticipantRaisedHand(['session-id-2']))
				.toStrictEqual({ state: true, timestamp: 2 })

			expect(store.getters.getParticipantRaisedHand(['session-id-another']))
				.toStrictEqual({ state: false, timestamp: null })
		})

		test('get raised hands after lowering', () => {
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 1 },
			})
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-2',
				raisedHand: { state: false, timestamp: 3 },
			})

			expect(store.getters.getParticipantRaisedHand(['session-id-2']))
				.toStrictEqual({ state: false, timestamp: null })
		})

		test('clears raised hands state after leaving call', () => {
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 1 },
			})
			store.dispatch('leaveCall')

			expect(store.getters.getParticipantRaisedHand(['session-id-2']))
				.toStrictEqual({ state: false, timestamp: null })
		})

		test('get raised hands with multiple session ids only returns first found', () => {
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-2',
				raisedHand: { state: true, timestamp: 1 },
			})
			store.dispatch('setParticipantHandRaised', {
				sessionId: 'session-id-3',
				raisedHand: { state: true, timestamp: 1 },
			})

			expect(store.getters.getParticipantRaisedHand(['session-id-1', 'session-id-2', 'session-id-3']))
				.toStrictEqual({ state: true, timestamp: 1 })
		})
	})

	describe('call view mode and presentation', () => {
		test('restores grid state when joining call (true)', () => {
			localStorage.getItem.mockReturnValueOnce('true')

			store.dispatch('joinCall', { token: 'XXTOKENXX' })

			expect(localStorage.getItem).toHaveBeenCalled()
			expect(localStorage.getItem.mock.calls[0][0]).toEqual(expect.stringMatching(/callprefs-XXTOKENXX-isgrid$/))

			expect(store.getters.isGrid).toBe(true)
			expect(store.getters.isStripeOpen).toBe(true)
		})

		test('restores grid state when joining call (false)', () => {
			localStorage.getItem.mockReturnValueOnce('false')

			store.dispatch('joinCall', { token: 'XXTOKENXX' })

			expect(localStorage.getItem).toHaveBeenCalled()
			expect(localStorage.getItem.mock.calls[0][0]).toEqual(expect.stringMatching(/callprefs-XXTOKENXX-isgrid$/))

			expect(store.getters.isGrid).toBe(false)
			expect(store.getters.isStripeOpen).toBe(true)
		})

		/**
		 * @param {number} conversationType The type of the conversation
		 * @param {boolean} state Whether or not the grid is shown
		 */
		function testDefaultGridState(conversationType, state) {
			localStorage.getItem.mockReturnValueOnce(null)

			// using commit instead of dispatch because the action
			// also processes participants
			store.commit('addConversation', {
				token: 'XXTOKENXX',
				type: conversationType,
			})
			store.dispatch('joinCall', { token: 'XXTOKENXX' })

			expect(localStorage.getItem).toHaveBeenCalled()
			expect(localStorage.getItem.mock.calls[0][0]).toEqual(expect.stringMatching(/callprefs-XXTOKENXX-isgrid$/))

			expect(store.getters.isGrid).toBe(state)
			expect(store.getters.isStripeOpen).toBe(true)
		}

		test('sets default grid state when joining call in group conversation', () => {
			testDefaultGridState(CONVERSATION.TYPE.GROUP, true)
		})

		test('sets default grid state when joining call in public conversation', () => {
			testDefaultGridState(CONVERSATION.TYPE.PUBLIC, true)
		})

		test('sets default grid state when joining call in one to one conversation', () => {
			testDefaultGridState(CONVERSATION.TYPE.ONE_TO_ONE, false)
		})

		test('switching call view mode saves in local storage', () => {
			store.dispatch('updateToken', 'XXTOKENXX')

			store.dispatch('setCallViewMode', {
				isGrid: true,
				isStripeOpen: false,
			})

			expect(store.getters.isGrid).toEqual(true)
			expect(store.getters.isStripeOpen).toEqual(false)

			expect(localStorage.setItem).toHaveBeenCalled()
			expect(localStorage.setItem.mock.calls[0][0]).toEqual(expect.stringMatching(/callprefs-XXTOKENXX-isgrid$/))
			expect(localStorage.setItem.mock.calls[0][1]).toBe(true)

			store.dispatch('setCallViewMode', {
				isGrid: false,
				isStripeOpen: true,
			})

			expect(store.getters.isGrid).toEqual(false)
			expect(store.getters.isStripeOpen).toEqual(true)

			expect(localStorage.setItem).toHaveBeenCalled()
			expect(localStorage.setItem.mock.calls[1][0]).toEqual(expect.stringMatching(/callprefs-XXTOKENXX-isgrid$/))
			expect(localStorage.setItem.mock.calls[1][1]).toBe(false)
		})

		test('start presentation switches off grid view and restores when it ends', () => {
			[{
				isGrid: true,
				isStripeOpen: true,
			}, {
				isGrid: false,
				isStripeOpen: false,
			}].forEach((testState) => {
				store.dispatch('setCallViewMode', testState)

				store.dispatch('startPresentation')

				expect(store.getters.isGrid).toEqual(false)
				expect(store.getters.isStripeOpen).toEqual(false)

				store.dispatch('stopPresentation')

				expect(store.getters.isGrid).toEqual(testState.isGrid)
				expect(store.getters.isStripeOpen).toEqual(testState.isStripeOpen)
			})
		})

		test('switching modes during presentation does not resets it after it ends', () => {
			store.dispatch('setCallViewMode', {
				isGrid: true,
				isStripeOpen: true,
			})

			store.dispatch('startPresentation')

			// switch during presentation
			store.dispatch('setCallViewMode', {
				isGrid: true,
				isStripeOpen: true,
			})

			store.dispatch('stopPresentation')

			// state kept, not restored
			expect(store.getters.isGrid).toEqual(true)
			expect(store.getters.isStripeOpen).toEqual(true)
		})

		test('starting presentation twice does not mess up remembered state', () => {
			store.dispatch('setCallViewMode', {
				isGrid: true,
				isStripeOpen: true,
			})

			expect(store.getters.presentationStarted).toBe(false)

			store.dispatch('startPresentation')

			expect(store.getters.presentationStarted).toBe(true)

			// switch during presentation
			store.dispatch('setCallViewMode', {
				isGrid: true,
				isStripeOpen: true,
			})

			store.dispatch('startPresentation')

			// state kept
			expect(store.getters.isGrid).toEqual(true)
			expect(store.getters.isStripeOpen).toEqual(true)

			expect(store.getters.presentationStarted).toBe(true)

			store.dispatch('stopPresentation')

			expect(store.getters.presentationStarted).toBe(false)

			// state kept, not restored
			expect(store.getters.isGrid).toEqual(true)
			expect(store.getters.isStripeOpen).toEqual(true)
		})

		test('stopping presentation twice does not mess up remembered state', () => {
			store.dispatch('setCallViewMode', {
				isGrid: true,
				isStripeOpen: true,
			})

			expect(store.getters.presentationStarted).toBe(false)

			store.dispatch('startPresentation')

			expect(store.getters.presentationStarted).toBe(true)

			store.dispatch('stopPresentation')

			expect(store.getters.presentationStarted).toBe(false)

			expect(store.getters.isGrid).toEqual(true)
			expect(store.getters.isStripeOpen).toEqual(true)

			store.dispatch('setCallViewMode', {
				isGrid: false,
				isStripeOpen: false,
			})

			store.dispatch('stopPresentation')

			expect(store.getters.presentationStarted).toBe(false)

			// state kept, not reset
			expect(store.getters.isGrid).toEqual(false)
			expect(store.getters.isStripeOpen).toEqual(false)
		})
	})
})
