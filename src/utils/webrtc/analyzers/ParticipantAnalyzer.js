/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	PEER_DIRECTION,
	PEER_TYPE,
	PeerConnectionAnalyzer
} from './PeerConnectionAnalyzer.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 * Analyzer for the quality of the connections of a Participant.
 *
 * After a ParticipantAnalyzer is created the participant to analyze must be set
 * using any of the "setXXXParticipant" methods; "setSenderReceiverParticipant"
 * is meant to be used when there is no HPB, while "setSenderParticipant" and
 * "setReceiverParticipant" are meant to be used when there is an HPB for the
 * local and remote participants respectively.
 *
 * When the quality of the connections change different events will be triggered
 * depending on the case:
 * - 'change:senderConnectionQualityAudio'
 * - 'change:senderConnectionQualityVideo'
 * - 'change:senderConnectionQualityScreen'
 * - 'change:receiverConnectionQualityAudio'
 * - 'change:receiverConnectionQualityVideo'
 * - 'change:receiverConnectionQualityScreen'
 *
 * The reported values are based on CONNECTION_QUALITY values of
 * PeerConnectionAnalyzer.
 *
 * Note that the connections will be analyzed only when the corresponding media
 * is enabled so, for example, if a sender participant has muted but still has
 * the video enabled only the video quality will be analyzed until the audio is
 * unmuted again. This is done not only because the connection quality of media
 * is less relevant when the media is disabled, but also because the connection
 * stats provided by the browser and used for the analysis are less reliable in
 * that case.
 *
 * Once the ParticipantAnalyzer is no longer needed "destroy()" must be called
 * to stop the analysis.
 */
function ParticipantAnalyzer() {
	this._superEmitterMixin()

	this._localMediaModel = null
	this._localCallParticipantModel = null
	this._callParticipantModel = null

	this._peer = null
	this._screenPeer = null

	this._senderPeerConnectionAnalyzer = null
	this._receiverPeerConnectionAnalyzer = null
	this._senderScreenPeerConnectionAnalyzer = null
	this._receiverScreenPeerConnectionAnalyzer = null

	this._handlePeerChangeBound = this._handlePeerChange.bind(this)
	this._handleScreenPeerChangeBound = this._handleScreenPeerChange.bind(this)
	this._handleSenderAudioEnabledChangeBound = this._handleSenderAudioEnabledChange.bind(this)
	this._handleSenderVideoEnabledChangeBound = this._handleSenderVideoEnabledChange.bind(this)
	this._handleReceiverAudioAvailableChangeBound = this._handleReceiverAudioAvailableChange.bind(this)
	this._handleReceiverVideoAvailableChangeBound = this._handleReceiverVideoAvailableChange.bind(this)
	this._handleConnectionQualityAudioChangeBound = this._handleConnectionQualityAudioChange.bind(this)
	this._handleConnectionQualityVideoChangeBound = this._handleConnectionQualityVideoChange.bind(this)
	this._handleConnectionQualityScreenChangeBound = this._handleConnectionQualityScreenChange.bind(this)
}
ParticipantAnalyzer.prototype = {

	destroy() {
		if (this._localCallParticipantModel) {
			this._localCallParticipantModel.off('change:peer', this._handlePeerChangeBound)
			this._localCallParticipantModel.off('change:screenPeer', this._handleScreenPeerChangeBound)
		}

		if (this._callParticipantModel) {
			this._callParticipantModel.off('change:peer', this._handlePeerChangeBound)
			this._callParticipantModel.off('change:screenPeer', this._handleScreenPeerChangeBound)
		}

		this._stopListeningToAudioVideoChanges()
		this._stopListeningToScreenChanges()

		this._localMediaModel = null
		this._localCallParticipantModel = null
		this._callParticipantModel = null

		this._peer = null
		this._screenPeer = null

		this._senderPeerConnectionAnalyzer = null
		this._receiverPeerConnectionAnalyzer = null
		this._senderScreenPeerConnectionAnalyzer = null
		this._receiverScreenPeerConnectionAnalyzer = null
	},

	setSenderParticipant(localMediaModel, localCallParticipantModel) {
		this.destroy()

		this._localMediaModel = localMediaModel
		this._localCallParticipantModel = localCallParticipantModel

		if (this._localCallParticipantModel) {
			this._senderPeerConnectionAnalyzer = new PeerConnectionAnalyzer()
			this._senderScreenPeerConnectionAnalyzer = new PeerConnectionAnalyzer()

			this._localCallParticipantModel.on('change:peer', this._handlePeerChangeBound)
			this._handlePeerChange(this._localCallParticipantModel, this._localCallParticipantModel.get('peer'))

			this._localCallParticipantModel.on('change:screenPeer', this._handleScreenPeerChangeBound)
			this._handleScreenPeerChange(this._localCallParticipantModel, this._localCallParticipantModel.get('screenPeer'))
		}
	},

	setReceiverParticipant(callParticipantModel) {
		this.destroy()

		this._callParticipantModel = callParticipantModel

		if (this._callParticipantModel) {
			this._receiverPeerConnectionAnalyzer = new PeerConnectionAnalyzer()
			this._receiverScreenPeerConnectionAnalyzer = new PeerConnectionAnalyzer()

			this._callParticipantModel.on('change:peer', this._handlePeerChangeBound)
			this._handlePeerChange(this._callParticipantModel, this._callParticipantModel.get('peer'))

			this._callParticipantModel.on('change:screenPeer', this._handleScreenPeerChangeBound)
			this._handleScreenPeerChange(this._callParticipantModel, this._callParticipantModel.get('screenPeer'))
		}
	},

	setSenderReceiverParticipant(localMediaModel, callParticipantModel) {
		this.destroy()

		this._localMediaModel = localMediaModel
		this._callParticipantModel = callParticipantModel

		if (this._callParticipantModel) {
			this._senderPeerConnectionAnalyzer = new PeerConnectionAnalyzer()
			this._receiverPeerConnectionAnalyzer = new PeerConnectionAnalyzer()
			this._senderScreenPeerConnectionAnalyzer = new PeerConnectionAnalyzer()
			this._receiverScreenPeerConnectionAnalyzer = new PeerConnectionAnalyzer()

			this._callParticipantModel.on('change:peer', this._handlePeerChangeBound)
			this._handlePeerChange(this._callParticipantModel, this._callParticipantModel.get('peer'))

			this._callParticipantModel.on('change:screenPeer', this._handleScreenPeerChangeBound)
			this._handleScreenPeerChange(this._callParticipantModel, this._callParticipantModel.get('screenPeer'))
		}
	},

	_handlePeerChange(model, peer) {
		if (this._peer) {
			this._stopListeningToAudioVideoChanges()
		}

		this._peer = peer

		if (peer) {
			this._startListeningToAudioVideoChanges()
		}
	},

	_handleScreenPeerChange(model, screenPeer) {
		if (this._screenPeer) {
			this._stopListeningToScreenChanges()
		}

		this._screenPeer = screenPeer

		if (screenPeer) {
			this._startListeningToScreenChanges()
		}
	},

	_startListeningToAudioVideoChanges() {
		if (this._localMediaModel) {
			this._senderPeerConnectionAnalyzer.setPeerConnection(this._peer.pc, PEER_DIRECTION.SENDER)

			this._senderPeerConnectionAnalyzer.on('change:connectionQualityAudio', this._handleConnectionQualityAudioChangeBound)
			this._senderPeerConnectionAnalyzer.on('change:connectionQualityVideo', this._handleConnectionQualityVideoChangeBound)

			this._localMediaModel.on('change:audioEnabled', this._handleSenderAudioEnabledChangeBound)
			this._localMediaModel.on('change:videoEnabled', this._handleSenderVideoEnabledChangeBound)

			this._handleSenderAudioEnabledChange(this._localMediaModel, this._localMediaModel.get('audioEnabled'))
			this._handleSenderVideoEnabledChange(this._localMediaModel, this._localMediaModel.get('videoEnabled'))
		}

		if (this._callParticipantModel) {
			this._receiverPeerConnectionAnalyzer.setPeerConnection(this._peer.pc, PEER_DIRECTION.RECEIVER)

			this._receiverPeerConnectionAnalyzer.on('change:connectionQualityAudio', this._handleConnectionQualityAudioChangeBound)
			this._receiverPeerConnectionAnalyzer.on('change:connectionQualityVideo', this._handleConnectionQualityVideoChangeBound)

			this._callParticipantModel.on('change:audioAvailable', this._handleReceiverAudioAvailableChangeBound)
			this._callParticipantModel.on('change:videoAvailable', this._handleReceiverVideoAvailableChangeBound)

			this._handleReceiverAudioAvailableChange(this._localMediaModel, this._callParticipantModel.get('audioAvailable'))
			this._handleReceiverVideoAvailableChange(this._localMediaModel, this._callParticipantModel.get('videoAvailable'))
		}
	},

	_startListeningToScreenChanges() {
		if (this._localMediaModel) {
			this._senderScreenPeerConnectionAnalyzer.setPeerConnection(this._screenPeer.pc, PEER_DIRECTION.SENDER, PEER_TYPE.SCREEN)

			this._senderScreenPeerConnectionAnalyzer.on('change:connectionQualityVideo', this._handleConnectionQualityScreenChangeBound)

			this._senderScreenPeerConnectionAnalyzer.setAnalysisEnabledAudio(false)
			this._senderScreenPeerConnectionAnalyzer.setAnalysisEnabledVideo(true)
		}

		if (this._callParticipantModel) {
			this._receiverScreenPeerConnectionAnalyzer.setPeerConnection(this._screenPeer.pc, PEER_DIRECTION.RECEIVER, PEER_TYPE.SCREEN)

			this._receiverScreenPeerConnectionAnalyzer.on('change:connectionQualityVideo', this._handleConnectionQualityScreenChangeBound)

			this._receiverScreenPeerConnectionAnalyzer.setAnalysisEnabledAudio(false)
			this._receiverScreenPeerConnectionAnalyzer.setAnalysisEnabledVideo(true)
		}
	},

	_stopListeningToAudioVideoChanges() {
		if (this._localMediaModel) {
			this._senderPeerConnectionAnalyzer.setPeerConnection(null)

			this._senderPeerConnectionAnalyzer.off('change:connectionQualityAudio', this._handleConnectionQualityAudioChangeBound)
			this._senderPeerConnectionAnalyzer.off('change:connectionQualityVideo', this._handleConnectionQualityVideoChangeBound)

			this._localMediaModel.off('change:audioEnabled', this._handleSenderAudioEnabledChangeBound)
			this._localMediaModel.off('change:videoEnabled', this._handleSenderVideoEnabledChangeBound)
		}

		if (this._callParticipantModel) {
			this._receiverPeerConnectionAnalyzer.setPeerConnection(null)

			this._receiverPeerConnectionAnalyzer.off('change:connectionQualityAudio', this._handleConnectionQualityAudioChangeBound)
			this._receiverPeerConnectionAnalyzer.off('change:connectionQualityVideo', this._handleConnectionQualityVideoChangeBound)

			this._callParticipantModel.off('change:audioAvailable', this._handleReceiverAudioAvailableChangeBound)
			this._callParticipantModel.off('change:videoAvailable', this._handleReceiverVideoAvailableChangeBound)
		}
	},

	_stopListeningToScreenChanges() {
		if (this._localMediaModel) {
			this._senderScreenPeerConnectionAnalyzer.setPeerConnection(null)

			this._senderPeerConnectionAnalyzer.off('change:connectionQualityVideo', this._handleConnectionQualityScreenChangeBound)
		}

		if (this._callParticipantModel) {
			this._receiverScreenPeerConnectionAnalyzer.setPeerConnection(null)

			this._receiverPeerConnectionAnalyzer.off('change:connectionQualityVideo', this._handleConnectionQualityScreenChangeBound)
		}
	},

	_handleConnectionQualityAudioChange(peerConnectionAnalyzer, connectionQualityAudio) {
		if (peerConnectionAnalyzer === this._senderPeerConnectionAnalyzer) {
			this._trigger('change:senderConnectionQualityAudio', [connectionQualityAudio])
		} else if (peerConnectionAnalyzer === this._receiverPeerConnectionAnalyzer) {
			this._trigger('change:receiverConnectionQualityAudio', [connectionQualityAudio])
		}
	},

	_handleConnectionQualityVideoChange(peerConnectionAnalyzer, connectionQualityVideo) {
		if (peerConnectionAnalyzer === this._senderPeerConnectionAnalyzer) {
			this._trigger('change:senderConnectionQualityVideo', [connectionQualityVideo])
		} else if (peerConnectionAnalyzer === this._receiverPeerConnectionAnalyzer) {
			this._trigger('change:receiverConnectionQualityVideo', [connectionQualityVideo])
		}
	},

	_handleConnectionQualityScreenChange(peerConnectionAnalyzer, connectionQualityScreen) {
		if (peerConnectionAnalyzer === this._senderScreenPeerConnectionAnalyzer) {
			this._trigger('change:senderConnectionQualityScreen', [connectionQualityScreen])
		} else if (peerConnectionAnalyzer === this._receiverScreenPeerConnectionAnalyzer) {
			this._trigger('change:receiverConnectionQualityScreen', [connectionQualityScreen])
		}
	},

	_handleSenderAudioEnabledChange(localMediaModel, audioEnabled) {
		this._senderPeerConnectionAnalyzer.setAnalysisEnabledAudio(audioEnabled)
	},

	_handleSenderVideoEnabledChange(localMediaModel, videoEnabled) {
		this._senderPeerConnectionAnalyzer.setAnalysisEnabledVideo(videoEnabled)
	},

	_handleReceiverAudioAvailableChange(callParticipantModel, audioAvailable) {
		this._receiverPeerConnectionAnalyzer.setAnalysisEnabledAudio(audioAvailable)
	},

	_handleReceiverVideoAvailableChange(callParticipantModel, videoAvailable) {
		this._receiverPeerConnectionAnalyzer.setAnalysisEnabledVideo(videoAvailable)
	},

}

EmitterMixin.apply(ParticipantAnalyzer.prototype)

export {
	ParticipantAnalyzer,
}
