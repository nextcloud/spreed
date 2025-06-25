/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import { createStore } from 'vuex'
import VideoVue from './VideoVue.vue'
import storeConfig from '../../../store/storeConfig.js'
import EmitterMixin from '../../../utils/EmitterMixin.js'
import CallParticipantModel from '../../../utils/webrtc/models/CallParticipantModel.js'

describe('VideoVue.vue', () => {
	let store
	let testStoreConfig

	let callParticipantModel

	/**
	 * Constructor
	 */
	function PeerMock() {
		this._superEmitterMixin()

		this.id = 'theId'
		this.nick = 'The nick'
		this.pc = {
			connectionState: 'new',
			iceConnectionState: 'new',
			signalingState: 'stable',
		}
	}
	PeerMock.prototype._setIceConnectionState = function(iceConnectionState) {
		this.pc.iceConnectionState = iceConnectionState
		this._trigger('extendedIceConnectionStateChange', [iceConnectionState])
	}
	PeerMock.prototype._setSignalingState = function(signalingState) {
		this.pc.signalingState = signalingState
		this._trigger('signalingStateChange', [signalingState])
	}
	EmitterMixin.apply(PeerMock.prototype)
	// Override _trigger from EmitterMixin, as it includes "this" as the first
	// argument.
	PeerMock.prototype._trigger = function(event, args) {
		let handlers = this._handlers[event]
		if (!handlers) {
			return
		}

		if (!args) {
			args = []
		}

		handlers = handlers.slice(0)
		for (let i = 0; i < handlers.length; i++) {
			const handler = handlers[i]
			handler.apply(handler, args)
		}
	}

	beforeEach(() => {
		setActivePinia(createPinia())

		testStoreConfig = cloneDeep(storeConfig)
		store = createStore(testStoreConfig)

		const webRtcMock = {
			on: jest.fn(),
			off: jest.fn(),
		}
		callParticipantModel = new CallParticipantModel({
			peerId: 'theId',
			webRtc: webRtcMock,
		})
	})

	describe('connection state feedback', () => {
		const connectionMessage = {
			NOT_ESTABLISHED: 'Connection could not be established. Trying again …',
			NOT_ESTABLISHED_NOT_RETRYING: 'Connection could not be established …',
			LOST: 'Connection lost. Trying to reconnect …',
			LOST_NOT_RETRYING: 'Connection was lost and could not be re-established …',
			PROBLEMS: 'Connection problems …',
			NONE: null,
		}

		let wrapper

		/**
		 * "setupWrapper()" needs to be called right before checking the wrapper
		 * to ensure that the component state is updated. If the wrapper is
		 * created at the beginning of each test "await Vue.nextTick()" would
		 * need to be called instead (and for that the tests would need to be
		 * async).
		 */
		function setupWrapper() {
			wrapper = shallowMount(VideoVue, {
				global: { plugins: [store] },
				props: {
					model: callParticipantModel,
					token: 'theToken',
					sharedData: {
						remoteVideoBlocker: {
							increaseVisibleCounter: jest.fn(),
						},
						promoted: false,
					},
				},
			})
		}

		/**
		 * @param {string} expectedText Expected connection label
		 */
		function assertConnectionMessageLabel(expectedText) {
			const connectionMessageLabel = wrapper.find('.connection-message')
			if (expectedText) {
				expect(connectionMessageLabel.exists()).toBe(true)
				expect(connectionMessageLabel.text()).toBe(expectedText)
			} else {
				expect(connectionMessageLabel.exists()).toBe(false)
			}
		}

		/**
		 * @param {boolean} expected Whether the loading icon is shown
		 */
		function assertLoadingIconIsShown(expected) {
			const loadingIcon = wrapper.findComponent({ name: 'NcLoadingIcon' })
			expect(loadingIcon.exists()).toBe(expected)
		}

		/**
		 * @param {boolean} expected Whether the connection is not connected
		 */
		function assertNotConnected(expected) {
			const notConnected = wrapper.find('.not-connected')
			expect(notConnected.exists()).toBe(expected)
		}

		test('participant just created', () => {
			setupWrapper()

			assertConnectionMessageLabel(connectionMessage.NONE)
			assertLoadingIconIsShown(true)
			assertNotConnected(true)
		})

		test('no peer', () => {
			callParticipantModel.setPeer(null)

			setupWrapper()

			assertConnectionMessageLabel(connectionMessage.NONE)
			assertLoadingIconIsShown(false)
			assertNotConnected(false)
		})

		describe('original connection', () => {
			let peerMock

			beforeEach(() => {
				peerMock = new PeerMock()
				callParticipantModel.setPeer(peerMock)
			})

			test('peer just set', () => {
				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('sending offer', () => {
				peerMock._setSignalingState('have-local-offer')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('receiving offer', () => {
				peerMock._setSignalingState('have-remote-offer')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('negotiation finished', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('establishing connection', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('connection established', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(false)
				assertNotConnected(false)
			})

			test('connection established (completed)', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setIceConnectionState('completed')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(false)
				assertNotConnected(false)
			})

			test('disconnected', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setIceConnectionState('disconnected')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('disconnected without ever connecting', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('disconnected')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('disconnected long', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setIceConnectionState('disconnected')
				// Custom event emitted when there is no HPB and the connection
				// has been disconnected for a few seconds
				peerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('disconnected long without ever connecting', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('disconnected')
				// Custom event emitted when there is no HPB and the connection
				// has been disconnected for a few seconds
				peerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('failed', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setIceConnectionState('disconnected')
				peerMock._setIceConnectionState('failed')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.LOST)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('failed without ever connecting', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('failed')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})
		})

		describe('renegotiation', () => {
			let peerMock

			beforeEach(() => {
				peerMock = new PeerMock()
				callParticipantModel.setPeer(peerMock)
			})

			test('started after connection established', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setSignalingState('have-remote-offer')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(false)
				assertNotConnected(false)
			})

			test('started after connection established and then finished', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(false)
				assertNotConnected(false)
			})

			test('started before disconnected', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setIceConnectionState('disconnected')

				setupWrapper()

				// FIXME The message should be "PROBLEMS" rather than "LOST", as
				// the negotiation is not caused by the disconnection itself.
				// However it does not seem to be an easy way to do it right
				// now.
				assertConnectionMessageLabel(connectionMessage.LOST)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('started before disconnected and then finished', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setIceConnectionState('disconnected')
				peerMock._setSignalingState('stable')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('started after disconnected', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setIceConnectionState('disconnected')
				peerMock._setSignalingState('have-remote-offer')

				setupWrapper()

				// FIXME The message should be "PROBLEMS" rather than "LOST", as
				// the negotiation is not caused by the disconnection itself.
				// However it does not seem to be an easy way to do it right
				// now.
				assertConnectionMessageLabel(connectionMessage.LOST)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('started after disconnected and then finished', () => {
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')
				peerMock._setIceConnectionState('checking')
				peerMock._setIceConnectionState('connected')
				peerMock._setIceConnectionState('disconnected')
				peerMock._setSignalingState('have-remote-offer')
				peerMock._setSignalingState('stable')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})
		})

		describe('reconnection after no original connection', () => {
			let newPeerMock

			beforeEach(() => {
				callParticipantModel.setPeer(null)

				newPeerMock = new PeerMock()
				callParticipantModel.setPeer(newPeerMock)
			})

			test('peer just set', () => {
				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('sending offer', () => {
				newPeerMock._setSignalingState('have-local-offer')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('receiving offer', () => {
				newPeerMock._setSignalingState('have-remote-offer')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('negotiation finished', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('establishing connection', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('connection established', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('connected')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(false)
				assertNotConnected(false)
			})

			test('connection established (completed)', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('connected')
				newPeerMock._setIceConnectionState('completed')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NONE)
				assertLoadingIconIsShown(false)
				assertNotConnected(false)
			})

			test('disconnected', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('connected')
				newPeerMock._setIceConnectionState('disconnected')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('disconnected without ever connecting', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('disconnected')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('disconnected long', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('connected')
				newPeerMock._setIceConnectionState('disconnected')
				// Custom event emitted when there is no HPB and the connection
				// has been disconnected for a few seconds
				newPeerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('disconnected long without ever connecting', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('disconnected')
				// Custom event emitted when there is no HPB and the connection
				// has been disconnected for a few seconds
				newPeerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.PROBLEMS)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('failed', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('connected')
				newPeerMock._setIceConnectionState('disconnected')
				newPeerMock._setIceConnectionState('failed')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.LOST)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})

			test('failed without ever connecting', () => {
				newPeerMock._setSignalingState('have-remote-offer')
				newPeerMock._setSignalingState('stable')
				newPeerMock._setIceConnectionState('checking')
				newPeerMock._setIceConnectionState('failed')

				setupWrapper()

				assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
				assertLoadingIconIsShown(true)
				assertNotConnected(true)
			})
		})

		describe('reconnection', () => {
			let peerMock
			let newPeerMock

			beforeEach(() => {
				peerMock = new PeerMock()
				callParticipantModel.setPeer(peerMock)

				newPeerMock = new PeerMock()
			})

			describe('without having been connected', () => {
				beforeEach(() => {
					peerMock._setSignalingState('have-remote-offer')
					peerMock._setSignalingState('stable')
					peerMock._setIceConnectionState('checking')
					peerMock._setIceConnectionState('failed')

					callParticipantModel.setPeer(newPeerMock)
				})

				test('peer just set', () => {
					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('sending offer', () => {
					newPeerMock._setSignalingState('have-local-offer')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('receiving offer', () => {
					newPeerMock._setSignalingState('have-remote-offer')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('negotiation finished', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('establishing connection', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('connection established', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('connected')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NONE)
					assertLoadingIconIsShown(false)
					assertNotConnected(false)
				})

				test('disconnected', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('connected')
					newPeerMock._setIceConnectionState('disconnected')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.PROBLEMS)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('disconnected without ever connecting', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('disconnected')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('disconnected long', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('connected')
					newPeerMock._setIceConnectionState('disconnected')
					// Custom event emitted when there is no HPB and the
					// connection has been disconnected for a few seconds
					newPeerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.PROBLEMS)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('disconnected long without ever connecting', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('disconnected')
					// Custom event emitted when there is no HPB and the
					// connection has been disconnected for a few seconds
					newPeerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('failed', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('connected')
					newPeerMock._setIceConnectionState('disconnected')
					newPeerMock._setIceConnectionState('failed')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.LOST)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('failed without ever connecting', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('failed')

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED)
					assertLoadingIconIsShown(true)
					assertNotConnected(true)
				})

				test('failed without ever connecting and not retrying', () => {
					newPeerMock._setSignalingState('have-remote-offer')
					newPeerMock._setSignalingState('stable')
					newPeerMock._setIceConnectionState('checking')
					newPeerMock._setIceConnectionState('failed')
					// Custom event emitted when there is no HPB and the
					// connection has failed several times in a row
					newPeerMock._trigger('extendedIceConnectionStateChange', ['failed-no-restart'])

					setupWrapper()

					assertConnectionMessageLabel(connectionMessage.NOT_ESTABLISHED_NOT_RETRYING)
					assertLoadingIconIsShown(false)
					assertNotConnected(true)
				})
			})

			describe('after having been connected', () => {
				beforeEach(() => {
					peerMock._setSignalingState('have-remote-offer')
					peerMock._setSignalingState('stable')
					peerMock._setIceConnectionState('checking')
					peerMock._setIceConnectionState('connected')
				})

				describe('without HPB', () => {
					test('ICE restarted after disconnected long (no HPB)', () => {
						peerMock._setIceConnectionState('disconnected')
						// Custom event emitted when there is no HPB and the connection
						// has been disconnected for a few seconds
						peerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])
						peerMock._setSignalingState('have-local-offer')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('ICE restarted after failed (no HPB)', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('negotiation finished', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('establishing connection', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('connection established', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('connected')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.NONE)
						assertLoadingIconIsShown(false)
						assertNotConnected(false)
					})

					test('connection established (completed)', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('connected')
						peerMock._setIceConnectionState('completed')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.NONE)
						assertLoadingIconIsShown(false)
						assertNotConnected(false)
					})

					test('disconnected', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('connected')
						peerMock._setIceConnectionState('disconnected')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.PROBLEMS)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('disconnected without connecting the second time', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('disconnected')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('disconnected long', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('connected')
						peerMock._setIceConnectionState('disconnected')
						// Custom event emitted when there is no HPB and the connection
						// has been disconnected for a few seconds
						peerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.PROBLEMS)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('disconnected long without connecting the second time', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('disconnected')
						// Custom event emitted when there is no HPB and the connection
						// has been disconnected for a few seconds
						peerMock._trigger('extendedIceConnectionStateChange', ['disconnected-long'])

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('failed', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('connected')
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('failed without connecting the second time', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('failed')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('failed without connecting the second time and not retrying', () => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')
						peerMock._setSignalingState('have-local-offer')
						peerMock._setSignalingState('stable')
						peerMock._setIceConnectionState('checking')
						peerMock._setIceConnectionState('failed')
						// Custom event emitted when there is no HPB and the
						// connection has failed several times in a row
						peerMock._trigger('extendedIceConnectionStateChange', ['failed-no-restart'])

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST_NOT_RETRYING)
						assertLoadingIconIsShown(false)
						assertNotConnected(true)
					})
				})

				describe('with HPB', () => {
					beforeEach(() => {
						peerMock._setIceConnectionState('disconnected')
						peerMock._setIceConnectionState('failed')

						callParticipantModel.setPeer(newPeerMock)

						newPeerMock._setSignalingState('have-remote-offer')
					})

					test('requested renegotiation after connection failed', () => {
						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('negotiation finished', () => {
						newPeerMock._setSignalingState('stable')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('establishing connection', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('connection established', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')
						newPeerMock._setIceConnectionState('connected')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.NONE)
						assertLoadingIconIsShown(false)
						assertNotConnected(false)
					})

					test('connection established (completed)', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')
						newPeerMock._setIceConnectionState('connected')
						newPeerMock._setIceConnectionState('completed')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.NONE)
						assertLoadingIconIsShown(false)
						assertNotConnected(false)
					})

					test('disconnected', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')
						newPeerMock._setIceConnectionState('connected')
						newPeerMock._setIceConnectionState('disconnected')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.PROBLEMS)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('disconnected without connecting the second time', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')
						newPeerMock._setIceConnectionState('disconnected')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('failed', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')
						newPeerMock._setIceConnectionState('connected')
						newPeerMock._setIceConnectionState('disconnected')
						newPeerMock._setIceConnectionState('failed')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})

					test('failed without connecting the second time', () => {
						newPeerMock._setSignalingState('stable')
						newPeerMock._setIceConnectionState('checking')
						newPeerMock._setIceConnectionState('failed')

						setupWrapper()

						assertConnectionMessageLabel(connectionMessage.LOST)
						assertLoadingIconIsShown(true)
						assertNotConnected(true)
					})
				})
			})
		})
	})
})
