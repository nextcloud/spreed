/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref } from 'vue'
import EmitterMixin from '../EmitterMixin.js'
import CallParticipantsAudioPlayer from './CallParticipantsAudioPlayer.js'

/**
 * Stub of CallParticipantModel with just the attributes and methods used by
 * CallParticipantsAudioPlayer.
 *
 * @param {string} peerId the ID of the peer
 */
function CallParticipantModelStub(peerId) {
	this._superEmitterMixin()

	this.attributes = {
		peerId,
	}

	this.get = (key) => {
		return this.attributes[key]
	}

	this.set = (key, value) => {
		this.attributes[key] = value

		this._trigger('change:' + key, [value])
	}
}
EmitterMixin.apply(CallParticipantModelStub.prototype)

/**
 * Stub of CallParticipantCollection with just the attributes and methods used
 * by CallParticipantsAudioPlayer.
 */
function CallParticipantCollectionStub() {
	this._superEmitterMixin()

	this.callParticipantModels = ref([])
}
EmitterMixin.apply(CallParticipantCollectionStub.prototype)

/**
 * Mock of MediaStream with an id for easier tracking in tests.
 *
 * HTMLAudioElement requires srcObject to conform to the MediaStream interface,
 * but in jsdom anything can be assigned, so the mock is kept to a minimum.
 *
 * @param {string} id the id for the stream.
 */
function MediaStreamMock(id) {
	this.id = id
}

describe('CallParticipantsAudioPlayer', () => {
	let callParticipantCollection
	let callParticipantsAudioPlayer

	/**
	 * Adds a CallParticipantModel to the collection.
	 *
	 * The participant must not be yet in the collection.
	 *
	 * @param {object} callParticipantModel the CallParticipantModel to add.
	 */
	function addCallParticipantModel(callParticipantModel) {
		callParticipantCollection.callParticipantModels.value.push(callParticipantModel)

		callParticipantCollection._trigger('add', [callParticipantModel])
	}

	/**
	 * Removes a CallParticipantModel from the collection.
	 *
	 * The participant must be in the collection.
	 *
	 * @param {object} callParticipantModel the CallParticipantModel to remove.
	 */
	function removeCallParticipantModel(callParticipantModel) {
		const index = callParticipantCollection.callParticipantModels.value.indexOf(callParticipantModel)
		callParticipantCollection.callParticipantModels.value.splice(index, 1)

		callParticipantCollection._trigger('remove', [callParticipantModel])
	}

	/**
	 * Asserts that the audio element with the given id has the expected
	 * srcObject and muted value.
	 *
	 * @param {string} audioElementId the id of the audio element in the player.
	 * @param {object|null} expectedSrcObject the expected srcObject in the
	 *        element.
	 * @param {boolean} expectedMuted the expected muted value in the element.
	 */
	function assertAudioElement(audioElementId, expectedSrcObject, expectedMuted) {
		expect(callParticipantsAudioPlayer._audioElements.get(audioElementId)).not.toBe(null)
		expect(callParticipantsAudioPlayer._audioElements.get(audioElementId).tagName.toLowerCase()).toBe('audio')
		expect(callParticipantsAudioPlayer._audioElements.get(audioElementId).srcObject).toBe(expectedSrcObject)
		expect(callParticipantsAudioPlayer._audioElements.get(audioElementId).muted).toBe(expectedMuted)
	}

	beforeEach(() => {
		callParticipantCollection = new CallParticipantCollectionStub()

		callParticipantsAudioPlayer = new CallParticipantsAudioPlayer(callParticipantCollection)
	})

	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('constructor', () => {
		test('without participants', () => {
			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		test('with several participants', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			callParticipantModel2.attributes.audioAvailable = false

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)

			const callParticipantModel3 = new CallParticipantModelStub('peerId3')
			addCallParticipantModel(callParticipantModel3)

			callParticipantModel3.attributes.audioAvailable = true

			const stream3 = new MediaStreamMock('stream3')
			callParticipantModel3.set('stream', stream3)
			const screen3 = new MediaStreamMock('screen3')
			callParticipantModel3.set('screen', screen3)

			callParticipantsAudioPlayer = new CallParticipantsAudioPlayer(callParticipantCollection)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(3)
			assertAudioElement('peerId2-stream', stream2, true)
			assertAudioElement('peerId3-stream', stream3, false)
			assertAudioElement('peerId3-screen', screen3, false)
		})

		test('add stream and screen', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			callParticipantsAudioPlayer = new CallParticipantsAudioPlayer(callParticipantCollection)

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(2)
			assertAudioElement('peerId1-stream', stream1, true)
			assertAudioElement('peerId1-screen', screen1, false)
		})

		test('change audio available', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			callParticipantModel1.attributes.audioAvailable = false

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			callParticipantsAudioPlayer = new CallParticipantsAudioPlayer(callParticipantCollection)

			callParticipantModel1.set('audioAvailable', true)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(2)
			assertAudioElement('peerId1-stream', stream1, false)
			assertAudioElement('peerId1-screen', screen1, false)
		})

		test('remove participant, stream and screen', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)
			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			callParticipantsAudioPlayer = new CallParticipantsAudioPlayer(callParticipantCollection)

			callParticipantModel1.set('stream', null)
			callParticipantModel1.set('screen', null)

			removeCallParticipantModel(callParticipantModel2)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})
	})

	describe('add stream and screen', () => {
		test('stream with available audio', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = true

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-stream', stream, false)
		})

		test('stream without available audio', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = false

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-stream', stream, true)
		})

		test('screen', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-screen', screen, false)
		})

		test('stream and screen', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = false

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(2)
			assertAudioElement('peerId1-stream', stream, true)
			assertAudioElement('peerId1-screen', screen, false)
		})

		// This should not happen (the stream and screen are expected to be set
		// once the participant is already in the collection), but test it just
		// in case.
		test('participant', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')

			callParticipantModel.attributes.audioAvailable = false

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			addCallParticipantModel(callParticipantModel)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(2)
			assertAudioElement('peerId1-stream', stream, true)
			assertAudioElement('peerId1-screen', screen, false)
		})

		test('several participants, streams and screens', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)
			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			callParticipantModel1.attributes.audioAvailable = false

			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)
			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)

			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			const callParticipantModel3 = new CallParticipantModelStub('peerId3')
			addCallParticipantModel(callParticipantModel3)

			callParticipantModel3.attributes.audioAvailable = true

			const stream3 = new MediaStreamMock('stream3')
			callParticipantModel3.set('stream', stream3)

			const callParticipantModel4 = new CallParticipantModelStub('peerId4')

			callParticipantModel4.attributes.audioAvailable = true

			const stream4 = new MediaStreamMock('stream4')
			callParticipantModel4.set('stream', stream4)

			addCallParticipantModel(callParticipantModel4)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(5)
			assertAudioElement('peerId1-stream', stream1, true)
			assertAudioElement('peerId1-screen', screen1, false)
			assertAudioElement('peerId2-screen', screen2, false)
			assertAudioElement('peerId3-stream', stream3, false)
			assertAudioElement('peerId4-stream', stream4, false)
		})

		// This should not happen (the previous stream or screen is expected to
		// be removed first), but test it just in case.
		test('replace stream and screen', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = false

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen1)

			const audioElementStream1 = callParticipantsAudioPlayer._audioElements.get('peerId1-stream')
			const audioElementScreen1 = callParticipantsAudioPlayer._audioElements.get('peerId1-screen')

			callParticipantModel.attributes.audioAvailable = true

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel.set('stream', stream2)
			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel.set('screen', screen2)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(2)
			assertAudioElement('peerId1-stream', stream2, false)
			assertAudioElement('peerId1-screen', screen2, false)
			expect(audioElementStream1.srcObject).toBe(null)
			expect(audioElementScreen1.srcObject).toBe(null)
		})
	})

	describe('change audio available', () => {
		test('not available with stream', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = true

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)

			callParticipantModel.set('audioAvailable', false)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-stream', stream, true)
		})

		test('not available without stream', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.set('audioAvailable', false)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		test('not available with screen', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = true

			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			callParticipantModel.set('audioAvailable', false)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-screen', screen, false)
		})

		test('available with stream', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = false

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)

			callParticipantModel.set('audioAvailable', true)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-stream', stream, false)
		})

		test('available without stream', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.set('audioAvailable', true)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		test('several participants', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)
			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			callParticipantModel1.attributes.audioAvailable = false

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			callParticipantModel1.set('audioAvailable', true)

			const callParticipantModel3 = new CallParticipantModelStub('peerId3')
			addCallParticipantModel(callParticipantModel3)

			callParticipantModel3.attributes.audioAvailable = true

			const stream3 = new MediaStreamMock('stream1')
			callParticipantModel3.set('stream', stream3)

			callParticipantModel3.set('audioAvailable', false)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(4)
			assertAudioElement('peerId1-stream', stream1, false)
			assertAudioElement('peerId1-screen', screen1, false)
			assertAudioElement('peerId2-screen', screen2, false)
			assertAudioElement('peerId3-stream', stream3, true)
		})
	})

	describe('remove stream and screen', () => {
		test('stream', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = true

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			const audioElementStream = callParticipantsAudioPlayer._audioElements.get('peerId1-stream')

			callParticipantModel.set('stream', null)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-screen', screen, false)
			expect(audioElementStream.srcObject).toBe(null)
		})

		test('screen', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = true

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			const audioElementScreen = callParticipantsAudioPlayer._audioElements.get('peerId1-screen')

			callParticipantModel.set('screen', null)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(1)
			assertAudioElement('peerId1-stream', stream, false)
			expect(audioElementScreen.srcObject).toBe(null)
		})

		test('participant', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			const audioElementStream = callParticipantsAudioPlayer._audioElements.get('peerId1-stream')
			const audioElementScreen = callParticipantsAudioPlayer._audioElements.get('peerId1-screen')

			removeCallParticipantModel(callParticipantModel)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
			expect(audioElementStream.srcObject).toBe(null)
			expect(audioElementScreen.srcObject).toBe(null)
		})

		test('several participants, stream and screen', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)
			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			const callParticipantModel3 = new CallParticipantModelStub('peerId3')
			addCallParticipantModel(callParticipantModel3)

			const stream3 = new MediaStreamMock('stream3')
			callParticipantModel3.set('stream', stream3)
			const screen3 = new MediaStreamMock('screen3')
			callParticipantModel3.set('screen', screen3)

			const audioElementStream1 = callParticipantsAudioPlayer._audioElements.get('peerId1-stream')
			const audioElementScreen1 = callParticipantsAudioPlayer._audioElements.get('peerId1-screen')
			const audioElementStream3 = callParticipantsAudioPlayer._audioElements.get('peerId3-stream')
			const audioElementScreen3 = callParticipantsAudioPlayer._audioElements.get('peerId3-screen')

			removeCallParticipantModel(callParticipantModel1)
			callParticipantModel3.set('stream', null)
			callParticipantModel3.set('screen', null)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(2)
			assertAudioElement('peerId2-stream', stream2, true)
			assertAudioElement('peerId2-screen', screen2, false)
			expect(audioElementStream1.srcObject).toBe(null)
			expect(audioElementScreen1.srcObject).toBe(null)
			expect(audioElementStream3.srcObject).toBe(null)
			expect(audioElementScreen3.srcObject).toBe(null)
		})

		// This should not happen, but test it just in case.
		test('change stream and screen in removed participant', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			removeCallParticipantModel(callParticipantModel)

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		// This should not happen, but test it just in case.
		test('change audio available in removed participant', () => {
			const callParticipantModel = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel)

			callParticipantModel.attributes.audioAvailable = false

			const stream = new MediaStreamMock('stream1')
			callParticipantModel.set('stream', stream)
			const screen = new MediaStreamMock('screen1')
			callParticipantModel.set('screen', screen)

			removeCallParticipantModel(callParticipantModel)

			callParticipantModel.set('audioAvailable', true)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})
	})

	describe('destroy', () => {
		test('without participants', () => {
			callParticipantsAudioPlayer.destroy()

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		test('with several participants', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)

			const callParticipantModel3 = new CallParticipantModelStub('peerId3')
			addCallParticipantModel(callParticipantModel3)

			const stream3 = new MediaStreamMock('stream3')
			callParticipantModel3.set('stream', stream3)
			const screen3 = new MediaStreamMock('screen3')
			callParticipantModel3.set('screen', screen3)

			const audioElementStream2 = callParticipantsAudioPlayer._audioElements.get('peerId2-stream')
			const audioElementStream3 = callParticipantsAudioPlayer._audioElements.get('peerId3-stream')
			const audioElementScreen3 = callParticipantsAudioPlayer._audioElements.get('peerId3-screen')

			callParticipantsAudioPlayer.destroy()

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
			expect(audioElementStream2.srcObject).toBe(null)
			expect(audioElementStream3.srcObject).toBe(null)
			expect(audioElementScreen3.srcObject).toBe(null)
		})

		test('add stream and screen after destroying', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			callParticipantsAudioPlayer.destroy()

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)
			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		test('change audio available after destroying', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			callParticipantModel1.attributes.audioAvailable = false

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			callParticipantsAudioPlayer.destroy()

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			callParticipantModel2.attributes.audioAvailable = false

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)
			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			callParticipantModel1.set('audioAvailable', true)

			callParticipantModel2.set('audioAvailable', true)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})

		test('remove stream and screen after destroying', () => {
			const callParticipantModel1 = new CallParticipantModelStub('peerId1')
			addCallParticipantModel(callParticipantModel1)

			const stream1 = new MediaStreamMock('stream1')
			callParticipantModel1.set('stream', stream1)
			const screen1 = new MediaStreamMock('screen1')
			callParticipantModel1.set('screen', screen1)

			const callParticipantModel2 = new CallParticipantModelStub('peerId2')
			addCallParticipantModel(callParticipantModel2)

			const stream2 = new MediaStreamMock('stream2')
			callParticipantModel2.set('stream', stream2)
			const screen2 = new MediaStreamMock('screen2')
			callParticipantModel2.set('screen', screen2)

			callParticipantsAudioPlayer.destroy()

			callParticipantModel1.set('stream', null)
			callParticipantModel1.set('screen', null)

			removeCallParticipantModel(callParticipantModel2)

			expect(callParticipantsAudioPlayer._audioElements.size).toBe(0)
		})
	})
})
