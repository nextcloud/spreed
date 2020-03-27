/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
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

import { generateFilePath } from '@nextcloud/router'

export const Sounds = {
	isInCall: false,
	lastPlayedJoin: 0,
	lastPlayedLeave: 0,
	backgroundAudio: null,
	backgroundInterval: null,

	_playSounceOnce(soundFile) {
		const file = generateFilePath('spreed', 'img', soundFile)
		const audio = new Audio(file)
		audio.play()
	},

	async playWaiting() {
		if (!this.backgroundAudio) {
			console.debug('Loading waiting sound')
			const file = generateFilePath('spreed', 'img', 'LibremPhoneCall.ogg')
			this.backgroundAudio = new Audio(file)
		}

		this.backgroundInterval = setInterval(() => {
			console.debug('Playing waiting sound')
			this.backgroundAudio.play()
		}, 7000)
	},

	async playJoin(force, playWaitingSound) {
		clearInterval(this.backgroundInterval)
		if (force) {
			this.isInCall = true
		} else if (!this.isInCall) {
			return
		}

		const currentTime = (new Date()).getTime()
		if (!force && this.lastPlayedJoin >= (currentTime - 7000)) {
			if (this.lastPlayedJoin >= (currentTime - 7000)) {
				console.debug('Skipping join sound because it was played %.2f seconds ago', currentTime - this.lastPlayedJoin)
			}
			return
		}

		if (force) {
			// Don't play sounds for 8 more seconds when you just joined.
			this.lastPlayedJoin = currentTime + 8000
			this.lastPlayedLeave = currentTime + 8000
			console.debug('Playing join sound because of self joining')
		} else {
			this.lastPlayedJoin = currentTime
			console.debug('Playing join sound')
		}

		this._playSounceOnce('LibremEmailNotification.ogg')

		if (playWaitingSound) {
			this.playWaiting()
		}
	},

	async playLeave(force, playWaitingSound) {
		clearInterval(this.backgroundInterval)
		if (!this.isInCall) {
			return
		}

		const currentTime = (new Date()).getTime()
		if (!force && this.lastPlayedLeave >= (currentTime - 7000)) {
			if (this.lastPlayedLeave >= (currentTime - 7000)) {
				console.debug('Skipping leave sound because it was played %f.2 seconds ago', currentTime - this.lastPlayedLeave)
			}
			return
		}

		if (force) {
			console.debug('Playing leave sound because of self leaving')
			this.isInCall = false
		} else {
			console.debug('Playing leave sound')
		}
		this.lastPlayedLeave = currentTime

		this._playSounceOnce('LibremTextMessage.ogg')

		if (playWaitingSound) {
			this.playWaiting()
		}
	},
}
