/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { CallParticipantCollection } from './CallParticipantCollection.js'

describe('CallParticipantCollection', () => {
	let callParticipantCollection
	let webRtc

	beforeEach(() => {
		webRtc = {
			on: vi.fn(),
			off: vi.fn(),
		}

		callParticipantCollection = new CallParticipantCollection()
	})

	describe('volume', () => {
		test('volume is restored when the same actor joins again', () => {
			const callParticipantModel1 = callParticipantCollection.add({ peerId: 'peerId1', webRtc })
			callParticipantModel1.setActor('users', 'alice')
			callParticipantModel1.setVolume(0.3)

			callParticipantCollection.remove('peerId1')

			const callParticipantModel2 = callParticipantCollection.add({ peerId: 'peerId2', webRtc })
			expect(callParticipantModel2.get('volume')).toBe(1)

			callParticipantModel2.setActor('users', 'alice')
			expect(callParticipantModel2.get('volume')).toBe(0.3)
		})

		test('volume of other actors is not restored', () => {
			const callParticipantModel1 = callParticipantCollection.add({ peerId: 'peerId1', webRtc })
			callParticipantModel1.setActor('users', 'alice')
			callParticipantModel1.setVolume(0.3)

			const callParticipantModel2 = callParticipantCollection.add({ peerId: 'peerId2', webRtc })
			callParticipantModel2.setActor('users', 'bob')
			expect(callParticipantModel2.get('volume')).toBe(1)

			const callParticipantModel3 = callParticipantCollection.add({ peerId: 'peerId3', webRtc })
			callParticipantModel3.setActor('guests', 'alice')
			expect(callParticipantModel3.get('volume')).toBe(1)
		})

		test('volume reset to the default is not restored', () => {
			const callParticipantModel1 = callParticipantCollection.add({ peerId: 'peerId1', webRtc })
			callParticipantModel1.setActor('users', 'alice')
			callParticipantModel1.setVolume(0.3)
			callParticipantModel1.setVolume(1)

			callParticipantCollection.remove('peerId1')

			const callParticipantModel2 = callParticipantCollection.add({ peerId: 'peerId2', webRtc })
			callParticipantModel2.setActor('users', 'alice')
			expect(callParticipantModel2.get('volume')).toBe(1)
		})

		test('volume set before the actor is known is not stored', () => {
			const callParticipantModel1 = callParticipantCollection.add({ peerId: 'peerId1', webRtc })
			callParticipantModel1.setVolume(0.3)

			expect(callParticipantCollection._volumes.size).toBe(0)
		})

		test('removed models are no longer tracked', () => {
			const callParticipantModel1 = callParticipantCollection.add({ peerId: 'peerId1', webRtc })
			callParticipantModel1.setActor('users', 'alice')

			callParticipantCollection.remove('peerId1')

			callParticipantModel1.setVolume(0.3)
			expect(callParticipantCollection._volumes.size).toBe(0)
		})
	})

	describe('setVolume', () => {
		test('volume is clamped between 0 and 1', () => {
			const callParticipantModel1 = callParticipantCollection.add({ peerId: 'peerId1', webRtc })

			callParticipantModel1.setVolume(1.5)
			expect(callParticipantModel1.get('volume')).toBe(1)

			callParticipantModel1.setVolume(-0.5)
			expect(callParticipantModel1.get('volume')).toBe(0)

			callParticipantModel1.setVolume(0.4)
			expect(callParticipantModel1.get('volume')).toBe(0.4)
		})
	})
})
