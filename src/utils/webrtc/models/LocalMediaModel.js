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

import { VIRTUAL_BACKGROUND } from '../../../constants.js'
import BrowserStorage from '../../../services/BrowserStorage.js'
import store from '../../../store/index.js'
import EmitterMixin from '../../EmitterMixin.js'

/**
 *
 */
export default function LocalMediaModel() {

	this._superEmitterMixin()

	this.attributes = {
		localStreamRequestVideoError: null,
		localStream: null,
		audioAvailable: false,
		audioEnabled: false,
		speaking: false,
		speakingWhileMuted: false,
		currentVolume: -100,
		volumeThreshold: -100,
		videoAvailable: false,
		videoEnabled: false,
		virtualBackgroundAvailable: false,
		virtualBackgroundEnabled: false,
		virtualBackgroundType: null,
		virtualBackgroundBlurStrength: null,
		virtualBackgroundUrl: null,
		localScreen: null,
		token: '',
		raisedHand: false,
	}

	this._handleLocalStreamRequestedBound = this._handleLocalStreamRequested.bind(this)
	this._handleLocalStreamBound = this._handleLocalStream.bind(this)
	this._handleLocalStreamRequestFailedRetryNoVideoBound = this._handleLocalStreamRequestFailedRetryNoVideo.bind(this)
	this._handleLocalStreamRequestFailedBound = this._handleLocalStreamRequestFailed.bind(this)
	this._handleLocalStreamChangedBound = this._handleLocalStreamChanged.bind(this)
	this._handleLocalTrackEnabledChangedBound = this._handleLocalTrackEnabledChanged.bind(this)
	this._handleLocalStreamStoppedBound = this._handleLocalStreamStopped.bind(this)
	this._handleAudioDisallowedBound = this._handleAudioDisallowed.bind(this)
	this._handleVolumeChangeBound = this._handleVolumeChange.bind(this)
	this._handleSpeakingBound = this._handleSpeaking.bind(this)
	this._handleStoppedSpeakingBound = this._handleStoppedSpeaking.bind(this)
	this._handleSpeakingWhileMutedBound = this._handleSpeakingWhileMuted.bind(this)
	this._handleStoppedSpeakingWhileMutedBound = this._handleStoppedSpeakingWhileMuted.bind(this)
	this._handleVideoDisallowedBound = this._handleVideoDisallowed.bind(this)
	this._handleVirtualBackgroundLoadFailedBound = this._handleVirtualBackgroundLoadFailed.bind(this)
	this._handleVirtualBackgroundOnBound = this._handleVirtualBackgroundOn.bind(this)
	this._handleVirtualBackgroundSetBound = this._handleVirtualBackgroundSet.bind(this)
	this._handleVirtualBackgroundOffBound = this._handleVirtualBackgroundOff.bind(this)
	this._handleLocalScreenBound = this._handleLocalScreen.bind(this)
	this._handleLocalScreenStoppedBound = this._handleLocalScreenStopped.bind(this)

}

LocalMediaModel.prototype = {

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

	getWebRtc() {
		return this._webRtc
	},

	setWebRtc(webRtc) {
		if (this._webRtc && this._webRtc.webrtc) {
			this._webRtc.webrtc.off('localStreamRequested', this._handleLocalStreamRequestedBound)
			this._webRtc.webrtc.off('localStream', this._handleLocalStreamBound)
			this._webRtc.webrtc.off('localStreamRequestFailedRetryNoVideo', this._handleLocalStreamRequestFailedBound)
			this._webRtc.webrtc.off('localStreamRequestFailed', this._handleLocalStreamRequestFailedBound)
			this._webRtc.webrtc.off('localStreamChanged', this._handleLocalStreamChangedBound)
			this._webRtc.webrtc.off('localTrackEnabledChanged', this._handleLocalTrackEnabledChangedBound)
			this._webRtc.webrtc.off('localStreamStopped', this._handleLocalStreamStoppedBound)
			this._webRtc.webrtc.off('audioDisallowed', this._handleAudioDisallowedBound)
			this._webRtc.webrtc.off('volumeChange', this._handleVolumeChangeBound)
			this._webRtc.webrtc.off('speaking', this._handleSpeakingBound)
			this._webRtc.webrtc.off('stoppedSpeaking', this._handleStoppedSpeakingBound)
			this._webRtc.webrtc.off('speakingWhileMuted', this._handleSpeakingWhileMutedBound)
			this._webRtc.webrtc.off('stoppedSpeakingWhileMuted', this._handleStoppedSpeakingWhileMutedBound)
			this._webRtc.webrtc.off('videoDisallowed', this._handleVideoDisallowedBound)
			this._webRtc.webrtc.off('virtualBackgroundLoadFailed', this._handleVirtualBackgroundLoadFailedBound)
			this._webRtc.webrtc.off('virtualBackgroundOn', this._handleVirtualBackgroundOnBound)
			this._webRtc.webrtc.off('virtualBackgroundSet', this._handleVirtualBackgroundSetBound)
			this._webRtc.webrtc.off('virtualBackgroundOff', this._handleVirtualBackgroundOffBound)
			this._webRtc.webrtc.off('localScreen', this._handleLocalScreenBound)
			this._webRtc.webrtc.off('localScreenStopped', this._handleLocalScreenStoppedBound)
		}

		this._webRtc = webRtc

		this.set('localStream', null)
		this.set('audioAvailable', false)
		this.set('audioEnabled', false)
		this.set('speaking', false)
		this.set('speakingWhileMuted', false)
		this.set('currentVolume', -100)
		this.set('volumeThreshold', -100)
		this.set('videoAvailable', false)
		this.set('videoEnabled', false)
		this.set('virtualBackgroundAvailable', this._webRtc.webrtc.isVirtualBackgroundAvailable())
		this.set('virtualBackgroundEnabled', this._webRtc.webrtc.isVirtualBackgroundEnabled())
		if (this._webRtc.webrtc.isVirtualBackgroundAvailable()) {
			this._setVirtualBackgroundTypeAndParameters(this._webRtc.webrtc.getVirtualBackground())
		}
		this.set('localScreen', null)

		this._webRtc.webrtc.on('localStreamRequested', this._handleLocalStreamRequestedBound)
		this._webRtc.webrtc.on('localStream', this._handleLocalStreamBound)
		this._webRtc.webrtc.on('localStreamRequestFailedRetryNoVideo', this._handleLocalStreamRequestFailedRetryNoVideoBound)
		this._webRtc.webrtc.on('localStreamRequestFailed', this._handleLocalStreamRequestFailedBound)
		this._webRtc.webrtc.on('localStreamChanged', this._handleLocalStreamChangedBound)
		this._webRtc.webrtc.on('localTrackEnabledChanged', this._handleLocalTrackEnabledChangedBound)
		this._webRtc.webrtc.on('localStreamStopped', this._handleLocalStreamStoppedBound)
		this._webRtc.webrtc.on('audioDisallowed', this._handleAudioDisallowedBound)
		this._webRtc.webrtc.on('volumeChange', this._handleVolumeChangeBound)
		this._webRtc.webrtc.on('speaking', this._handleSpeakingBound)
		this._webRtc.webrtc.on('stoppedSpeaking', this._handleStoppedSpeakingBound)
		this._webRtc.webrtc.on('speakingWhileMuted', this._handleSpeakingWhileMutedBound)
		this._webRtc.webrtc.on('stoppedSpeakingWhileMuted', this._handleStoppedSpeakingWhileMutedBound)
		this._webRtc.webrtc.on('videoDisallowed', this._handleVideoDisallowedBound)
		this._webRtc.webrtc.on('virtualBackgroundLoadFailed', this._handleVirtualBackgroundLoadFailedBound)
		this._webRtc.webrtc.on('virtualBackgroundOn', this._handleVirtualBackgroundOnBound)
		this._webRtc.webrtc.on('virtualBackgroundSet', this._handleVirtualBackgroundSetBound)
		this._webRtc.webrtc.on('virtualBackgroundOff', this._handleVirtualBackgroundOffBound)
		this._webRtc.webrtc.on('localScreen', this._handleLocalScreenBound)
		this._webRtc.webrtc.on('localScreenStopped', this._handleLocalScreenStoppedBound)
	},

	_handleLocalStreamRequested(context) {
		if (context !== 'retry-no-video') {
			this.set('localStreamRequestVideoError', null)
		}
	},

	_handleLocalStream(localStream) {
		// Although there could be several local streams active at the same
		// time (if the local media is started again before stopping it
		// first) the methods to control them ("mute", "unmute",
		// "pauseVideo" and "resumeVideo") act on all the streams, it is not
		// possible to control them individually. Also all local streams
		// are transmitted when a Peer is created, but if another local
		// stream is then added it will not be automatically added to the
		// Peer. As it is not well supported and there is also no need to
		// use several local streams for now it is assumed that only one
		// local stream will be active at the same time.
		this.set('localStream', localStream)

		this._setInitialState(localStream)
	},

	_handleLocalStreamRequestFailedRetryNoVideo(error) {
		if (!error || error.name === 'NotFoundError') {
			return
		}

		this.set('localStreamRequestVideoError', error)
	},

	_handleLocalStreamRequestFailed() {
		this.set('localStream', null)

		this._setInitialState(null)
	},

	_setInitialState(localStream) {
		this.set('token', store.getters.getToken())

		this._updateMediaAvailability(localStream)

		this.set('raisedHand', { state: false, timestamp: Date.now() })
	},

	_handleLocalStreamChanged(localStream) {
		// Only a single local stream is assumed to be active at the same time.
		this.set('localStream', localStream)

		this._updateMediaAvailability(localStream)
	},

	_updateMediaAvailability(localStream) {
		if (localStream && localStream.getAudioTracks().length > 0) {
			this.set('audioAvailable', true)
			this.set('audioEnabled', localStream.getAudioTracks()[0].enabled)
		} else {
			this.disableAudio()
			// "audioEnabled" needs to be explicitly set to false, as there is
			// no audio track and thus disabling the audio will not trigger the
			// handler for "localTrackEnabledChanged"; calling "disableAudio()"
			// just ensures that the audio will be initially disabled if it
			// becomes available again later.
			this.set('audioEnabled', false)
			this.set('audioAvailable', false)
		}

		if (localStream && localStream.getVideoTracks().length > 0) {
			this.set('videoAvailable', true)
			this.set('videoEnabled', localStream.getVideoTracks()[0].enabled)
		} else {
			this.disableVideo()
			// "videoEnabled" needs to be explicitly set to false, as there is
			// no video track and thus disabling the video will not trigger the
			// handler for "localTrackEnabledChanged"; calling "disableVideo()"
			// just ensures that the video will be initially disabled if it
			// becomes available again later.
			this.set('videoEnabled', false)
			this.set('videoAvailable', false)
		}
	},

	_handleLocalTrackEnabledChanged(track, stream) {
		if (track.kind === 'audio') {
			this.set('audioEnabled', track.enabled)
		} else if (track.kind === 'video') {
			this.set('videoEnabled', track.enabled)
		}
	},

	_handleLocalStreamStopped(localStream) {
		if (this.get('localStream') !== localStream) {
			return
		}

		this.set('localStream', null)

		this.set('audioEnabled', false)
		this.set('audioAvailable', false)
		this.set('videoEnabled', false)
		this.set('videoAvailable', false)
	},

	_handleAudioDisallowed() {
		this.disableAudio()
	},

	_handleVolumeChange(currentVolume, volumeThreshold) {
		if (!this.get('audioAvailable')) {
			return
		}

		this.set('currentVolume', currentVolume)
		this.set('volumeThreshold', volumeThreshold)
	},

	_handleSpeaking() {
		if (!this.get('audioAvailable')) {
			return
		}

		this.set('speaking', true)
	},

	_handleStoppedSpeaking() {
		if (!this.get('audioAvailable')) {
			return
		}

		this.set('speaking', false)
	},

	_handleSpeakingWhileMuted() {
		if (!this.get('audioAvailable')) {
			return
		}

		this.set('speakingWhileMuted', true)
	},

	_handleStoppedSpeakingWhileMuted() {
		if (!this.get('audioAvailable')) {
			return
		}

		this.set('speakingWhileMuted', false)
	},

	_handleVideoDisallowed() {
		this.disableVideo()
	},

	_handleVirtualBackgroundLoadFailed() {
		this.set('virtualBackgroundAvailable', false)
	},

	_handleVirtualBackgroundOn() {
		this.set('virtualBackgroundEnabled', true)
	},

	_setVirtualBackgroundTypeAndParameters(virtualBackground) {
		this.set('virtualBackgroundType', virtualBackground.backgroundType)

		if (virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR) {
			this.set('virtualBackgroundBlurStrength', virtualBackground.blurValue)
			this.set('virtualBackgroundUrl', null)

			return
		}

		if (virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE
			|| virtualBackground.backgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO) {
			this.set('virtualBackgroundUrl', virtualBackground.virtualSource)
			this.set('virtualBackgroundBlurStrength', null)
		}
	},

	_handleVirtualBackgroundSet(virtualBackground) {
		this._setVirtualBackgroundTypeAndParameters(virtualBackground)
	},

	_handleVirtualBackgroundOff() {
		this.set('virtualBackgroundEnabled', false)
	},

	_handleLocalScreen(screen) {
		this.set('localScreen', screen)
	},

	_handleLocalScreenStopped() {
		this.set('localScreen', null)
	},

	enableAudio() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.removeItem('audioDisabled_' + this.get('token'))

		this._webRtc.unmute()
	},

	disableAudio() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.setItem('audioDisabled_' + this.get('token'), 'true')

		this._webRtc.mute()
	},

	enableVideo() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.removeItem('videoDisabled_' + this.get('token'))

		this._webRtc.resumeVideo()
	},

	disableVideo() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.setItem('videoDisabled_' + this.get('token'), 'true')

		this._webRtc.pauseVideo()
	},

	enableVirtualBackground() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.setItem('virtualBackgroundEnabled_' + this.get('token'), 'true')

		this._webRtc.enableVirtualBackground()
	},

	setVirtualBackgroundBlur(blurStrength) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		if (!blurStrength) {
			blurStrength = VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT
		}

		BrowserStorage.setItem('virtualBackgroundType_' + this.get('token'), VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR)
		BrowserStorage.setItem('virtualBackgroundBlurStrength_' + this.get('token'), blurStrength)
		BrowserStorage.removeItem('virtualBackgroundUrl_' + this.get('token'))

		this._webRtc.setVirtualBackground({
			backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
			blurValue: blurStrength,
		})
	},

	setVirtualBackgroundImage(imageUrl) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.setItem('virtualBackgroundType_' + this.get('token'), VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE)
		BrowserStorage.setItem('virtualBackgroundUrl_' + this.get('token'), imageUrl)
		BrowserStorage.removeItem('virtualBackgroundBlurStrength_' + this.get('token'))

		this._webRtc.setVirtualBackground({
			backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE,
			virtualSource: imageUrl,
		})
	},

	setVirtualBackgroundVideo(videoUrl) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.setItem('virtualBackgroundType_' + this.get('token'), VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO)
		BrowserStorage.setItem('virtualBackgroundUrl_' + this.get('token'), videoUrl)
		BrowserStorage.removeItem('virtualBackgroundBlurStrength_' + this.get('token'))

		this._webRtc.setVirtualBackground({
			backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO,
			virtualSource: videoUrl,
		})
	},

	disableVirtualBackground() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		BrowserStorage.removeItem('virtualBackgroundEnabled_' + this.get('token'))

		this._webRtc.disableVirtualBackground()
	},

	shareScreen(mode, callback) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		this._webRtc.shareScreen(mode, callback)
	},

	stopSharingScreen() {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		this._webRtc.stopScreenShare()
	},

	/**
	 * Toggles hand raised mode for the local participant
	 *
	 * @param {boolean} raised true for raised, false for lowered
	 */
	toggleHandRaised(raised) {
		if (!this._webRtc) {
			throw new Error('WebRtc not initialized yet')
		}

		const raisedHand = {
			state: raised,
			timestamp: Date.now(),
		}

		this._webRtc.sendToAll('raiseHand', raisedHand)

		// Set state locally too, as even when sending to all the sender will not
		// receive the message.
		this.set('raisedHand', raisedHand)
	},

}

EmitterMixin.apply(LocalMediaModel.prototype)
