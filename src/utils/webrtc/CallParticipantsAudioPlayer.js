/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import attachMediaStream from '../attachmediastream.js'

/**
 * Player for audio of call participants.
 *
 * The player keeps track of the participants added and removed to the
 * CallParticipantCollection and plays their audio as needed. Note that in the
 * case of regular audio whether the audio is muted or not depends on
 * "audioAvailable"; screen share audio, on the other hand, is always treated as
 * unmuted.
 *
 * The audio or screen audio of each participant is played on its own audio
 * element.
 *
 * Once the player is no longer needed "destroy()" must be called to stop
 * tracking the participants and playing audio.
 *
 * @param {object} callParticipantCollection the CallParticipantCollection.
 */
export default function CallParticipantsAudioPlayer(callParticipantCollection) {
	this._callParticipantCollection = callParticipantCollection

	this._audioElements = new Map()

	this._handleCallParticipantAddedBound = this._handleCallParticipantAdded.bind(this)
	this._handleCallParticipantRemovedBound = this._handleCallParticipantRemoved.bind(this)
	this._handleStreamChangedBound = this._handleStreamChanged.bind(this)
	this._handleScreenChangedBound = this._handleScreenChanged.bind(this)
	this._handleAudioAvailableChangedBound = this._handleAudioAvailableChanged.bind(this)

	this._callParticipantCollection.on('add', this._handleCallParticipantAddedBound)
	this._callParticipantCollection.on('remove', this._handleCallParticipantRemovedBound)

	this._callParticipantCollection.callParticipantModels.value.forEach(callParticipantModel => {
		this._handleCallParticipantAddedBound(this._callParticipantCollection, callParticipantModel)
	})
}

CallParticipantsAudioPlayer.prototype = {

	destroy() {
		this._callParticipantCollection.off('add', this._handleCallParticipantAddedBound)
		this._callParticipantCollection.off('remove', this._handleCallParticipantRemovedBound)

		this._callParticipantCollection.callParticipantModels.value.forEach(callParticipantModel => {
			this._handleCallParticipantRemovedBound(this._callParticipantCollection, callParticipantModel)
		})
	},

	_handleCallParticipantAdded(callParticipantCollection, callParticipantModel) {
		callParticipantModel.on('change:stream', this._handleStreamChangedBound)
		callParticipantModel.on('change:screen', this._handleScreenChangedBound)
		callParticipantModel.on('change:audioAvailable', this._handleAudioAvailableChangedBound)

		this._handleStreamChangedBound(callParticipantModel, callParticipantModel.get('stream'))
		this._handleScreenChangedBound(callParticipantModel, callParticipantModel.get('screen'))
	},

	_handleCallParticipantRemoved(callParticipantCollection, callParticipantModel) {
		callParticipantModel.off('change:stream', this._handleStreamChangedBound)
		callParticipantModel.off('change:screen', this._handleScreenChangedBound)
		callParticipantModel.off('change:audioAvailable', this._handleAudioAvailableChangedBound)

		this._handleStreamChangedBound(callParticipantModel, null)
		this._handleScreenChangedBound(callParticipantModel, null)
	},

	_handleStreamChanged(callParticipantModel, stream) {
		const id = callParticipantModel.get('peerId') + '-stream'
		const mute = !callParticipantModel.get('audioAvailable')
		this._setAudioElement(id, stream, mute)
	},

	_handleScreenChanged(callParticipantModel, screen) {
		const id = callParticipantModel.get('peerId') + '-screen'
		this._setAudioElement(id, screen)
	},

	_setAudioElement(id, stream, mute = false) {
		let audioElement = this._audioElements.get(id)
		if (audioElement) {
			audioElement.srcObject = null

			this._audioElements.delete(id)
		}

		if (!stream) {
			return
		}

		audioElement = attachMediaStream(stream, null, { audio: true })
		if (mute) {
			audioElement.muted = true
		}

		this._audioElements.set(id, audioElement)
	},

	_handleAudioAvailableChanged(callParticipantModel, audioAvailable) {
		const audioElement = this._audioElements.get(callParticipantModel.get('peerId') + '-stream')
		if (!audioElement) {
			return
		}

		audioElement.muted = !audioAvailable
	},

}
