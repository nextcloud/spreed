/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import Signaling from './signaling.js'

/**
 * Minimal replacement for WebSocket that records the messages sent through it.
 */
class FakeWebSocket {
	constructor(url) {
		this.url = url
		this.readyState = FakeWebSocket.CONNECTING
		this.sent = []
		FakeWebSocket.instances.push(this)
	}

	send(data) {
		this.sent.push(JSON.parse(data))
	}

	close() {
		this.readyState = FakeWebSocket.CLOSED
	}

	open() {
		this.readyState = FakeWebSocket.OPEN
		this.onopen({})
	}

	receive(message) {
		this.onmessage({ data: JSON.stringify(message) })
	}

	sentOfType(type) {
		return this.sent.filter((message) => message.type === type)
	}
}
FakeWebSocket.CONNECTING = 0
FakeWebSocket.OPEN = 1
FakeWebSocket.CLOSING = 2
FakeWebSocket.CLOSED = 3

const HELLO_FEATURES = ['mcu', 'audio-video-permissions', 'incall-all', 'switchto', 'federation']

describe('Signaling.Standalone', () => {
	let signaling
	let socket

	beforeEach(() => {
		vi.useFakeTimers()
		FakeWebSocket.instances = []
		vi.stubGlobal('WebSocket', FakeWebSocket)

		signaling = new Signaling.Standalone({
			server: 'https://signaling.example.com',
			helloAuthParams: {
				'1.0': {
					userid: 'user',
					ticket: 'ticket',
				},
			},
			stunservers: [],
			turnservers: [],
		}, 'https://signaling.example.com')

		socket = FakeWebSocket.instances.at(-1)
		socket.open()
		const hello = socket.sentOfType('hello')[0]
		socket.receive({
			id: hello.id,
			type: 'hello',
			hello: {
				sessionid: 'signaling-session',
				resumeid: 'resume-id',
				server: { features: HELLO_FEATURES },
			},
		})
	})

	afterEach(() => {
		signaling.disconnect()
		vi.clearAllTimers()
		vi.useRealTimers()
		vi.unstubAllGlobals()
		vi.restoreAllMocks()
	})

	describe('joining a room', () => {
		test('treats the room as joined when the join is answered', () => {
			signaling.joinRoom('room-token', 'nextcloud-session')
			const join = socket.sentOfType('room')[0]
			expect(join.room).toEqual({ roomid: 'room-token', sessionid: 'nextcloud-session' })

			socket.receive({ id: join.id, type: 'room', room: { roomid: 'room-token' } })

			expect(signaling.signalingRoomJoined).toBe('room-token')
		})

		test('joins the room again when joining failed', () => {
			const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
			signaling.joinRoom('room-token', 'nextcloud-session')
			const join = socket.sentOfType('room')[0]

			socket.receive({
				id: join.id,
				type: 'error',
				error: { code: 'room_join_failed', message: 'Failed to join the room.' },
			})

			expect(signaling.signalingRoomJoined).not.toBe('room-token')
			expect(consoleError).toHaveBeenCalledWith('Joining the room failed: room_join_failed (Failed to join the room.)')
			vi.advanceTimersByTime(1000)
			expect(socket.sentOfType('room')).toHaveLength(2)
		})

		test('treats the room as joined when the session has joined it already', () => {
			signaling.joinRoom('room-token', 'nextcloud-session')
			const join = socket.sentOfType('room')[0]

			socket.receive({
				id: join.id,
				type: 'error',
				error: { code: 'already_joined', message: 'Already joined this room.' },
			})

			expect(signaling.signalingRoomJoined).toBe('room-token')
			vi.advanceTimersByTime(10000)
			expect(socket.sentOfType('room')).toHaveLength(1)
		})
	})
})
