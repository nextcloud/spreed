/**
 *
 * @copyright Copyright (c) 2022, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vuex from 'vuex'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import storeConfig from '../../../store/storeConfig'

import EmitterMixin from '../../../utils/EmitterMixin'
import CallParticipantModel from '../../../utils/webrtc/models/CallParticipantModel'

import Video from './Video'

describe('Video.vue', () => {
	let localVue
	let store
	let testStoreConfig

	let callParticipantModel

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
		localVue = createLocalVue()
		localVue.use(Vuex)

		testStoreConfig = cloneDeep(storeConfig)
		store = new Vuex.Store(testStoreConfig)

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

		// "setupWrapper()" needs to be called right before checking the wrapper
		// to ensure that the component state is updated. If the wrapper is
		// created at the beginning of each test "await Vue.nextTick()" would
		// need to be called instead (and for that the tests would need to be
		// async).
		function setupWrapper() {
			wrapper = shallowMount(Video, {
				localVue,
				store,
				propsData: {
					model: callParticipantModel,
					token: 'theToken',
					sharedData: {
						promoted: false,
					},
				},
			})
		}

		function assertConnectionMessageLabel(expectedText) {
			const connectionMessageLabel = wrapper.find('.connection-message')
			if (expectedText) {
				expect(connectionMessageLabel.exists()).toBe(true)
				expect(connectionMessageLabel.text()).toBe(expectedText)
			} else {
				expect(connectionMessageLabel.exists()).toBe(false)
			}
		}

		function assertLoadingIconIsShown(expected) {
			const loadingIcon = wrapper.find('.icon-loading')
			expect(loadingIcon.exists()).toBe(expected)
		}

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
