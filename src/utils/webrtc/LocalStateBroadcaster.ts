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
 * The LocalStateBroadcaster (or, rather, its subclasses) also sends the current
 * state to remote participants as needed when a participant joins (including
 * when the local participant joins) so the remote participants can set an
 * initial state for the local participant.
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
	private _handleChangeNameBound: (localCallParticipantModel: LocalCallParticipantModel, name: string) => void

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
		this._handleChangeNameBound = this._handleChangeName.bind(this)

		this._handleAddCallParticipantModelBound = this._handleAddCallParticipantModel.bind(this)
		this._handleRemoveCallParticipantModelBound = this._handleRemoveCallParticipantModel.bind(this)

		this._webRtc.on('audioOn', this._handleAudioOnBound)
		this._webRtc.on('audioOff', this._handleAudioOffBound)
		this._webRtc.on('speaking', this._handleSpeakingBound)
		this._webRtc.on('stoppedSpeaking', this._handleStoppedSpeakingBound)
		this._webRtc.on('videoOn', this._handleVideoOnBound)
		this._webRtc.on('videoOff', this._handleVideoOffBound)

		this._localCallParticipantModel.on('change:name', this._handleChangeNameBound)

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

		this._localCallParticipantModel.off('change:name', this._handleChangeNameBound)

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

	private _handleChangeName(localCallParticipantModel: LocalCallParticipantModel, name: string): void {
		this._webRtc.sendDataChannelToAll('status', 'nickChanged', this._getNickChangedDataChannelMessagePayload(name))

		this._webRtc.sendToAll('nickChanged', { name })
	}

	/**
	 * Returns the paylod of the "nickChanged" data channel message for the
	 * given name.
	 *
	 * Due to historical reasons the payload of the data channel message is
	 * either a string that contains the name (if the participant is a guest) or
	 * an object with "name" and "userid" string fields (when the participant is
	 * a user).
	 *
	 * @param name string the name.
	 * @return string|object the data channel message payload.
	 */
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
