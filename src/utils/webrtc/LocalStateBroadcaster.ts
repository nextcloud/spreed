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

import { ConnectionState } from './models/CallParticipantModel.js'

/**
 * Helper class to send the local participant state to the other participants in
 * the call.
 *
 * Once created, and until destroyed, the LocalStateBroadcaster will send the
 * changes in the local participant state to all the participants in the call.
 * Note that the LocalStateBroadcaster does not check whether the local
 * participant is actually in the call or not; it is expected that the
 * LocalStateBroadcaster will be created and destroyed when the local
 * participant joins and leaves the call.
 *
 * The LocalStateBroadcaster also sends the current state to remote participants
 * when they join (which implicitly sends it to all remote participants when the
 * local participant joins the call) so they can set an initial state for the
 * local participant.
 */
export abstract class LocalStateBroadcaster {
	protected _webRtc: WebRtc
	private _callParticipantCollection: CallParticipantCollection
	protected _localCallParticipantModel: LocalCallParticipantModel

	private _handleAudioOnBound: () => void
	private _handleAudioOffBound: () => void
	private _handleSpeakingBound: () => void
	private _handleStoppedSpeakingBound: () => void
	private _handleVideoOnBound: () => void
	private _handleVideoOffBound: () => void
	private _handleChangeGuestNameBound: (localCallParticipantModel: LocalCallParticipantModel, guestName: string) => void

	private _handleAddCallParticipantModelBound: (callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel) => void
	private _handleRemoveCallParticipantModelBound: (callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel) => void

	public constructor(webRtc: WebRtc, callParticipantCollection: CallParticipantCollection, localCallParticipantModel: LocalCallParticipantModel) {
		this._webRtc = webRtc
		this._callParticipantCollection = callParticipantCollection
		this._localCallParticipantModel = localCallParticipantModel

		this._handleAudioOnBound = this._handleAudioOn.bind(this)
		this._handleAudioOffBound = this._handleAudioOff.bind(this)
		this._handleSpeakingBound = this._handleSpeaking.bind(this)
		this._handleStoppedSpeakingBound = this._handleStoppedSpeaking.bind(this)
		this._handleVideoOnBound = this._handleVideoOn.bind(this)
		this._handleVideoOffBound = this._handleVideoOff.bind(this)
		this._handleChangeGuestNameBound = this._handleChangeGuestName.bind(this)

		this._handleAddCallParticipantModelBound = this._handleAddCallParticipantModel.bind(this)
		this._handleRemoveCallParticipantModelBound = this._handleRemoveCallParticipantModel.bind(this)

		this._webRtc.on('audioOn', this._handleAudioOnBound)
		this._webRtc.on('audioOff', this._handleAudioOffBound)
		this._webRtc.on('speaking', this._handleSpeakingBound)
		this._webRtc.on('stoppedSpeaking', this._handleStoppedSpeakingBound)
		this._webRtc.on('videoOn', this._handleVideoOnBound)
		this._webRtc.on('videoOff', this._handleVideoOffBound)

		this._localCallParticipantModel.on('change:guestName', this._handleChangeGuestNameBound)

		this._callParticipantCollection.on('add', this._handleAddCallParticipantModelBound)
		this._callParticipantCollection.on('remove', this._handleRemoveCallParticipantModelBound)
	}

	public destroy(): void {
		this._webRtc.off('audioOn', this._handleAudioOnBound)
		this._webRtc.off('audioOff', this._handleAudioOffBound)
		this._webRtc.off('speaking', this._handleSpeakingBound)
		this._webRtc.off('stoppedSpeaking', this._handleStoppedSpeakingBound)
		this._webRtc.off('videoOn', this._handleVideoOnBound)
		this._webRtc.off('videoOff', this._handleVideoOffBound)

		this._localCallParticipantModel.off('change:guestName', this._handleChangeGuestNameBound)

		this._callParticipantCollection.off('add', this._handleAddCallParticipantModelBound)
		this._callParticipantCollection.off('remove', this._handleRemoveCallParticipantModelBound)
	}

	protected abstract _handleAddCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void
	protected abstract _handleRemoveCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void

	private _handleAudioOn(): void {
		this._webRtc.sendDataChannelToAll('status', 'audioOn')

		this._webRtc.sendToAll('unmute', { name: 'audio' })
	}

	private _handleAudioOff(): void {
		this._webRtc.sendDataChannelToAll('status', 'audioOff')

		this._webRtc.sendToAll('mute', { name: 'audio' })
	}

	private _handleSpeaking(): void {
		this._webRtc.sendDataChannelToAll('status', 'speaking')
	}

	private _handleStoppedSpeaking(): void {
		this._webRtc.sendDataChannelToAll('status', 'stoppedSpeaking')
	}

	private _handleVideoOn(): void {
		this._webRtc.sendDataChannelToAll('status', 'videoOn')

		this._webRtc.sendToAll('unmute', { name: 'video' })
	}

	private _handleVideoOff(): void {
		this._webRtc.sendDataChannelToAll('status', 'videoOff')

		this._webRtc.sendToAll('mute', { name: 'video' })
	}

	private _handleChangeGuestName(localCallParticipantModel: LocalCallParticipantModel, guestName: string): void {
		this._webRtc.sendDataChannelToAll('status', 'nickChanged', this._getNickChangedDataChannelMessagePayload(guestName))

		this._webRtc.sendToAll('nickChanged', { name: guestName })
	}

	protected _getNickChangedDataChannelMessagePayload(name: string): string | object {
		if (this._webRtc.connection.settings.userId === null) {
			return name
		}

		return {
			name,
			userid: this._webRtc.connection.settings.userId,
		}
	}
}

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
 * several times with an increasing delay whenever a participant joins the call
 * (which implicitly broadcasts the initial state when the local participant
 * joins the call, as all the remote participants joined from the point of view
 * of the local participant). If the state was already being sent the sending is
 * restarted with each new participant that joins.
 *
 * Similarly, in the case of signaling messages it is not possible either to
 * know when the remote participants have "seen" the local participant and thus
 * are ready to handle signaling messages about the state. However, in the case
 * of signaling messages it is possible to send them to a specific participant,
 * so the initial state is sent several times with an increasing delay directly
 * to the participant that was added. Moreover, if the participant is removed
 * the state is no longer directly sent.
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
			this._sendCurrentStateToAll()
		})

		const peerId = callParticipantModel.get('peerId') as string

		this._sendStateWithRepetitionToParticipant.get(peerId)?.destroy()

		this._sendStateWithRepetitionToParticipant.set(peerId, new ExponentialBackoffCallback(() => {
			this._sendCurrentStateTo(peerId)
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

	private _sendCurrentStateToAll(): void {
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

		const name = this._localCallParticipantModel.get('guestName')
		this._webRtc.sendDataChannelToAll('status', 'nickChanged', this._getNickChangedDataChannelMessagePayload(name))
	}

	private _sendCurrentStateTo(peerId: string): void {
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

		const name = this._localCallParticipantModel.get('guestName')
		this._webRtc.sendTo(peerId, 'nickChanged', { name })
	}
}

/**
 * Helper class to send the local participant state to the other participants in
 * the call when an MCU is not used.
 *
 * Sending the state when it changes is handled by the base class; this subclass
 * only handles sending the initial state when a remote participant is added.
 *
 * The state is sent when a connection with another participant is first
 * established (which implicitly broadcasts the initial state when the local
 * participant joins the call, as a connection is established with all the
 * remote participants). Note that, as long as that participant stays in the
 * call, the initial state is not sent again, even after a temporary
 * disconnection; data channels use a reliable transport by default, so even if
 * the state changes while the connection is temporarily interrupted the normal
 * state update messages should be received by the other participant once the
 * connection is restored.
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
	private _handlePeerBound: (callParticipantModel: CallParticipantModel, peer?: object) => void

	public constructor(webRtc: WebRtc, callParticipantCollection: CallParticipantCollection, localCallParticipantModel: LocalCallParticipantModel) {
		super(webRtc, callParticipantCollection, localCallParticipantModel)

		this._callParticipantModels = new Map()

		this._handleConnectionStateBound = this._handleConnectionState.bind(this)
		this._handlePeerBound = this._handlePeer.bind(this)
	}

	public destroy(): void {
		super.destroy()

		this._callParticipantModels.forEach((callParticipantModel) => {
			callParticipantModel.off('change:connectionState', this._handleConnectionStateBound)
			callParticipantModel.off('change:peer', this._handlePeerBound)
		})
	}

	protected _handleAddCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void {
		this._callParticipantModels.set(callParticipantModel.get('peerId') as string, callParticipantModel)

		callParticipantModel.on('change:connectionState', this._handleConnectionStateBound)
		callParticipantModel.on('change:peer', this._handlePeerBound)
	}

	protected _handleRemoveCallParticipantModel(callParticipantCollection: CallParticipantCollection, callParticipantModel: CallParticipantModel): void {
		this._callParticipantModels.delete(callParticipantModel.get('peerId') as string)

		callParticipantModel.off('change:connectionState', this._handleConnectionStateBound)
		callParticipantModel.off('change:peer', this._handlePeerBound)
	}

	private _handleConnectionState(callParticipantModel: CallParticipantModel, connectionState: string): void {
		if (connectionState === ConnectionState.CONNECTED
			|| connectionState === ConnectionState.COMPLETED) {
			this._sendCurrentMediaStateTo(callParticipantModel.get('peerId') as string)

			callParticipantModel.off('change:connectionState', this._handleConnectionStateBound)
			callParticipantModel.off('change:peer', this._handlePeerBound)
		}
	}

	private _handlePeer(callParticipantModel: CallParticipantModel, peer?: object): void {
		if (peer !== null) {
			return
		}

		this._sendCurrentNameTo(callParticipantModel.get('peerId'))
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

	private _sendCurrentNameTo(peerId: string): void {
		const name = this._localCallParticipantModel.get('guestName')

		this._webRtc.sendDataChannelTo(peerId, 'status', 'nickChanged', this._getNickChangedDataChannelMessagePayload(name))
		this._webRtc.sendTo(peerId, 'nickChanged', { name })
	}
}
