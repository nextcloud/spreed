/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	CallParticipantCollection,
	CallParticipantModel,
	LocalCallParticipantModel,
	WebRtc,
} from '../../types/index.ts'

import { LocalStateBroadcaster } from './LocalStateBroadcaster.ts'
import { ConnectionState } from './models/CallParticipantModel.js'

/**
 * Helper class to run a callback with an exponential backoff.
 *
 * The callback is run after 0, 1, 2, 4, 8 and 16 seconds. Running the callback
 * can be aborted by calling "destroy()".
 */
class ExponentialBackoffCallback {
	private _timeout?: ReturnType<typeof setTimeout> | null | undefined
	private _callback: () => void

	constructor(callback: () => void) {
		this._callback = callback

		this._runCallbackWithRepetition()
	}

	destroy(): void {
		if (this._timeout) {
			clearTimeout(this._timeout)
		}

		this._timeout = null
	}

	private _runCallbackWithRepetition(timeout?: number): void {
		if (!timeout) {
			timeout = 0
		}

		this._timeout = setTimeout(() => {
			this._callback()

			if (!timeout) {
				timeout = 1000
			} else {
				timeout *= 2
			}

			if (timeout > 16000) {
				this._timeout = null
				return
			}

			this._runCallbackWithRepetition(timeout)
		}, timeout)
	}
}

/**
 * Helper class to send the local participant state to the other participants in
 * the call when an MCU is used.
 *
 * Sending the state when it changes is handled by the base class; this subclass
 * only handles sending the initial state when a remote participant is added.
 *
 * When Janus is used data channel messages are sent to all remote participants
 * (with a peer connection to receive from the local participant). Moreover, it
 * is not possible to know when the remote participants open the data channel to
 * receive the messages, or even when they establish the receiver connection; it
 * is only possible to know when the data channel is open for the publisher
 * connection of the local participant. Due to all that the state is sent
 * several times with an increasing delay whenever a participant joins the call.
 * If the state was already being sent the sending is restarted with each new
 * participant that joins.
 *
 * Similarly, in the case of signaling messages it is not possible either to
 * know when the remote participants have "seen" the local participant and thus
 * are ready to handle signaling messages about the state. However, in the case
 * of signaling messages it is possible to send them to a specific participant,
 * so the current state is sent several times with an increasing delay directly
 * to the participant that joined. Moreover, if the participant leaves the state
 * is no longer directly sent.
 *
 * In any case, note that the state is sent only when the remote participant
 * joins the call. Even in case of temporary disconnections the normal state
 * updates sent when the state changes are expected to be received by the
 * other participant, as signaling messages are sent through a WebSocket and are
 * therefore reliable. Moreover, even if the WebSocket is restarted and the
 * connection resumed (rather than joining with a new session ID) the messages
 * would be also received, as in that case they would be queued until the
 * WebSocket is connected again.
 *
 * Data channel messages, on the other hand, could be lost if the remote
 * participant restarts the peer receiver connection (although they would be
 * received in case of temporary disconnections, as data channels use a reliable
 * transport by default). Therefore, as the speaking state is sent only through
 * data channels, updates of the speaking state could be not received by remote
 * participants.
 */
export class LocalStateBroadcasterMcu extends LocalStateBroadcaster {
	private _sendStateWithRepetition?: ExponentialBackoffCallback | null
	private _sendStateWithRepetitionToParticipant: Map<string, ExponentialBackoffCallback>

	public constructor(webRtc: WebRtc, callParticipantCollection: CallParticipantCollection, localCallParticipantModel: LocalCallParticipantModel) {
		super(webRtc, callParticipantCollection, localCallParticipantModel)

		this._sendStateWithRepetition = null
		this._sendStateWithRepetitionToParticipant = new Map()
	}

	public destroy(): void {
		super.destroy()

		this._sendStateWithRepetition?.destroy()

		this._sendStateWithRepetitionToParticipant.forEach((sendStateWithRepetitionToParticipant) => {
			sendStateWithRepetitionToParticipant.destroy()
		})
	}

	protected _handleAddCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void {
		this._sendStateWithRepetition?.destroy()

		this._sendStateWithRepetition = new ExponentialBackoffCallback(() => {
			this._sendCurrentMediaStateToAll()
		})

		const peerId = callParticipantModel.get('peerId') as string

		this._sendStateWithRepetitionToParticipant.get(peerId)?.destroy()

		this._sendStateWithRepetitionToParticipant.set(peerId, new ExponentialBackoffCallback(() => {
			this._sendCurrentMediaStateTo(peerId)
		}))
	}

	protected _handleRemoveCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void {
		if (callParticipantCollection.callParticipantModels.length === 0 && this._sendStateWithRepetition) {
			this._sendStateWithRepetition.destroy()
			this._sendStateWithRepetition = null
		}

		const peerId = callParticipantModel.get('peerId') as string

		this._sendStateWithRepetitionToParticipant.get(peerId)?.destroy()

		this._sendStateWithRepetitionToParticipant.delete(peerId)
	}

	private _sendCurrentMediaStateToAll(): void {
		if (!this._webRtc.webrtc.isAudioEnabled()) {
			this._webRtc.sendDataChannelToAll('status', 'audioOff')
		} else {
			this._webRtc.sendDataChannelToAll('status', 'audioOn')

			if (!this._webRtc.webrtc.isSpeaking()) {
				this._webRtc.sendDataChannelToAll('status', 'stoppedSpeaking')
			} else {
				this._webRtc.sendDataChannelToAll('status', 'speaking')
			}
		}

		if (!this._webRtc.webrtc.isVideoEnabled()) {
			this._webRtc.sendDataChannelToAll('status', 'videoOff')
		} else {
			this._webRtc.sendDataChannelToAll('status', 'videoOn')
		}
	}

	private _sendCurrentMediaStateTo(peerId: string): void {
		if (!this._webRtc.webrtc.isAudioEnabled()) {
			this._webRtc.sendTo(peerId, 'mute', { name: 'audio' })
		} else {
			this._webRtc.sendTo(peerId, 'unmute', { name: 'audio' })
		}

		if (!this._webRtc.webrtc.isVideoEnabled()) {
			this._webRtc.sendTo(peerId, 'mute', { name: 'video' })
		} else {
			this._webRtc.sendTo(peerId, 'unmute', { name: 'video' })
		}
	}
}
