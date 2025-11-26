/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	WebRtc,
} from '../../types/index.ts'

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
 */
export class LocalStateBroadcaster {
	private _webRtc: WebRtc

	private _handleAudioOnBound: () => void
	private _handleAudioOffBound: () => void
	private _handleSpeakingBound: () => void
	private _handleStoppedSpeakingBound: () => void
	private _handleVideoOnBound: () => void
	private _handleVideoOffBound: () => void

	public constructor(webRtc: WebRtc) {
		this._webRtc = webRtc

		this._handleAudioOnBound = this._handleAudioOn.bind(this)
		this._handleAudioOffBound = this._handleAudioOff.bind(this)
		this._handleSpeakingBound = this._handleSpeaking.bind(this)
		this._handleStoppedSpeakingBound = this._handleStoppedSpeaking.bind(this)
		this._handleVideoOnBound = this._handleVideoOn.bind(this)
		this._handleVideoOffBound = this._handleVideoOff.bind(this)

		this._webRtc.on('audioOn', this._handleAudioOnBound)
		this._webRtc.on('audioOff', this._handleAudioOffBound)
		this._webRtc.on('speaking', this._handleSpeakingBound)
		this._webRtc.on('stoppedSpeaking', this._handleStoppedSpeakingBound)
		this._webRtc.on('videoOn', this._handleVideoOnBound)
		this._webRtc.on('videoOff', this._handleVideoOffBound)
	}

	public destroy(): void {
		this._webRtc.off('audioOn', this._handleAudioOnBound)
		this._webRtc.off('audioOff', this._handleAudioOffBound)
		this._webRtc.off('speaking', this._handleSpeakingBound)
		this._webRtc.off('stoppedSpeaking', this._handleStoppedSpeakingBound)
		this._webRtc.off('videoOn', this._handleVideoOnBound)
		this._webRtc.off('videoOff', this._handleVideoOffBound)
	}

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
}
