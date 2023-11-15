/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

import attachMediaStream from 'attachmediastream/attachmediastream.bundle.js'

import EmitterMixin from '../../EmitterMixin.js'

export const ConnectionState = {
	NEW: 'new',
	CHECKING: 'checking',
	CONNECTED: 'connected',
	COMPLETED: 'completed',
	DISCONNECTED: 'disconnected',
	DISCONNECTED_LONG: 'disconnected-long', // Talk specific
	FAILED: 'failed',
	FAILED_NO_RESTART: 'failed-no-restart', // Talk specific
	CLOSED: 'closed',
}

/**
 * @param {object} options The model
 * @param {string} options.peerId The peerId of the participant
 * @param {object} options.webRtc The WebRTC connection to the participant
 */
export default function CallParticipantModel(options) {

	this._superEmitterMixin()

	this.attributes = {
		peerId: null,
		nextcloudSessionId: null,
		peer: null,
		screenPeer: null,
		// "undefined" is used for values not known yet; "null" or "false"
		// are used for known but negative/empty values.
		userId: undefined,
		name: undefined,
		internal: undefined,
		connectionState: ConnectionState.NEW,
		negotiating: false,
		connecting: false,
		initialConnection: true,
		connectedAtLeastOnce: false,
		stream: null,
		// The audio element is part of the model to ensure that it can be
		// played if needed even if there is no view for it.
		audioElement: null,
		audioAvailable: undefined,
		speaking: undefined,
		// "videoBlocked" is "true" only if the video is blocked and it would
		// have been available in the remote peer if not blocked.
		videoBlocked: undefined,
		videoAvailable: undefined,
		screen: null,
		// The audio element is part of the model to ensure that it can be
		// played if needed even if there is no view for it.
		screenAudioElement: null,
		raisedHand: {
			state: false,
			timestamp: null,
		},
	}

	this.set('peerId', options.peerId)

	this._webRtc = options.webRtc

	this._handlePeerStreamAddedBound = this._handlePeerStreamAdded.bind(this)
	this._handlePeerStreamRemovedBound = this._handlePeerStreamRemoved.bind(this)
	this._handleNickBound = this._handleNick.bind(this)
	this._handleMuteBound = this._handleMute.bind(this)
	this._handleUnmuteBound = this._handleUnmute.bind(this)
	this._handleExtendedIceConnectionStateChangeBound = this._handleExtendedIceConnectionStateChange.bind(this)
	this._handleSignalingStateChangeBound = this._handleSignalingStateChange.bind(this)
	this._handleChannelMessageBound = this._handleChannelMessage.bind(this)
	this._handleRaisedHandBound = this._handleRaisedHand.bind(this)
	this._handleRemoteVideoBlockedBound = this._handleRemoteVideoBlocked.bind(this)
	this._handleReactionBound = this._handleReaction.bind(this)

	this._webRtc.on('peerStreamAdded', this._handlePeerStreamAddedBound)
	this._webRtc.on('peerStreamRemoved', this._handlePeerStreamRemovedBound)
	this._webRtc.on('nick', this._handleNickBound)
	this._webRtc.on('mute', this._handleMuteBound)
	this._webRtc.on('unmute', this._handleUnmuteBound)
	this._webRtc.on('channelMessage', this._handleChannelMessageBound)
	this._webRtc.on('raisedHand', this._handleRaisedHandBound)
	this._webRtc.on('reaction', this._handleReactionBound)
}

CallParticipantModel.prototype = {

	destroy() {
		if (this.get('peer')) {
			this.get('peer').off('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound)
			this.get('peer').off('signalingStateChange', this._handleSignalingStateChangeBound)
			this.get('peer').off('remoteVideoBlocked', this._handleRemoteVideoBlockedBound)
		}

		this._webRtc.off('peerStreamAdded', this._handlePeerStreamAddedBound)
		this._webRtc.off('peerStreamRemoved', this._handlePeerStreamRemovedBound)
		this._webRtc.off('nick', this._handleNickBound)
		this._webRtc.off('mute', this._handleMuteBound)
		this._webRtc.off('unmute', this._handleUnmuteBound)
		this._webRtc.off('channelMessage', this._handleChannelMessageBound)
		this._webRtc.off('raisedHand', this._handleRaisedHandBound)
		this._webRtc.off('reaction', this._handleReactionBound)
	},

	get(key) {
		return this.attributes[key]
	},

	set(key, value) {
		if (this.attributes[key] === value) {
			return
		}

		this.attributes[key] = value

		this._trigger('change:' + key, [value])
	},

	_handlePeerStreamAdded(peer) {
		if (this.get('peer') === peer) {
			this.set('stream', this.get('peer').stream || null)
			this.set('audioElement', attachMediaStream(this.get('stream'), null, { audio: true }))
			this.get('audioElement').muted = !this.get('audioAvailable')

			// "peer.nick" is set only for users and when the MCU is not used.
			if (this.get('peer').nick !== undefined) {
				this.set('name', this.get('peer').nick)
			}
		} else if (this.get('screenPeer') === peer) {
			this.set('screen', this.get('screenPeer').stream || null)
			this.set('screenAudioElement', attachMediaStream(this.get('screen'), null, { audio: true }))
		}
	},

	_handlePeerStreamRemoved(peer) {
		if (this.get('peer') === peer) {
			this.get('audioElement').srcObject = null
			this.set('audioElement', null)
			this.set('stream', null)
			this.set('audioAvailable', undefined)
			this.set('speaking', undefined)
			this.set('videoAvailable', undefined)
		} else if (this.get('screenPeer') === peer) {
			this.get('screenAudioElement').srcObject = null
			this.set('screenAudioElement', null)
			this.set('screen', null)
		}
	},

	_handleNick(data) {
		// The nick could be changed even if there is no Peer object.
		if (this.get('peerId') !== data.id) {
			return
		}

		this.set('name', data.name || null)
	},

	_handleMute(data) {
		if (!this.get('peer') || this.get('peer').id !== data.id) {
			return
		}

		if (data.name === 'video') {
			this.set('videoAvailable', false)
		} else {
			if (this.get('audioElement')) {
				this.get('audioElement').muted = true
			}
			this.set('audioAvailable', false)
			this.set('speaking', false)
		}
	},

	forceMute() {
		if (!this.get('peer')) {
			return
		}

		this._webRtc.sendToAll('control', {
			action: 'forceMute',
			peerId: this.get('peer').id,
		})

		// Mute locally too, as even when sending to all the sender will not
		// receive the message.
		this._handleMute({ id: this.get('peer').id })
	},

	_handleUnmute(data) {
		if (!this.get('peer') || this.get('peer').id !== data.id) {
			return
		}

		if (data.name === 'video') {
			this.set('videoAvailable', true)
		} else {
			if (this.get('audioElement')) {
				this.get('audioElement').muted = false
			}
			this.set('audioAvailable', true)
		}
	},

	_handleChannelMessage(peer, label, data) {
		if (!this.get('peer') || this.get('peer').id !== peer.id) {
			return
		}

		if (label !== 'status' && label !== 'JanusDataChannel') {
			return
		}

		if (data.type === 'speaking') {
			this.set('speaking', true)
		} else if (data.type === 'stoppedSpeaking') {
			this.set('speaking', false)
		}
	},

	_handleRaisedHand(data) {
		// The hand could be raised even if there is no Peer object.
		if (this.get('peerId') !== data.id) {
			return
		}

		this.set('raisedHand', data.raised)
	},

	setPeer(peer) {
		if (peer && this.get('peerId') !== peer.id) {
			console.warn('Mismatch between stored peer ID and ID of given peer: ', this.get('peerId'), peer.id)
		}

		if (this.get('peer')) {
			this.get('peer').off('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound)
			this.get('peer').off('signalingStateChange', this._handleSignalingStateChangeBound)
			this.get('peer').off('remoteVideoBlocked', this._handleRemoteVideoBlockedBound)
		}

		this.set('peer', peer)

		// Special case when the participant has no streams.
		if (!this.get('peer')) {
			this.set('connectionState', ConnectionState.COMPLETED)
			this.set('negotiating', false)
			this.set('connecting', false)
			this.set('audioAvailable', false)
			this.set('speaking', false)
			this.set('videoAvailable', false)
			this.set('videoBlocked', false)

			return
		}

		// Reset state that depends on the Peer object.
		if (this.get('peer').pc.connectionState === 'failed' && this.get('peer').pc.iceConnectionState === 'disconnected') {
			// Work around Chromium bug where "iceConnectionState" gets stuck as
			// "disconnected" even if the connection already failed.
			this._handleExtendedIceConnectionStateChange(this.get('peer').pc.connectionState)
		} else {
			this._handleExtendedIceConnectionStateChange(this.get('peer').pc.iceConnectionState)
		}
		this._handleSignalingStateChange(this.get('peer').pc.signalingState)
		this._handlePeerStreamAdded(this.get('peer'))
		this._handleRemoteVideoBlocked(undefined)

		this.get('peer').on('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound)
		this.get('peer').on('signalingStateChange', this._handleSignalingStateChangeBound)
		this.get('peer').on('remoteVideoBlocked', this._handleRemoteVideoBlockedBound)

		// Set expected state in Peer object.
		if (this._simulcastVideoQuality !== undefined) {
			this.setSimulcastVideoQuality(this._simulcastVideoQuality)
		}
		if (this._videoBlocked !== undefined) {
			this.setVideoBlocked(this._videoBlocked)
		}
	},

	_handleExtendedIceConnectionStateChange(extendedIceConnectionState) {
		// Ensure that the name is set, as when the MCU is not used it will
		// not be set later for registered users without microphone nor
		// camera.
		const setNameForUserFromPeerNick = function() {
			if (this.get('peer').nick !== undefined) {
				this.set('name', this.get('peer').nick)
			}
		}.bind(this)

		// "connecting" state is not changed when entering the "disconnected"
		// state, as it can be entered while still connecting (if done from
		// "checking") or once already connected (from "connected" or
		// "completed").

		switch (extendedIceConnectionState) {
		case 'new':
			this.set('connectionState', ConnectionState.NEW)
			this.set('connecting', true)
			this.set('audioAvailable', undefined)
			this.set('speaking', undefined)
			this.set('videoAvailable', undefined)
			break
		case 'checking':
			this.set('connectionState', ConnectionState.CHECKING)
			this.set('connecting', true)
			this.set('audioAvailable', undefined)
			this.set('speaking', undefined)
			this.set('videoAvailable', undefined)
			break
		case 'connected':
			this.set('connectionState', ConnectionState.CONNECTED)
			this.set('connecting', false)
			this.set('initialConnection', false)
			this.set('connectedAtLeastOnce', true)
			setNameForUserFromPeerNick()
			break
		case 'completed':
			this.set('connectionState', ConnectionState.COMPLETED)
			this.set('connecting', false)
			this.set('initialConnection', false)
			this.set('connectedAtLeastOnce', true)
			setNameForUserFromPeerNick()
			break
		case 'disconnected':
			this.set('connectionState', ConnectionState.DISCONNECTED)
			break
		case 'disconnected-long':
			this.set('connectionState', ConnectionState.DISCONNECTED_LONG)
			break
		case 'failed':
			this.set('connectionState', ConnectionState.FAILED)
			this.set('connecting', false)
			this.set('initialConnection', false)
			break
		case 'failed-no-restart':
			this.set('connectionState', ConnectionState.FAILED_NO_RESTART)
			this.set('connecting', false)
			this.set('initialConnection', false)
			break
		case 'closed':
			this.set('connectionState', ConnectionState.CLOSED)
			this.set('connecting', false)
			this.set('initialConnection', false)
			break
		default:
			console.error('Unexpected (extended) ICE connection state: ', extendedIceConnectionState)
		}
	},

	_handleSignalingStateChange(signalingState) {
		this.set('negotiating', signalingState !== 'stable' && signalingState !== 'closed')
	},

	setScreenPeer(screenPeer) {
		if (screenPeer && this.get('peerId') !== screenPeer.id) {
			console.warn('Mismatch between stored peer ID and ID of given screen peer: ', this.get('peerId'), screenPeer.id)
		}

		this.set('screenPeer', screenPeer)

		// Reset state that depends on the screen Peer object.
		this._handlePeerStreamAdded(this.get('screenPeer'))

		// Set expected state in screen Peer object.
		if (this._simulcastScreenQuality !== undefined) {
			this.setSimulcastScreenQuality(this._simulcastScreenQuality)
		}
	},

	setUserId(userId) {
		this.set('userId', userId)
	},

	setNextcloudSessionId(nextcloudSessionId) {
		this.set('nextcloudSessionId', nextcloudSessionId)
	},

	setVideoBlocked(videoBlocked) {
		// Store value to be able to apply it again if a new Peer object is set.
		this._videoBlocked = videoBlocked

		if (!this.get('peer')) {
			return
		}

		this.get('peer').setRemoteVideoBlocked(videoBlocked)
	},

	_handleRemoteVideoBlocked(remoteVideoBlocked) {
		this.set('videoBlocked', remoteVideoBlocked)
	},

	setSimulcastVideoQuality(simulcastVideoQuality) {
		// Store value to be able to apply it again if a new Peer object is set.
		this._simulcastVideoQuality = simulcastVideoQuality

		if (!this.get('peer') || !this.get('peer').enableSimulcast) {
			return
		}

		// Use same quality for simulcast and temporal layer.
		this.get('peer').selectSimulcastStream(simulcastVideoQuality, simulcastVideoQuality)
	},

	setSimulcastScreenQuality(simulcastScreenQuality) {
		// Store value to be able to apply it again if a new screen Peer object
		// is set.
		this._simulcastScreenQuality = simulcastScreenQuality

		if (!this.get('screenPeer') || !this.get('screenPeer').enableSimulcast) {
			return
		}

		// Use same quality for simulcast and temporal layer.
		this.get('screenPeer').selectSimulcastStream(simulcastScreenQuality, simulcastScreenQuality)
	},

	_handleReaction(data) {
		// A reaction could be sent even if there is no Peer object.
		if (this.get('peerId') !== data.id) {
			return
		}

		this._trigger('reaction', [data.reaction])
	},

}

EmitterMixin.apply(CallParticipantModel.prototype)
