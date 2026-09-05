/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { defineStore } from 'pinia'
import BrowserStorage from '../services/BrowserStorage.js'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import { setPlaySounds } from '../services/settingsService.ts'

const hasUserAccount = Boolean(getCurrentUser()?.uid)
const playSoundsCapability = getTalkConfig('local', 'call', 'play-sounds')
const hasPlaySoundsCapability = playSoundsCapability !== undefined

/**
 * Get play sounds option. A guest keeps whatever this browser remembered, otherwise the
 * capability decides. Servers before Talk 24 don't hand the value out at all, so fall back
 * to the browser and then to enabled.
 *
 * @return {boolean}
 */
function getInitialShouldPlaySounds() {
	const fromStorage = BrowserStorage.getItem('play_sounds')

	if (!hasUserAccount && fromStorage) {
		return fromStorage !== 'no'
	}

	if (hasPlaySoundsCapability) {
		return playSoundsCapability
	}

	return fromStorage ? fromStorage !== 'no' : true
}

const shouldPlaySounds = getInitialShouldPlaySounds()

/**
 * Preferred version is the .ogg, with .flac fallback if .ogg is not supported (Safari)
 */
const fileExtension = new Audio().canPlayType('audio/ogg') ? '.ogg' : '.flac'
const isAudioOutputSelectSupported = !!(new Audio().setSinkId)

export const useSoundsStore = defineStore('sounds', {
	state: () => ({
		shouldPlaySounds,
		audioObjectsCreated: false,
		audioObjects: {
			join: null,
			leave: null,
			wait: null,
		},
		audioObjectsPromises: {
			join: null,
			leave: null,
			wait: null,
		},
		audioOutputDeviceId: undefined,
	}),

	actions: {
		/**
		 * Set play sounds option (on server for user or in browser storage for guest)
		 *
		 * @param {boolean} value whether sounds should be played
		 */
		async setShouldPlaySounds(value) {
			await setPlaySounds(hasUserAccount && hasPlaySoundsCapability, value ? 'yes' : 'no')
			this.shouldPlaySounds = value
		},

		playAudio(key) {
			if (!this.audioObjectsCreated) {
				this.initAudioObjects()
			}
			this.audioObjectsPromises[key] = this.audioObjects[key].play()
			this.audioObjectsPromises[key].catch((error) => {
				console.error(error)
			})
		},

		pauseAudio(key) {
			if (this.audioObjectsPromises[key]) {
				this.audioObjects[key].pause()
			}
		},

		/**
		 * Creates a HTMLAudioElement with the specified fileName and loads it
		 *
		 * @param {string} key key of the file at store.audioObjects
		 * @param {string} fileName name of the file at 'spreed/img/' directory
		 * @param {number} volume volume of the audio
		 */
		createAudioObject(key, fileName, volume) {
			const filePath = generateFilePath('spreed', 'img', fileName + fileExtension)
			const audio = new Audio(filePath)
			audio.load()
			audio.volume = volume

			audio.addEventListener('pause', () => {
				this.audioObjectsPromises[key] = null
				audio.currentTime = 0
			})

			audio.addEventListener('ended', () => {
				this.audioObjectsPromises[key] = null
			})

			this.audioObjects[key] = audio
		},

		/**
		 * If not already created, this creates audio objects for join, leave and wait sounds
		 */
		initAudioObjects() {
			if (this.audioObjectsCreated) {
				return
			}

			this.createAudioObject('join', 'join_call', 0.75)
			this.createAudioObject('leave', 'leave_call', 0.75)
			this.createAudioObject('wait', 'LibremPhoneCall', 0.5)
			this.audioObjectsCreated = true
		},

		async setGeneralAudioOutput(deviceId) {
			if (!isAudioOutputSelectSupported) {
				return
			}

			if (!this.audioObjectsCreated) {
				this.initAudioObjects()
			}

			try {
				for (const key in this.audioObjects) {
					this.pauseAudio(key)
					await this.audioObjects[key].setSinkId(deviceId)
				}
			} catch (error) {
				console.error(error)
			}
			this.audioOutputDeviceId = deviceId
		},
	},
})
