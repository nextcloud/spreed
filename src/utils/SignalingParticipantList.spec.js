/*
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import SignalingParticipantList from './SignalingParticipantList.js'

describe('SignalingParticipantList', () => {
	let signaling
	let signalingParticipantList
	let participantsJoinedHandler
	let participantsLeftHandler

	const expectedInternalLocalParticipant = {
		nextcloudSessionId: 'localSignalingSessionId',
		signalingSessionId: 'localSignalingSessionId',
		userId: 'localUserId',
	}
	const expectedInternalUser1 = {
		nextcloudSessionId: 'user1SignalingSessionId',
		signalingSessionId: 'user1SignalingSessionId',
		userId: 'user1UserId',
	}
	const expectedInternalUser2 = {
		nextcloudSessionId: 'user2SignalingSessionId',
		signalingSessionId: 'user2SignalingSessionId',
		userId: 'user2UserId',
	}
	const expectedInternalGuest1 = {
		nextcloudSessionId: 'guest1SignalingSessionId',
		signalingSessionId: 'guest1SignalingSessionId',
	}
	const expectedInternalGuest2 = {
		nextcloudSessionId: 'guest2SignalingSessionId',
		signalingSessionId: 'guest2SignalingSessionId',
	}
	const expectedExternalLocalParticipant = {
		nextcloudSessionId: 'localNextcloudSessionId',
		signalingSessionId: 'localSignalingSessionId',
		userId: 'localUserId',
	}
	const expectedExternalUser1 = {
		nextcloudSessionId: 'user1NextcloudSessionId',
		signalingSessionId: 'user1SignalingSessionId',
		userId: 'user1UserId',
	}
	const expectedExternalUser2 = {
		nextcloudSessionId: 'user2NextcloudSessionId',
		signalingSessionId: 'user2SignalingSessionId',
		userId: 'user2UserId',
	}
	const expectedExternalGuest1 = {
		nextcloudSessionId: 'guest1NextcloudSessionId',
		signalingSessionId: 'guest1SignalingSessionId',
	}
	const expectedExternalGuest2 = {
		nextcloudSessionId: 'guest2NextcloudSessionId',
		signalingSessionId: 'guest2SignalingSessionId',
	}

	beforeEach(() => {
		signaling = new function() {
			this._handlers = {}

			this.on = vi.fn((event, handler) => {
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

			this.off = vi.fn((event, handler) => {
				const handlers = this._handlers[event]
				if (!handlers) {
					return
				}

				const index = handlers.indexOf(handler)
				if (index !== -1) {
					handlers.splice(index, 1)
				}
			})

			this.emit = vi.fn()
		}()

		signalingParticipantList = new SignalingParticipantList()
		signalingParticipantList.setSignaling(signaling)

		participantsJoinedHandler = vi.fn()
		participantsLeftHandler = vi.fn()

		signalingParticipantList.on('participantsJoined', participantsJoinedHandler)
		signalingParticipantList.on('participantsLeft', participantsLeftHandler)
	})

	describe('local participant joins empty room', () => {
		test('with internal signaling', () => {
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(1)
			expect(participantsJoinedHandler).toHaveBeenCalledWith(signalingParticipantList, [
				expectedInternalLocalParticipant,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedInternalLocalParticipant,
			])
		})

		test('with external signaling', () => {
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(1)
			expect(participantsJoinedHandler).toHaveBeenCalledWith(signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedExternalLocalParticipant,
			])
		})
	})

	describe('local participant joins room with other participants', () => {
		test('with internal signaling', () => {
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'guest1SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(1)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalLocalParticipant,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalLocalParticipant,
			])
		})

		test('with external signaling', () => {
			// When there are other participants in the room first an event is
			// triggered with the participants already in the room, and then another event is
			// triggered with the local participant.
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
				{
					roomsessionid: 'guest1NextcloudSessionId',
					sessionid: 'guest1SignalingSessionId',
					userid: '',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(2)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
				expectedExternalGuest1,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedExternalUser1,
				expectedExternalGuest1,
				expectedExternalLocalParticipant,
			])
		})
	})

	describe('local participant switches rooms', () => {
		test('with internal signaling', () => {
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'guest1SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])

			signaling._trigger('leaveRoom', ['theToken'])

			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user2SignalingSessionId',
					userId: 'user2UserId',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(2)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalLocalParticipant,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedInternalUser2,
				expectedInternalLocalParticipant,
			])
			expect(participantsLeftHandler).toHaveBeenCalledTimes(1)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalLocalParticipant,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedInternalUser2,
				expectedInternalLocalParticipant,
			])
		})

		test('with external signaling', () => {
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
				{
					roomsessionid: 'guest1NextcloudSessionId',
					sessionid: 'guest1SignalingSessionId',
					userid: '',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])

			signaling._trigger('leaveRoom', ['theToken'])

			// When a room is left "usersLeft" is emitted (after "leaveRoom")
			// with all the known participants in the room.
			signaling._trigger('usersLeft', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
				{
					roomsessionid: 'guest1NextcloudSessionId',
					sessionid: 'guest1SignalingSessionId',
					userid: '',
				},
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])

			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user2NextcloudSessionId',
					sessionid: 'user2SignalingSessionId',
					userid: 'user2UserId',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(4)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
				expectedExternalGuest1,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(3, signalingParticipantList, [
				expectedExternalUser2,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(4, signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(participantsLeftHandler).toHaveBeenCalledTimes(1)
			expect(participantsLeftHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
				expectedExternalGuest1,
				expectedExternalLocalParticipant,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedExternalUser2,
				expectedExternalLocalParticipant,
			])
		})
	})

	describe('participant joins', () => {
		test('with internal signaling', () => {
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])

			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
				{
					sessionId: 'guest1SignalingSessionId',
					userId: '',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(2)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalLocalParticipant,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedInternalGuest1,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedInternalUser1,
				expectedInternalLocalParticipant,
				expectedInternalGuest1,
			])
		})

		test('with external signaling', () => {
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'guest1NextcloudSessionId',
					sessionid: 'guest1SignalingSessionId',
					userid: '',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(3)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(3, signalingParticipantList, [
				expectedExternalGuest1,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedExternalUser1,
				expectedExternalLocalParticipant,
				expectedExternalGuest1,
			])
		})
	})

	describe('participant leaves', () => {
		test('with internal signaling', () => {
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'guest1SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(1)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalLocalParticipant,
			])
			expect(participantsLeftHandler).toHaveBeenCalledTimes(1)
			expect(participantsLeftHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalGuest1,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedInternalUser1,
				expectedInternalLocalParticipant,
			])
		})

		test('with external signaling', () => {
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
				{
					roomsessionid: 'guest1NextcloudSessionId',
					sessionid: 'guest1SignalingSessionId',
					userid: '',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])
			signaling._trigger('usersLeft', [[
				'guest1SignalingSessionId',
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(2)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
				expectedExternalGuest1,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(participantsLeftHandler).toHaveBeenCalledTimes(1)
			expect(participantsLeftHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalGuest1,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedExternalUser1,
				expectedExternalLocalParticipant,
			])
		})
	})

	describe('participants join and leave', () => {
		test('with internal signaling', () => {
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'guest1SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'guest2SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
				{
					sessionId: 'guest1SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'guest2SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
				{
					sessionId: 'user2SignalingSessionId',
					userId: 'user2UserId',
				},
			]])
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'guest2SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
			]])
			signaling._trigger('usersInRoom', [[
				{
					sessionId: 'guest2SignalingSessionId',
					userId: '',
				},
				{
					sessionId: 'localSignalingSessionId',
					userId: 'localUserId',
				},
				{
					sessionId: 'user1SignalingSessionId',
					userId: 'user1UserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(3)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalGuest2,
				expectedInternalLocalParticipant,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedInternalUser2,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(3, signalingParticipantList, [
				expectedInternalUser1,
			])
			expect(participantsLeftHandler).toHaveBeenCalledTimes(1)
			expect(participantsLeftHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedInternalUser1,
				expectedInternalGuest1,
				expectedInternalUser2,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedInternalGuest2,
				expectedInternalLocalParticipant,
				expectedInternalUser1,
			])
		})

		test('with external signaling', () => {
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
				{
					roomsessionid: 'guest1NextcloudSessionId',
					sessionid: 'guest1SignalingSessionId',
					userid: '',
				},
				{
					roomsessionid: 'guest2NextcloudSessionId',
					sessionid: 'guest2SignalingSessionId',
					userid: '',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'localNextcloudSessionId',
					sessionid: 'localSignalingSessionId',
					userid: 'localUserId',
				},
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user2NextcloudSessionId',
					sessionid: 'user2SignalingSessionId',
					userid: 'user2UserId',
				},
			]])
			signaling._trigger('usersLeft', [[
				'user1SignalingSessionId',
				'guest1SignalingSessionId',
				'user2SignalingSessionId',
			]])
			signaling._trigger('usersJoined', [[
				{
					roomsessionid: 'user1NextcloudSessionId',
					sessionid: 'user1SignalingSessionId',
					userid: 'user1UserId',
				},
			]])

			expect(participantsJoinedHandler).toHaveBeenCalledTimes(4)
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
				expectedExternalGuest1,
				expectedExternalGuest2,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(2, signalingParticipantList, [
				expectedExternalLocalParticipant,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(3, signalingParticipantList, [
				expectedExternalUser2,
			])
			expect(participantsJoinedHandler).toHaveBeenNthCalledWith(4, signalingParticipantList, [
				expectedExternalUser1,
			])
			expect(participantsLeftHandler).toHaveBeenCalledTimes(1)
			expect(participantsLeftHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
				expectedExternalUser1,
				expectedExternalGuest1,
				expectedExternalUser2,
			])
			expect(signalingParticipantList.getParticipants()).toEqual([
				expectedExternalGuest2,
				expectedExternalLocalParticipant,
				expectedExternalUser1,
			])
		})
	})

	describe('destroy', () => {
		describe('prevents updating the list when other participants join', () => {
			test('with internal signaling', () => {
				signaling._trigger('usersInRoom', [[
					{
						sessionId: 'localSignalingSessionId',
						userId: 'localUserId',
					},
				]])

				signalingParticipantList.destroy()

				signaling._trigger('usersInRoom', [[
					{
						sessionId: 'localSignalingSessionId',
						userId: 'localUserId',
					},
					{
						sessionId: 'user1SignalingSessionId',
						userId: 'user1UserId',
					},
					{
						sessionId: 'guest1SignalingSessionId',
						userId: '',
					},
				]])

				expect(participantsJoinedHandler).toHaveBeenCalledTimes(1)
				expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
					expectedInternalLocalParticipant,
				])
				expect(signalingParticipantList.getParticipants()).toEqual([])
			})

			test('with external signaling', () => {
				signaling._trigger('usersJoined', [[
					{
						roomsessionid: 'localNextcloudSessionId',
						sessionid: 'localSignalingSessionId',
						userid: 'localUserId',
					},
				]])

				signalingParticipantList.destroy()

				signaling._trigger('usersJoined', [[
					{
						roomsessionid: 'user1NextcloudSessionId',
						sessionid: 'user1SignalingSessionId',
						userid: 'user1UserId',
					},
					{
						roomsessionid: 'guest1NextcloudSessionId',
						sessionid: 'guest1SignalingSessionId',
						userid: '',
					},
				]])

				expect(participantsJoinedHandler).toHaveBeenCalledTimes(1)
				expect(participantsJoinedHandler).toHaveBeenNthCalledWith(1, signalingParticipantList, [
					expectedExternalLocalParticipant,
				])
				expect(signalingParticipantList.getParticipants()).toEqual([])
			})
		})

		describe('prevents updating the list when other participants leave', () => {
			test('with internal signaling', () => {
				signaling._trigger('usersInRoom', [[
					{
						sessionId: 'localSignalingSessionId',
						userId: 'localUserId',
					},
					{
						sessionId: 'user1SignalingSessionId',
						userId: 'user1UserId',
					},
					{
						sessionId: 'guest1SignalingSessionId',
						userId: '',
					},
				]])

				signalingParticipantList.destroy()

				signaling._trigger('usersInRoom', [[
					{
						sessionId: 'localSignalingSessionId',
						userId: 'localUserId',
					},
				]])

				expect(participantsLeftHandler).toHaveBeenCalledTimes(0)
				expect(signalingParticipantList.getParticipants()).toEqual([])
			})

			test('with external signaling', () => {
				signaling._trigger('usersJoined', [[
					{
						roomsessionid: 'localNextcloudSessionId',
						sessionid: 'localSignalingSessionId',
						userid: 'localUserId',
					},
				]])
				signaling._trigger('usersJoined', [[
					{
						roomsessionid: 'user1NextcloudSessionId',
						sessionid: 'user1SignalingSessionId',
						userid: 'user1UserId',
					},
					{
						roomsessionid: 'guest1NextcloudSessionId',
						sessionid: 'guest1SignalingSessionId',
						userid: '',
					},
				]])

				signalingParticipantList.destroy()

				signaling._trigger('usersLeft', [[
					{
						roomsessionid: 'user1NextcloudSessionId',
						sessionid: 'user1SignalingSessionId',
						userid: 'user1UserId',
					},
					{
						roomsessionid: 'guest1NextcloudSessionId',
						sessionid: 'guest1SignalingSessionId',
						userid: '',
					},
				]])

				expect(participantsLeftHandler).toHaveBeenCalledTimes(0)
				expect(signalingParticipantList.getParticipants()).toEqual([])
			})
		})
	})
})
