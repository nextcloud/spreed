/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { getCurrentUser } from '@nextcloud/auth'
import { loadState } from '@nextcloud/initial-state'
import { generateFilePath } from '@nextcloud/router'

import BrowserStorage from '../services/BrowserStorage.js'
import { setPlaySounds } from '../services/settingsService.js'

/**
 * Helper method to get the full file path of an audio file in spreed/img.
 * Example: getFullAudioFilepath('myAudio') => spreed/img/myAudio.ogg
 *
 * @param {string} fileName The name of the file without extension
 * @return {string} The full path to the file in the spreed/img directory and adds .ogg/.flac depending on supported codecs
 */
const getFullAudioFilepath = function(fileName) {
	const tempAudio = new Audio()

	// Prefer the .ogg version of sounds, but fall back to .flac in case .ogg is not supported (Safari)
	if (tempAudio.canPlayType('audio/ogg')) {
		return generateFilePath('spreed', 'img', fileName + '.ogg')
	} else {
		return generateFilePath('spreed', 'img', fileName + '.flac')
	}
}

/**
 * Creates a HTMLAudioElement with the specified filePath and loads it
 *
 * @param {string} filePath Path to the file
 * @return {HTMLAudioElement} The created and loaded HTMLAudioElement
 */
const createAudioObject = function(filePath) {
	const audio = new Audio(filePath)
	audio.load()

	return audio
}

export const useSoundsStore = defineStore('sounds', {
	state: () => ({
		playSoundsUser: loadState('spreed', 'play_sounds', false),
		playSoundsGuest: BrowserStorage.getItem('play_sounds') !== 'no',
		audioObjectsCreated: false,
		joinAudioObject: null,
		leaveAudioObject: null,
		waitAudioObject: null,
	}),

	getters: {
		playSounds: (state) => {
			if (getCurrentUser()?.uid) {
				return state.playSoundsUser
			}
			return state.playSoundsGuest
		},
	},

	actions: {
		/**
		 * Sets the join audio object
		 *
		 * @param {HTMLAudioElement} audioObject new audio object
		 */
		setJoinAudioObject(audioObject) {
			this.joinAudioObject = audioObject
		},

		/**
		 * Sets the leave audio object
		 *
		 * @param {HTMLAudioElement} audioObject new audio object
		 */
		setLeaveAudioObject(audioObject) {
			this.leaveAudioObject = audioObject
		},

		/**
		 * Sets the wait audio object
		 *
		 * @param {HTMLAudioElement} audioObject new audio object
		 */
		setWaitAudioObject(audioObject) {
			this.waitAudioObject = audioObject
		},

		/**
		 * Sets a flag that all audio objects were created
		 */
		setAudioObjectsCreated() {
			this.audioObjectsCreated = true
		},

		/**
		 * Set play sounds
		 *
		 * @param {boolean} enabled Whether sounds should be played
		 */
		async setPlaySounds(enabled) {
			await setPlaySounds(!this.userId, enabled)
			this.playSoundsUser = enabled
			this.playSoundsGuest = enabled
		},

		/**
		 * Plays the join audio file with a volume of 0.75
		 */
		playJoinAudio() {
			// Make sure the audio objects are really created before playing it
			this.createAudioObjects()

			const audio = this.joinAudioObject
			audio.load()
			audio.volume = 0.75
			audio.play()
		},

		/**
		 * Plays the leave audio file with a volume of 0.75
		 */
		playLeaveAudio() {
			// Make sure the audio objects are really created before playing it
			this.createAudioObjects()

			const audio = this.leaveAudioObject
			audio.load()
			audio.volume = 0.75
			audio.play()
		},

		/**
		 * Plays the wait audio file with a volume of 0.5
		 */
		async playWaitAudio() {
			// Make sure the audio objects are really created before playing it
			this.createAudioObjects()

			const audio = this.waitAudioObject
			audio.load()
			audio.volume = 0.5
			audio.play()

			return audio
		},

		/**
		 * Pauses the wait audio playback
		 */
		pauseWaitAudio() {
			// Make sure the audio objects are really created before pausing it
			this.createAudioObjects()

			const audio = this.waitAudioObject
			audio.pause()
		},

		/**
		 * If not already created, this creates audio objects for join, leave and wait sounds
		 */
		createAudioObjects() {
			// No need to create the audio objects, if they were created already
			if (this.audioObjectsCreated) {
				return
			}

			const joinFilepath = getFullAudioFilepath('join_call')
			const joinAudio = createAudioObject(joinFilepath)
			this.setJoinAudioObject(joinAudio)

			const leaveFilepath = getFullAudioFilepath('leave_call')
			const leaveAudio = createAudioObject(leaveFilepath)
			this.setLeaveAudioObject(leaveAudio)

			const waitFilepath = getFullAudioFilepath('LibremPhoneCall')
			const waitAudio = createAudioObject(waitFilepath)
			this.setWaitAudioObject(waitAudio)

			this.setAudioObjectsCreated()
		},
	}
})
