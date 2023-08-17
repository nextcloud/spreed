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

import store from '../../../store/index.js'
import EmitterMixin from '../../EmitterMixin.js'
import { ConnectionState } from './CallParticipantModel.js'

/**
 *
 */
export default function LocalCallParticipantModel() {

	this._superEmitterMixin()

	this.attributes = {
		peerId: null,
		peer: null,
		screenPeer: null,
		guestName: null,
		peerNeeded: false,
		connectionState: null,
	}

	this._handleForcedMuteBound = this._handleForcedMute.bind(this)
	this._handleExtendedIceConnectionStateChangeBound = this._handleExtendedIceConnectionStateChange.bind(this)

}

LocalCallParticipantModel.prototype = {

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

	setWebRtc(webRtc) {
		if (this._webRtc) {
			this._webRtc.off('forcedMute', this._handleForcedMuteBound)
			this._unwatchDisplayNameChange()
		}

		this._webRtc = webRtc

		this.set('peerId', this._webRtc.connection.getSessionId())
		this.set('guestName', null)

		this._webRtc.on('forcedMute', this._handleForcedMuteBound)
		this._unwatchDisplayNameChange = store.watch(state => state.actorStore.displayName, this.setGuestName.bind(this))
	},

	setPeerId(peerId) {
		this.set('peerId', peerId)
	},

	setPeer(peer) {
		if (peer && this.get('peerId') !== peer.id) {
			console.warn('Mismatch between stored peer ID and ID of given peer: ', this.get('peerId'), peer.id)
		}

		if (this.get('peer')) {
			this.get('peer').off('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound)
		}

		this.set('peer', peer)

		if (!this.get('peer')) {
			this.set('connectionState', null)

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

		this.get('peer').on('extendedIceConnectionStateChange', this._handleExtendedIceConnectionStateChangeBound)
	},

	setScreenPeer(screenPeer) {
		if (screenPeer && this.get('peerId') !== screenPeer.id) {
			console.warn('Mismatch between stored peer ID and ID of given screen peer: ', this.get('peerId'), screenPeer.id)
		}

		this.set('screenPeer', screenPeer)
	},

	setGuestName(guestName) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		this.set('guestName', guestName)

		this._webRtc.webrtc.emit('nickChanged', guestName)
	},

	setPeerNeeded(peerNeeded) {
		this.set('peerNeeded', peerNeeded)
	},

	_handleForcedMute() {
		this._trigger('forcedMute')
	},

	_handleExtendedIceConnectionStateChange(extendedIceConnectionState) {
		switch (extendedIceConnectionState) {
		case 'new':
			this.set('connectionState', ConnectionState.NEW)
			break
		case 'checking':
			this.set('connectionState', ConnectionState.CHECKING)
			break
		case 'connected':
			this.set('connectionState', ConnectionState.CONNECTED)
			break
		case 'completed':
			this.set('connectionState', ConnectionState.COMPLETED)
			break
		case 'disconnected':
			this.set('connectionState', ConnectionState.DISCONNECTED)
			break
		case 'disconnected-long':
			this.set('connectionState', ConnectionState.DISCONNECTED_LONG)
			break
		case 'failed':
			this.set('connectionState', ConnectionState.FAILED)
			break
		// 'failed-no-restart' is not emitted by own peer
		case 'closed':
			this.set('connectionState', ConnectionState.CLOSED)
			break
		default:
			console.error('Unexpected (extended) ICE connection state: ', extendedIceConnectionState)
		}
	},

	sendReaction(reaction) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		this._webRtc.sendToAll('reaction', {
			reaction,
		})
	},

}

EmitterMixin.apply(LocalCallParticipantModel.prototype)
