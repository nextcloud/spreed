/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	CallParticipantModel as CallParticipantModelType,
	WebRtc,
} from '../../../types/index.ts'

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { markRaw } from 'vue'
import WildEmitter from 'wildemitter'
import { CallParticipantModel } from './CallParticipantModel.js'

describe('CallParticipantModel', () => {
	let webRtc: WebRtc
	let peer: { id: string, off: () => void }
	let callParticipantModel: CallParticipantModelType

	beforeEach(() => {
		webRtc = new WildEmitter()

		peer = markRaw({
			id: 'thePeerId',
			off: vi.fn(),
		})

		callParticipantModel = new CallParticipantModel({ peerId: 'thePeerId', webRtc })
		callParticipantModel.set('peer', peer)
	})

	describe('video effect', () => {
		test('is null by default', () => {
			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})

		test('is set when video is unmuted with an effect', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: 'blur' })

			expect(callParticipantModel.get('videoAvailable')).toBe(true)
			expect(callParticipantModel.get('videoEffect')).toBe('blur')
		})

		test('is cleared when video is unmuted with a null effect', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: 'blur' })
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: null })

			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})

		test('is kept when video is unmuted without an effect', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: 'image' })
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video' })

			expect(callParticipantModel.get('videoEffect')).toBe('image')
		})

		test('is not set when audio is unmuted', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'audio', effect: 'blur' })

			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})

		test('is not set for another peer', () => {
			webRtc.emit('unmute', { id: 'theOtherPeerId', name: 'video', effect: 'blur' })

			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})

		test('is cleared when video is muted', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: 'blur' })
			webRtc.emit('mute', { id: 'thePeerId', name: 'video' })

			expect(callParticipantModel.get('videoAvailable')).toBe(false)
			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})

		test('is cleared when the peer stream is removed', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: 'blur' })
			webRtc.emit('peerStreamRemoved', peer)

			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})

		test('is cleared when the peer is set to null', () => {
			webRtc.emit('unmute', { id: 'thePeerId', name: 'video', effect: 'blur' })
			callParticipantModel.setPeer(null)

			expect(callParticipantModel.get('videoEffect')).toBe(null)
		})
	})
})
