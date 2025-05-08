/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import attachMediaStream from '../attachmediastream.js'
import { mediaDevicesManager } from '../webrtc/index.js'

/**
 * Player for audio of call participants.
 *
 * The player keeps track of the participants added and removed to the
 * CallParticipantCollection and plays their audio as needed. Note that in the
 * case of regular audio whether the audio is muted or not depends on
 * "audioAvailable"; screen share audio, on the other hand, is always treated as
 * unmuted.
 *
 * By default, the audio or screen audio of each participant is played on its
 * own audio element. Alternatively, the audio of all participants can be mixed
 * and played by a single element created when the player is created by setting
 * "mixAudio = true" in the constructor.
 *
 * Once the player is no longer needed "destroy()" must be called to stop
 * tracking the participants and playing audio.
 *
 * @param {object} callParticipantCollection the CallParticipantCollection.
 * @param {boolean} mixAudio true to mix and play all audio in a single audio
 *        element, false to play each audio on its own audio element.
 */
export default function CallParticipantsAudioPlayer(callParticipantCollection, mixAudio = false) {
	this._callParticipantCollection = callParticipantCollection

	this._mixAudio = mixAudio

	if (this._mixAudio) {
		this._audioContext = new (window.AudioContext || window.webkitAudioContext)()
		this._audioDestination = this._audioContext.createMediaStreamDestination()
		this._audioElement = attachMediaStream(this._audioDestination.stream, null, { audio: true })
		this._audioNodes = new Map()
	} else {
		this._audioElements = new Map()
	}
	this.setGeneralAudioOutput(mediaDevicesManager.attributes.audioOutputId)

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

		if (this._mixAudio) {
			this._audioElement.srcObject = null
			this._audioContext.close()
		}
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
		if (this._mixAudio) {
			this._setAudioNode(id, stream, mute)
		} else {
			this._setAudioElement(id, stream, mute)
		}
	},

	_handleScreenChanged(callParticipantModel, screen) {
		const id = callParticipantModel.get('peerId') + '-screen'
		if (this._mixAudio) {
			this._setAudioNode(id, screen)
		} else {
			this._setAudioElement(id, screen)
		}
	},

	_setAudioNode(id, stream, mute = false) {
		const audioNode = this._audioNodes.get(id)
		if (audioNode) {
			if (audioNode.connected) {
				audioNode.audioSource.disconnect(this._audioDestination)
			}

			this._audioNodes.delete(id)
		}

		if (!stream) {
			return
		}

		const audioSource = this._audioContext.createMediaStreamSource(stream)
		if (!mute) {
			audioSource.connect(this._audioDestination)
		}

		this._audioNodes.set(id, { audioSource, connected: !mute })
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
		this._setAudioElementOutput(mediaDevicesManager.attributes.audioOutputId, audioElement)

		if (mute) {
			audioElement.muted = true
		}

		this._audioElements.set(id, audioElement)
	},

	async setGeneralAudioOutput(deviceId) {
		if (!mediaDevicesManager.isAudioOutputSelectSupported) {
			console.debug('Your browser does not support audio output selecting')
			return
		}

		const promises = []
		for (const audioElement of this._audioElements.values()) {
			promises.push(this._setAudioElementOutput(deviceId, audioElement))
		}
		await Promise.all(promises)
	},

	async _setAudioElementOutput(deviceId, audioElement = null) {
		if (audioElement instanceof HTMLAudioElement) {
			await audioElement.setSinkId(deviceId)
			console.debug('Set audio output to %s', deviceId)
		}
	},

	_handleAudioAvailableChanged(callParticipantModel, audioAvailable) {
		if (this._mixAudio) {
			const audioNode = this._audioNodes.get(callParticipantModel.get('peerId') + '-stream')
			if (!audioNode) {
				return
			}

			if (audioAvailable && !audioNode.connected) {
				audioNode.audioSource.connect(this._audioDestination)
				audioNode.connected = true
			} else if (!audioAvailable && audioNode.connected) {
				audioNode.audioSource.disconnect(this._audioDestination)
				audioNode.connected = false

				// Force creating a new audio renderer to work around broken
				// audio output in Safari after disconnecting a node.
				this._audioElement.srcObject = this._audioDestination.stream
			}

			return
		}

		const audioElement = this._audioElements.get(callParticipantModel.get('peerId') + '-stream')
		if (!audioElement) {
			return
		}

		audioElement.muted = !audioAvailable
	},

}
