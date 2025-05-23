/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { cloneDeep } from 'lodash'
import Vuex from 'vuex'
import storeConfig from '../store/storeConfig.js'
import SignalingTypingHandler from './SignalingTypingHandler.js'

describe('SignalingTypingHandler', () => {
	let store

	let signaling
	let signalingTypingHandler

	const addLocalParticipantInTheTokenData = {
		token: 'theToken',
		participant: {
			sessionIds: ['localNextcloudSessionId'],
			attendeeId: 'localAttendeeId',
		},
	}
	const addUser1ParticipantInTheTokenData = {
		token: 'theToken',
		participant: {
			sessionIds: ['user1NextcloudSessionId'],
			attendeeId: 'user1AttendeeId',
		},
	}
	const addGuest1ParticipantInTheTokenData = {
		token: 'theToken',
		participant: {
			sessionIds: ['guest1NextcloudSessionId'],
			attendeeId: 'guest1AttendeeId',
		},
	}
	const addGuest2ParticipantInTheTokenData = {
		token: 'theToken',
		participant: {
			sessionIds: ['guest2NextcloudSessionId'],
			attendeeId: 'guest2AttendeeId',
		},
	}

	const localParticipantInSignalingParticipantList = {
		nextcloudSessionId: 'localNextcloudSessionId',
		signalingSessionId: 'localSignalingSessionId',
		userId: 'localUserId',
	}
	const user1ParticipantInSignalingParticipantList = {
		nextcloudSessionId: 'user1NextcloudSessionId',
		signalingSessionId: 'user1SignalingSessionId',
		userId: 'user1UserId',
	}
	const guest1ParticipantInSignalingParticipantList = {
		nextcloudSessionId: 'guest1NextcloudSessionId',
		signalingSessionId: 'guest1SignalingSessionId',
	}
	const guest2ParticipantInSignalingParticipantList = {
		nextcloudSessionId: 'guest2NextcloudSessionId',
		signalingSessionId: 'guest2SignalingSessionId',
	}

	const expectedUser1Participant = {
		sessionIds: ['user1NextcloudSessionId'],
		attendeeId: 'user1AttendeeId',
	}
	const expectedGuest2Participant = {
		sessionIds: ['guest2NextcloudSessionId'],
		attendeeId: 'guest2AttendeeId',
	}

	beforeEach(() => {
		const testStoreConfig = cloneDeep(storeConfig)
		store = new Vuex.Store(testStoreConfig)

		signaling = new function() {
			this._handlers = {}

			this.on = jest.fn((event, handler) => {
				if (!Object.prototype.hasOwnProperty.call(this._handlers, event)) {
					this._handlers[event] = [handler]
				} else {
					this._handlers[event].push(handler)
				}
			})

			this._trigger = (event, args) => {
				const handlers = this._handlers[event]

				if (handlers) {
					for (let i = 0; i < handlers.length; i++) {
						const handler = handlers[i]
						handler.apply(handler, args)
					}
				}
			}

			this.off = jest.fn((event, handler) => {
				const handlers = this._handlers[event]
				if (!handlers) {
					return
				}

				const index = handlers.indexOf(handler)
				if (index !== -1) {
					handlers.splice(index, 1)
				}
			})

			this.emit = jest.fn()
		}()

		signalingTypingHandler = new SignalingTypingHandler(store)
		signalingTypingHandler._signalingParticipantList.getParticipants = jest.fn()

		store.dispatch('setCurrentParticipant', {
			sessionId: 'localNextcloudSessionId',
			attendeeId: 'localAttendeeId',
		})
	})

	describe('start typing', () => {
		test('when there are no other participants in the room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.setTyping(true)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})

		test('when there is another participant in the room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
				user1ParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.setTyping(true)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(1)
			expect(signaling.emit).toHaveBeenCalledWith('message', { type: 'startedTyping', to: 'user1SignalingSessionId' })
		})

		test('when there are other participants in the room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				user1ParticipantInSignalingParticipantList,
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.setTyping(true)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(2)
			expect(signaling.emit).toHaveBeenNthCalledWith(1, 'message', { type: 'startedTyping', to: 'user1SignalingSessionId' })
			expect(signaling.emit).toHaveBeenNthCalledWith(2, 'message', { type: 'startedTyping', to: 'guest1SignalingSessionId' })
		})

		test('when signaling is not set yet', () => {
			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)

			signalingTypingHandler.setTyping(true)

			signalingTypingHandler.setSignaling(signaling)

			// Typing is not set once finally joined the room.
			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})

		test('when room is not joined yet', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)

			signalingTypingHandler.setTyping(true)

			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			// Typing is not set once finally joined the room.
			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})
	})

	describe('stop typing', () => {
		test('when there are no other participants in the room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.setTyping(true)
			signalingTypingHandler.setTyping(false)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})

		test('when there is another participant in the room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
				user1ParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.setTyping(true)
			signalingTypingHandler.setTyping(false)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(2)
			expect(signaling.emit).toHaveBeenNthCalledWith(1, 'message', { type: 'startedTyping', to: 'user1SignalingSessionId' })
			expect(signaling.emit).toHaveBeenNthCalledWith(2, 'message', { type: 'stoppedTyping', to: 'user1SignalingSessionId' })
		})

		test('when there are other participants in the room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				user1ParticipantInSignalingParticipantList,
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.setTyping(true)
			signalingTypingHandler.setTyping(false)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(4)
			expect(signaling.emit).toHaveBeenNthCalledWith(1, 'message', { type: 'startedTyping', to: 'user1SignalingSessionId' })
			expect(signaling.emit).toHaveBeenNthCalledWith(2, 'message', { type: 'startedTyping', to: 'guest1SignalingSessionId' })
			expect(signaling.emit).toHaveBeenNthCalledWith(3, 'message', { type: 'stoppedTyping', to: 'user1SignalingSessionId' })
			expect(signaling.emit).toHaveBeenNthCalledWith(4, 'message', { type: 'stoppedTyping', to: 'guest1SignalingSessionId' })
		})

		test('when signaling is not set yet', () => {
			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)

			signalingTypingHandler.setTyping(true)
			signalingTypingHandler.setTyping(false)

			signalingTypingHandler.setSignaling(signaling)

			// Typing is not set once finally joined the room.
			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})

		test('when room is not joined yet', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)

			signalingTypingHandler.setTyping(true)
			signalingTypingHandler.setTyping(false)

			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			// Typing is not set once finally joined the room.
			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})
	})

	describe('other participants start typing', () => {
		test('in the current room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				user1ParticipantInSignalingParticipantList,
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signaling._trigger('message', [{
				type: 'startedTyping',
				from: 'user1SignalingSessionId',
			}])

			expect(store.getters.participantsListTyping('theToken')).toEqual([
				expectedUser1Participant,
			])
		})

		test('in another room', () => {
			// The other clients should not send the signaling message to the
			// participants that are not in their room. However, it is
			// technically possible to send them, so this verifies that it does
			// not misbehave.
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signaling._trigger('message', [{
				type: 'startedTyping',
				from: 'user1SignalingSessionId',
			}])

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
		})
	})

	describe('other participants stop typing', () => {
		test('in the current room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				user1ParticipantInSignalingParticipantList,
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signaling._trigger('message', [{
				type: 'startedTyping',
				from: 'user1SignalingSessionId',
			}])
			signaling._trigger('message', [{
				type: 'stoppedTyping',
				from: 'user1SignalingSessionId',
			}])

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
		})

		test('in another room', () => {
			// The other clients should not send the signaling message to the
			// participants that are not in their room. However, it is
			// technically possible to send them, so this verifies that it does
			// not misbehave.
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signaling._trigger('message', [{
				type: 'startedTyping',
				from: 'user1SignalingSessionId',
			}])
			signaling._trigger('message', [{
				type: 'stoppedTyping',
				from: 'user1SignalingSessionId',
			}])

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
		})
	})

	test('current participant leaves when typing', () => {
		signalingTypingHandler.setSignaling(signaling)

		store.dispatch('updateToken', 'theToken')
		store.dispatch('updateLastJoinedConversationToken', 'theToken')

		store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
		store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
			localParticipantInSignalingParticipantList,
			user1ParticipantInSignalingParticipantList,
		])

		signalingTypingHandler.setTyping(true)

		signalingTypingHandler._signalingParticipantList._trigger('participantsLeft', [[
			localParticipantInSignalingParticipantList,
		]])

		expect(store.getters.participantsListTyping('theToken')).toEqual([])
		expect(signaling.emit).toHaveBeenCalledTimes(1)
		expect(signaling.emit).toHaveBeenCalledWith('message', { type: 'startedTyping', to: 'user1SignalingSessionId' })
	})

	test('other participants join when current participant is typing', () => {
		signalingTypingHandler.setSignaling(signaling)

		store.dispatch('updateToken', 'theToken')
		store.dispatch('updateLastJoinedConversationToken', 'theToken')

		store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
		store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
			localParticipantInSignalingParticipantList,
			guest1ParticipantInSignalingParticipantList,
		])

		signalingTypingHandler.setTyping(true)

		store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList._trigger('participantsJoined', [[
			user1ParticipantInSignalingParticipantList,
		]])

		expect(store.getters.participantsListTyping('theToken')).toEqual([])
		expect(signaling.emit).toHaveBeenCalledTimes(2)
		expect(signaling.emit).toHaveBeenNthCalledWith(1, 'message', { type: 'startedTyping', to: 'guest1SignalingSessionId' })
		expect(signaling.emit).toHaveBeenNthCalledWith(2, 'message', { type: 'startedTyping', to: 'user1SignalingSessionId' })
	})

	test('other participants join when current participant is no longer typing', () => {
		signalingTypingHandler.setSignaling(signaling)

		store.dispatch('updateToken', 'theToken')
		store.dispatch('updateLastJoinedConversationToken', 'theToken')

		store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
		store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
			localParticipantInSignalingParticipantList,
			guest1ParticipantInSignalingParticipantList,
		])

		signalingTypingHandler.setTyping(true)
		signalingTypingHandler.setTyping(false)

		store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList._trigger('participantsJoined', [[
			user1ParticipantInSignalingParticipantList,
		]])

		expect(store.getters.participantsListTyping('theToken')).toEqual([])
		expect(signaling.emit).toHaveBeenCalledTimes(2)
		expect(signaling.emit).toHaveBeenNthCalledWith(1, 'message', { type: 'startedTyping', to: 'guest1SignalingSessionId' })
		expect(signaling.emit).toHaveBeenNthCalledWith(2, 'message', { type: 'stoppedTyping', to: 'guest1SignalingSessionId' })
	})

	test('other participants leave when they were typing', () => {
		signalingTypingHandler.setSignaling(signaling)

		store.dispatch('updateToken', 'theToken')
		store.dispatch('updateLastJoinedConversationToken', 'theToken')

		store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
		store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
		store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)
		store.dispatch('addParticipant', addGuest2ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
			user1ParticipantInSignalingParticipantList,
			localParticipantInSignalingParticipantList,
			guest1ParticipantInSignalingParticipantList,
			guest2ParticipantInSignalingParticipantList,
		])

		signaling._trigger('message', [{
			type: 'startedTyping',
			from: 'user1SignalingSessionId',
		}])
		signaling._trigger('message', [{
			type: 'startedTyping',
			from: 'guest2SignalingSessionId',
		}])

		signalingTypingHandler._signalingParticipantList._trigger('participantsLeft', [[
			user1ParticipantInSignalingParticipantList,
			guest1ParticipantInSignalingParticipantList,
		]])

		expect(store.getters.participantsListTyping('theToken')).toEqual([
			expectedGuest2Participant,
		])
	})

	test('other participants leave when they were not typing', () => {
		signalingTypingHandler.setSignaling(signaling)

		store.dispatch('updateToken', 'theToken')
		store.dispatch('updateLastJoinedConversationToken', 'theToken')

		store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
		store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
		store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)
		store.dispatch('addParticipant', addGuest2ParticipantInTheTokenData)

		signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
			user1ParticipantInSignalingParticipantList,
			localParticipantInSignalingParticipantList,
			guest1ParticipantInSignalingParticipantList,
			guest2ParticipantInSignalingParticipantList,
		])

		signaling._trigger('message', [{
			type: 'startedTyping',
			from: 'user1SignalingSessionId',
		}])
		signaling._trigger('message', [{
			type: 'startedTyping',
			from: 'guest2SignalingSessionId',
		}])
		signaling._trigger('message', [{
			type: 'stoppedTyping',
			from: 'user1SignalingSessionId',
		}])

		signalingTypingHandler._signalingParticipantList._trigger('participantsLeft', [[
			user1ParticipantInSignalingParticipantList,
			guest1ParticipantInSignalingParticipantList,
		]])

		expect(store.getters.participantsListTyping('theToken')).toEqual([
			expectedGuest2Participant,
		])
	})

	describe('destroy', () => {
		test('prevents start typing in the current room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				localParticipantInSignalingParticipantList,
				user1ParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.destroy()

			signalingTypingHandler.setTyping(true)

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
			expect(signaling.emit).toHaveBeenCalledTimes(0)
		})

		test('prevents other participants start typing in the current room', () => {
			signalingTypingHandler.setSignaling(signaling)

			store.dispatch('updateToken', 'theToken')
			store.dispatch('updateLastJoinedConversationToken', 'theToken')

			store.dispatch('addParticipant', addUser1ParticipantInTheTokenData)
			store.dispatch('addParticipant', addLocalParticipantInTheTokenData)
			store.dispatch('addParticipant', addGuest1ParticipantInTheTokenData)

			signalingTypingHandler._signalingParticipantList.getParticipants.mockReturnValue([
				user1ParticipantInSignalingParticipantList,
				localParticipantInSignalingParticipantList,
				guest1ParticipantInSignalingParticipantList,
			])

			signalingTypingHandler.destroy()

			signaling._trigger('message', [{
				type: 'startedTyping',
				from: 'user1SignalingSessionId',
			}])

			expect(store.getters.participantsListTyping('theToken')).toEqual([])
		})
	})
})
