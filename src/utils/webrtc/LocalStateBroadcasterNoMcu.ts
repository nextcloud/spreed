/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	CallParticipantCollection,
	CallParticipantModel,
	WebRtc,
} from '../../types/index.ts'

import { LocalStateBroadcaster } from './LocalStateBroadcaster.ts'
import { ConnectionState } from './models/CallParticipantModel.js'

/**
 * Helper class to send the local participant state to the other participants in
 * the call when an MCU is not used.
 *
 * Sending the state when it changes is handled by the base class; this subclass
 * only handles sending the initial state when a remote participant is added.
 *
 * The state is sent when a connection with another participant is first
 * established (which implicitly broadcasts the initial state when the local
 * participant joins the call and is publishing media, as a connection will be
 * established with all the remote participants). On the other hand, if no
 * connection will be established with the other participant (because neither
 * the local nor the remote participant is publishing audio nor video) the media
 * state is not sent (as both audio and video of the local participant are
 * implicitly disabled from the point of view of the remote participant).
 *
 * Regarding the state sent when a connection with another participant is first
 * established, note that, as long as that participant stays in the call, the
 * initial state is not sent again, even after a temporary disconnection; data
 * channels use a reliable transport by default, so even if the state changes
 * while the connection is temporarily interrupted the normal state update
 * messages should be received by the other participant once the connection is
 * restored.
 *
 * Nevertheless, in case of a failed connection and an ICE restart it is unclear
 * whether the data channel messages would be received or not (as the data
 * channel transport may be the one that failed and needs to be restarted).
 * However, the state (except the speaking state) is also sent through signaling
 * messages, which need to be explicitly fetched from the internal signaling
 * server, so even in case of a failed connection they will be eventually
 * received once the remote participant connects again.
 */
export class LocalStateBroadcasterNoMcu extends LocalStateBroadcaster {
	private _callParticipantModels: Map<string, CallParticipantModel>

	private _handleConnectionStateBound: (callParticipantModel: CallParticipantModel, connectionState: string) => void

	public constructor(webRtc: WebRtc, callParticipantCollection: CallParticipantCollection) {
		super(webRtc, callParticipantCollection)

		this._callParticipantModels = new Map()

		this._handleConnectionStateBound = this._handleConnectionState.bind(this)
	}

	public destroy(): void {
		super.destroy()

		this._callParticipantModels.forEach((callParticipantModel) => {
			callParticipantModel.off('change:connectionState', this._handleConnectionStateBound)
		})
	}

	protected _handleAddCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void {
		this._callParticipantModels.set(callParticipantModel.get('peerId') as string, callParticipantModel)

		callParticipantModel.on('change:connectionState', this._handleConnectionStateBound)
	}

	protected _handleRemoveCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void {
		this._callParticipantModels.delete(callParticipantModel.get('peerId') as string)

		callParticipantModel.off('change:connectionState', this._handleConnectionStateBound)
	}

	private _handleConnectionState(callParticipantModel: CallParticipantModel, connectionState: string): void {
		if (connectionState === ConnectionState.CONNECTED
			|| connectionState === ConnectionState.COMPLETED) {
			this._sendCurrentMediaStateTo(callParticipantModel.get('peerId') as string)

			callParticipantModel.off('change:connectionState', this._handleConnectionStateBound)
		}
	}

	private _sendCurrentMediaStateTo(peerId: string): void {
		if (!this._webRtc.webrtc.isAudioEnabled()) {
			this._webRtc.sendDataChannelTo(peerId, 'status', 'audioOff')
			this._webRtc.sendTo(peerId, 'mute', { name: 'audio' })
		} else {
			this._webRtc.sendDataChannelTo(peerId, 'status', 'audioOn')
			this._webRtc.sendTo(peerId, 'unmute', { name: 'audio' })

			if (!this._webRtc.webrtc.isSpeaking()) {
				this._webRtc.sendDataChannelTo(peerId, 'status', 'stoppedSpeaking')
			} else {
				this._webRtc.sendDataChannelTo(peerId, 'status', 'speaking')
			}
		}

		if (!this._webRtc.webrtc.isVideoEnabled()) {
			this._webRtc.sendDataChannelTo(peerId, 'status', 'videoOff')
			this._webRtc.sendTo(peerId, 'mute', { name: 'video' })
		} else {
			this._webRtc.sendDataChannelTo(peerId, 'status', 'videoOn')
			this._webRtc.sendTo(peerId, 'unmute', { name: 'video' })
		}
	}
}
