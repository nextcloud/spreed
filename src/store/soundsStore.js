/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { generateFilePath } from '@nextcloud/router'

import BrowserStorage from '../services/BrowserStorage.js'
import { setPlaySounds } from '../services/settingsService.js'

const state = () => ({
	userId: undefined,
	playSoundsUser: loadState('spreed', 'play_sounds', false),
	playSoundsGuest: BrowserStorage.getItem('play_sounds') !== 'no',
	audioObjectsCreated: false,
	joinAudioObject: null,
	leaveAudioObject: null,
	waitAudioObject: null,
})

const getters = {
	playSounds: (state) => {
		if (state.userId) {
			return state.playSoundsUser
		}
		return state.playSoundsGuest
	},
}

const mutations = {
	/**
	 * Set play sounds
	 *
	 * @param {object} state current store state
	 * @param {boolean} enabled Whether sounds should be played
	 */
	setPlaySounds(state, enabled) {
		state.playSoundsUser = enabled
		state.playSoundsGuest = enabled
	},

	setUserId(state, userId) {
		state.userId = userId
	},

	/**
	 * Sets the join audio object
	 *
	 * @param {object} state current store state
	 * @param {HTMLAudioElement} audioObject new audio object
	 */
	setJoinAudioObject(state, audioObject) {
		state.joinAudioObject = audioObject
	},

	/**
	 * Sets the leave audio object
	 *
	 * @param {object} state current store state
	 * @param {HTMLAudioElement} audioObject new audio object
	 */
	setLeaveAudioObject(state, audioObject) {
		state.leaveAudioObject = audioObject
	},

	/**
	 * Sets the wait audio object
	 *
	 * @param {object} state current store state
	 * @param {HTMLAudioElement} audioObject new audio object
	 */
	setWaitAudioObject(state, audioObject) {
		state.waitAudioObject = audioObject
	},

	/**
	 * Sets a flag that all audio objects were created
	 *
	 * @param {object} state current store state
	 */
	setAudioObjectsCreated(state) {
		state.audioObjectsCreated = true
	},
}

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

const actions = {

	/**
	 * @param {object} context default store context;
	 * @param {object} user A NextcloudUser object as returned by @nextcloud/auth
	 * @param {string} user.uid The user id of the user
	 */
	setCurrentUser(context, user) {
		context.commit('setUserId', user.uid)
	},

	/**
	 * Set the actor from the current user
	 *
	 * @param {object} context default store context;
	 * @param {boolean} enabled Whether sounds should be played
	 */
	async setPlaySounds(context, enabled) {
		await setPlaySounds(!context.state.userId, enabled)
		context.commit('setPlaySounds', enabled)
	},

	/**
	 * Plays the join audio file with a volume of 0.75
	 *
	 * @param {object} context default store context
	 */
	playJoinAudio(context) {
		// Make sure the audio objects are really created before playing it
		context.dispatch('createAudioObjects')

		const audio = context.state.joinAudioObject
		audio.load()
		audio.volume = 0.75
		audio.play()
	},

	/**
	 * Plays the leave audio file with a volume of 0.75
	 *
	 * @param {object} context default store context
	 */
	playLeaveAudio(context) {
		// Make sure the audio objects are really created before playing it
		context.dispatch('createAudioObjects')

		const audio = context.state.leaveAudioObject
		audio.load()
		audio.volume = 0.75
		audio.play()
	},

	/**
	 * Plays the wait audio file with a volume of 0.5
	 *
	 * @param {object} context default store context
	 */
	playWaitAudio(context) {
		// Make sure the audio objects are really created before playing it
		context.dispatch('createAudioObjects')

		const audio = context.state.waitAudioObject
		audio.load()
		audio.volume = 0.5
		audio.play()

		return audio
	},

	/**
	 * Pauses the wait audio playback
	 *
	 * @param {object} context default store context
	 */
	pauseWaitAudio(context) {
		// Make sure the audio objects are really created before pausing it
		context.dispatch('createAudioObjects')

		const audio = context.state.waitAudioObject
		audio.pause()
	},

	/**
	 * If not already created, this creates audio objects for join, leave and wait sounds
	 *
	 * @param {object} context default store context
	 */
	createAudioObjects(context) {
		// No need to create the audio objects, if they were created already
		if (context.state.audioObjectsCreated) {
			return
		}

		const joinFilepath = getFullAudioFilepath('join_call')
		const joinAudio = createAudioObject(joinFilepath)
		context.commit('setJoinAudioObject', joinAudio)

		const leaveFilepath = getFullAudioFilepath('leave_call')
		const leaveAudio = createAudioObject(leaveFilepath)
		context.commit('setLeaveAudioObject', leaveAudio)

		const waitFilepath = getFullAudioFilepath('LibremPhoneCall')
		const waitAudio = createAudioObject(waitFilepath)
		context.commit('setWaitAudioObject', waitAudio)
	},
}

export default { state, mutations, getters, actions }
