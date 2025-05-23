/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Olm from '@matrix-org/olm'
import base64js from 'base64-js'
import debounce from 'debounce'
import { isEqual } from 'lodash'
import { v4 as uuidv4 } from 'uuid'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import Signaling from '../signaling.js'
import Peer from '../webrtc/simplewebrtc/peer.js'
import SimpleWebRTC from '../webrtc/simplewebrtc/simplewebrtc.js'
import { importKey, ratchet } from './crypto-utils.js'
import Deferred from './JitsiDeferred.js'
import E2EEcontext from './JitsiE2EEContext.js'
import initializeOlm from './olm.js'

const supportsTransform
	// Firefox
	= (window.RTCRtpScriptTransform && window.RTCRtpSender && 'transform' in RTCRtpSender.prototype)
	// Chrome
		|| (window.RTCRtpReceiver && 'createEncodedStreams' in RTCRtpReceiver.prototype && window.RTCRtpSender && 'createEncodedStreams' in RTCRtpSender.prototype)

// Period which we'll wait before updating / rotating our keys when a participant
// joins or leaves.
const DEBOUNCE_PERIOD = 5000

const REQUEST_TIMEOUT_MS = 5 * 1000

const TYPE_ENCRYPTION_START = 'encryption.start'
const TYPE_ENCRYPTION_FINISH = 'encryption.finish'
const TYPE_ENCRYPTION_SET_KEY = 'encryption.setkey'
const TYPE_ENCRYPTION_GOT_KEY = 'encryption.gotkey'
const TYPE_ENCRYPTION_ERROR = 'encryption.error'

class Encryption {
	/**
	 * Check if the current browser supports encryption.
	 *
	 * @return {boolean} Returns true if supported and throws an error otherwise.
	 * @async
	 */
	static async isSupported() {
		if (!supportsTransform) {
			throw new Error('stream transform is not supported')
		}

		await initializeOlm()
		return true
	}

	/**
	 * Check if encryption should be enabled for calls.
	 *
	 * @return {boolean} Returns true if encryption should be enabled, false otherwise.
	 */
	static isEnabled() {
		if (!hasTalkFeature('local', 'call-end-to-end-encryption')) {
			return false
		}

		const enabled = getTalkConfig('local', 'call', 'end-to-end-encryption')
		return enabled || (enabled === undefined)
	}

	/**
	 * Create the encryption session.
	 *
	 * @param {Signaling} signaling The signaling instance.
	 */
	constructor(signaling) {
		this.signaling = signaling
		this._webrtc = null

		this._key = this._generateKey()
		this._keyIndex = 0
		this._sessions = {}
		this._requests = new Map()

		this._account = new Olm.Account()
		this._account.create()
		this._keys = JSON.parse(this._account.identity_keys())

		this.context = new E2EEcontext()

		this._handleSessionIdBound = this._handleSessionId.bind(this)
		this.signaling.on('sessionId', this._handleSessionIdBound)
		this._handleUsersJoinedBound = this._handleUsersJoined.bind(this)
		this.signaling.on('usersJoined', this._handleUsersJoinedBound)
		this._handleUsersLeftBound = this._handleUsersLeft.bind(this)
		this.signaling.on('usersLeft', this._handleUsersLeftBound)
		this._handleMessageBound = this._handleMessage.bind(this)
		this.signaling.on('message', this._handleMessageBound)

		this._rotateKey = debounce(this._rotateKeyImpl, DEBOUNCE_PERIOD)
		this._ratchetKey = debounce(this._ratchetKeyImpl, DEBOUNCE_PERIOD)

		this._handlePeerCreatedBound = this._handlePeerCreated.bind(this)

		this._handleSessionId(signaling.sessionId || '')
		this._handleUsersJoined(Object.values(signaling.joinedUsers))
	}

	/**
	 * Set the WebRTC instance to use.
	 *
	 * @param {SimpleWebRTC} webrtc The WebRTC instance.
	 */
	setWebRtc(webrtc) {
		if (this._webrtc) {
			this._webrtc.off('createdPeer', this._handlePeerCreatedBound)
		}

		webrtc.on('createdPeer', this._handlePeerCreatedBound)
		this._webrtc = webrtc
	}

	/**
	 * Close the encryption session.
	 */
	close() {
		this.signaling.off('sessionId', this._handleSessionIdBound)
		this.signaling.off('usersJoined', this._handleUsersJoinedBound)
		this.signaling.off('usersLeft', this._handleUsersLeftBound)
		this.signaling.off('message', this._handleMessageBound)
		if (this._webrtc) {
			this._webrtc.off('createdPeer', this._handlePeerCreatedBound)
			this._webrtc = null
		}
		this._sessions = {}
		if (this._account) {
			this._account.free()
			this._account = null
		}
		this.context.cleanupAll()
	}

	/**
	 * Handle an event to store the local session id.
	 *
	 * @param {string} sessionId The current local session id.
	 * @private
	 */
	_handleSessionId(sessionId) {
		this._sessionId = sessionId
		if (sessionId) {
			this.context.setKey(sessionId, this._key, this._keyIndex)
		}
	}

	/**
	 * Handle event when users joined.
	 *
	 * @param {Array<object>} users The list of joined users.
	 * @private
	 */
	_handleUsersJoined(users) {
		users.forEach((user) => {
			if (user.sessionid < this._sessionId) {
				this._startSession(user.sessionid)
			}
		})

		// Derieve new key from current so new users won't be able to decrypt past
		// content but existing users can deduct the new key by ratcheting
		// themselves.
		this._ratchetKey()
	}

	/**
	 * Handle event when users left.
	 *
	 * @param {Array<string>} sessionIds The list of session ids that have left.
	 * @private
	 */
	_handleUsersLeft(sessionIds) {
		sessionIds.forEach((sessionId) => {
			delete this._sessions[sessionId]
			this.context.cleanup(sessionId)
		})

		// Generate new key so previously joined users won't be able to decrypt
		// future data.
		this._rotateKey()
	}

	/**
	 * Handle received signaling message.
	 *
	 * @param {object} message The signaling message.
	 * @private
	 */
	_handleMessage(message) {
		const sender = message.from
		switch (message.payload?.type) {
			case TYPE_ENCRYPTION_START:
				this._processStartSession(sender, message)
				break
			case TYPE_ENCRYPTION_FINISH:
				this._processFinishSession(sender, message)
				break
			case TYPE_ENCRYPTION_SET_KEY:
				this._processSessionSetKey(sender, message)
				break
			case TYPE_ENCRYPTION_GOT_KEY:
				this._processSessionGotKey(sender, message)
				break
			case TYPE_ENCRYPTION_ERROR:
				this._processError(sender, message)
				break
		}
	}

	/**
	 * Returns the data for the given session id.
	 *
	 * @param {string} sessionId The session id.
	 * @return {object} The data for this session id.
	 * @private
	 */
	_sessionData(sessionId) {
		this._sessions[sessionId] = this._sessions[sessionId] || {}
		return this._sessions[sessionId]
	}

	/**
	 * Start encrypted session with remote session.
	 *
	 * @param {string} sessionId The remote session id.
	 * @return {Promise} A promise that will be resolved when the session has been established.
	 * @private
	 */
	_startSession(sessionId) {
		const sessionData = this._sessionData(sessionId)
		if (sessionData.session) {
			console.error('Already have a session')
			return Promise.reject(new Error('Already have a session'))
		}

		if (sessionData.startMsgId) {
			console.error('Session request already started')
			return Promise.reject(new Error('Session request already started'))
		}

		console.debug('Starting e2s session with', sessionId)
		this._account.generate_one_time_keys(1)
		const keys = JSON.parse(this._account.one_time_keys())
		const key = Object.values(keys.curve25519)[0]
		if (!key) {
			return Promise.reject(new Error('No one-time key created'))
		}

		this._account.mark_keys_as_published()
		const msgId = uuidv4()
		const message = {
			type: 'message',
			to: sessionId,
			payload: {
				id: msgId,
				type: TYPE_ENCRYPTION_START,
				identity: this._keys.curve25519,
				key,
			},
		}
		sessionData.startMsgId = msgId

		const d = new Deferred()

		d.setRejectTimeout(REQUEST_TIMEOUT_MS)
		d.catch((e) => {
			console.debug('Starting e2e session failed', sessionId, e)
			this._requests.delete(msgId)
			delete sessionData.startMsgId
		})
		this._requests.set(msgId, d)

		this.signaling.sendCallMessage(message)

		return d
	}

	/**
	 * Rotates the local key. Rotating the key implies creating a new one, then distributing it
	 * to all participants and once they all received it, start using it.
	 *
	 * @private
	 * @async
	 */
	async _rotateKeyImpl() {
		console.debug('Rotating key')

		this._rotating = true
		try {
			this._key = this._generateKey()

			// Wait until new key is distributed before using it.
			const index = await this._updateKey(this._key)

			this.context.setKey(this._sessionId, this._key, index)
		} finally {
			this._rotating = false
		}
	}

	/**
	 * Advances the current key by using ratcheting.
	 *
	 * @private
	 * @async
	 */
	async _ratchetKeyImpl() {
		if (this._rotating) {
			console.debug('Not ratchetting key, currently rotating')
			return
		}

		console.debug('Ratchetting key')
		const material = await importKey(this._key)
		const newKey = await ratchet(material)

		this._key = new Uint8Array(newKey)

		const index = this._updateCurrentKey(this._key)

		if (this._sessionId) {
			this.context.setKey(this._sessionId, this._key, index)
		}
	}

	/**
	 * Generates a new 256 bit random key.
	 *
	 * @return {Uint8Array} The generated key.
	 * @private
	 */
	_generateKey() {
		return window.crypto.getRandomValues(new Uint8Array(32))
	}

	/**
	 * Update the key and send it to all sessions. Will only return after it has been received by all sessions.
	 *
	 * @param {Uint8Array} key The key to update to.
	 * @return {number} The updated key index.
	 * @async
	 */
	async _updateKey(key) {
		// Store it locally for new sessions.
		this._key = key
		this._keyIndex++

		const promises = []

		Object.entries(this._sessions).forEach((entry) => {
			const [sessionId, sessionData] = entry
			promises.push(this._sendKey(sessionId, sessionData))
		})

		await Promise.allSettled(promises)

		return this._keyIndex
	}

	/**
	 * Updates the current participant key.
	 * @param {Uint8Array|boolean} key - The new key.
	 * @return {number} The current key index.
	 * @private
	 */
	_updateCurrentKey(key) {
		this._key = key

		return this._keyIndex
	}

	/**
	 * Encrypt the current local key for the given session.
	 *
	 * @param {Olm.Session} session The Olm session to encrypt the key for.
	 * @return {object} The encrypted key data.
	 * @private
	 */
	_encryptKey(session) {
		const data = {}

		if (this._key !== undefined) {
			data.key = this._key ? base64js.fromByteArray(this._key) : false
			data.index = this._keyIndex
		}

		return session.encrypt(JSON.stringify(data))
	}

	/**
	 * Process a request to start an encrypted session with the given peer.
	 *
	 * @param {string} sessionId The session id that sent the start request.
	 * @param {object} message The received message.
	 * @private
	 */
	_processStartSession(sessionId, message) {
		const sessionData = this._sessionData(sessionId)
		if (sessionData.session) {
			console.warn('Already has a session', sessionId)
			this._sendError(sessionId, 'Session already created')
			return
		}

		console.debug('Received e2s session request from', sessionId)
		const payload = message.payload
		const session = new Olm.Session()
		session.create_outbound(this._account, payload.identity, payload.key)
		sessionData.session = session
		const response = {
			type: 'message',
			to: sessionId,
			payload: {
				id: payload.id,
				type: TYPE_ENCRYPTION_FINISH,
				key: this._encryptKey(session),
			},
		}
		this.signaling.sendCallMessage(response)
	}

	/**
	 * Finish the request to start an encrypted session with the given peer.
	 *
	 * @param {string} sessionId The session id that sent the finish request.
	 * @param {object} message The received message.
	 * @private
	 */
	_processFinishSession(sessionId, message) {
		const sessionData = this._sessionData(sessionId)
		if (sessionData.session) {
			console.warn('Already has a session', sessionId)
			this._sendError(sessionId, 'Session already created')
			return
		}

		const payload = message.payload
		if (payload.id !== sessionData.startMsgId) {
			console.warn('Received finish with wrong id', sessionId)
			this._sendError(sessionId, 'Finish has wrong id')
			return
		}

		console.debug('Finished e2s session with', sessionId)
		const session = new Olm.Session()
		session.create_inbound(this._account, payload.key.body)
		this._account.remove_one_time_keys(session)

		// Get current key (if present).
		const data = session.decrypt(payload.key.type, payload.key.body)
		sessionData.session = session
		delete sessionData.startMsgId

		const d = this._requests.get(payload.id)
		this._requests.delete(payload.id)
		d.resolve()

		const decoded = JSON.parse(data)
		if (decoded.key) {
			const key = base64js.toByteArray(decoded.key)
			const index = decoded.index

			sessionData.lastKey = key
			console.debug('Key updated', sessionId, index, decoded.key)
			this.context.setKey(sessionId, key, index)
		}

		if (this._key !== undefined) {
			// Notify remote session about local key.
			this._sendKey(sessionId, sessionData)
		}
	}

	/**
	 * Process a key request from the given session.
	 *
	 * @param {string} sessionId The session id that sent the set key request.
	 * @param {object} message The received message.
	 * @private
	 */
	_processSessionSetKey(sessionId, message) {
		const sessionData = this._sessionData(sessionId)
		if (!sessionData.session) {
			console.warn('No session found', sessionId)
			this._sendError(sessionId, 'No session for setting key')
			return
		}

		const payload = message.payload
		const data = sessionData.session.decrypt(payload.key.type, payload.key.body)

		const decoded = JSON.parse(data)
		if (decoded.key !== undefined && decoded.index !== undefined) {
			const key = base64js.toByteArray(decoded.key)
			const index = decoded.index

			if (!isEqual(sessionData.lastKey, key)) {
				sessionData.lastKey = key
				console.debug('Key updated', sessionId, index, decoded.key)
				this.context.setKey(sessionId, key, index)
			}

			// Confirm that we have received the key.
			const response = {
				type: 'message',
				to: sessionId,
				payload: {
					id: payload.id,
					type: TYPE_ENCRYPTION_GOT_KEY,
					key: this._encryptKey(sessionData.session),
				},
			}
			this.signaling.sendCallMessage(response)
		}
	}

	/**
	 * Process request that a key has been received by the given session.
	 *
	 * @param {string} sessionId The session id that received the key.
	 * @param {object} message The received message.
	 * @private
	 */
	_processSessionGotKey(sessionId, message) {
		const sessionData = this._sessionData(sessionId)
		if (!sessionData.session) {
			console.warn('No session found', sessionId)
			this._sendError(sessionId, 'No session for confirming key')
			return
		}

		const payload = message.payload
		const data = sessionData.session.decrypt(payload.key.type, payload.key.body)

		const decoded = JSON.parse(data)
		if (decoded.key !== undefined && decoded.index !== undefined) {
			const key = base64js.toByteArray(decoded.key)
			const index = decoded.index

			if (!isEqual(sessionData.lastKey, key)) {
				sessionData.lastKey = key
				console.debug('Key updated', sessionId, index, decoded.key)
				this.context.setKey(sessionId, key, index)
			}
		}

		const d = this._requests.get(payload.id)
		this._requests.delete(payload.id)
		d.resolve()
	}

	/**
	 * Process error message.
	 *
	 * @param {string} sessionId The session id that sent the error.
	 * @param {object} message The received message.
	 * @private
	 */
	_processError(sessionId, message) {
		console.error('Received error', sessionId, message.payload.error)
	}

	/**
	 * Send error message to a remote session.
	 *
	 * @param {string} sessionId The session id to send the error to.
	 * @param {string|object} error The error message.
	 */
	_sendError(sessionId, error) {
		const message = {
			type: 'message',
			to: sessionId,
			payload: {
				type: TYPE_ENCRYPTION_ERROR,
				error,
			},
		}
		this.signaling.sendCallMessage(message)
	}

	/**
	 * Send the current encryption key to the given peer.
	 *
	 * @param {string} sessionId The session id to send the key to.
	 * @param {object|null|undefined} sessionData The optional data for the session.
	 * @return {Promise} A promise that will be resolved when the key has been received by the peer.
	 * @private
	 */
	_sendKey(sessionId, sessionData) {
		if (!sessionData) {
			sessionData = this._sessionData(sessionId)
		}
		if (!sessionData.session) {
			console.warn('No session found', sessionId, sessionData)
			return Promise.reject(new Error('No session found'))
		}

		const msgId = uuidv4()
		const response = {
			type: 'message',
			to: sessionId,
			payload: {
				id: msgId,
				type: TYPE_ENCRYPTION_SET_KEY,
				key: this._encryptKey(sessionData.session),
			},
		}

		const d = new Deferred()

		d.setRejectTimeout(REQUEST_TIMEOUT_MS)
		d.catch(() => {
			this._requests.delete(msgId)
		})
		this._requests.set(msgId, d)

		this.signaling.sendCallMessage(response)

		return d
	}

	/**
	 * A peer was created.
	 *
	 * @param {Peer} peer The peer that was created.
	 * @private
	 */
	_handlePeerCreated(peer) {
		if (peer.id === this._sessionId) {
			// Own peers are sending.
			peer.pc.getSenders().forEach((sender) => {
				this.context.handleSender(sender, sender.track.kind, peer.id)
			})
		} else {
			// Remote peers are receiving.
			if (peer.stream) {
				this._processReceivePeerStream(peer, peer.stream)
			}
			peer.pc.addEventListener('addstream', (event) => {
				this._processReceivePeerStream(peer, event.stream)
			})
		}
	}

	/**
	 * Returns the receiver for a given track.
	 *
	 * @param {RTCPeerConnection} pc The peer connection to search the receiver in.
	 * @param {MediaStreamTrack} track The track for which the receiver should be returned.
	 * @return {RTCRtpReceiver|undefined} The found receiver or undefined.
	 * @private
	 */
	_findReceiverForTrack(pc, track) {
		return pc && pc.getReceivers().find((r) => r.track === track)
	}

	/**
	 * Process streams of a receiving peer for decrypting.
	 *
	 * @param {Peer} peer The peer where the stream has been created.
	 * @param {MediaStream} stream The created stream.
	 * @private
	 */
	_processReceivePeerStream(peer, stream) {
		stream.getTracks().forEach((track) => {
			const receiver = this._findReceiverForTrack(peer.pc, track)
			this.context.handleReceiver(receiver, receiver.track.kind, peer.id)
		})

		stream.addEventListener('addtrack', (event) => {
			const receiver = this._findReceiverForTrack(peer.pc, event.track)
			this.context.handleReceiver(receiver, receiver.track.kind, peer.id)
		})
	}
}

export default Encryption
