/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { defineStore } from 'pinia'

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import { talkBroadcastChannel } from '../services/talkBroadcastChannel.js'

/**
 * @typedef {object} State
 * @property {string} initialNextcloudTalkHash - The absence status per conversation.
 * @property {boolean} isNextcloudTalkHashDirty - The parent message id to reply per conversation.
 * @property {object|null} maintenanceWarningToast -The input value per conversation.
 */

/**
 * Store for Talk app hash handling and actualizing
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useTalkHashStore = defineStore('talkHash', {
	state: () => ({
		initialNextcloudTalkHash: '',
		isNextcloudTalkHashDirty: false,
		maintenanceWarningToast: null,
	}),

	actions: {
		/**
		 * Set the current Talk hash
		 *
		 * @param {string} hash Sha1 over some config information
		 */
		setNextcloudTalkHash(hash) {
			if (!this.initialNextcloudTalkHash) {
				console.debug('X-Nextcloud-Talk-Hash initialised: ', hash)
				this.initialNextcloudTalkHash = hash
			} else if (this.initialNextcloudTalkHash !== hash && !this.isNextcloudTalkHashDirty) {
				console.debug('X-Nextcloud-Talk-Hash marked dirty: ', hash)
				this.isNextcloudTalkHashDirty = true
			}
		},

		/**
		 * Updates a Talk hash from a response
		 *
		 * @param {object} response HTTP response
		 */
		updateTalkVersionHash(response) {
			const newTalkCacheBusterHash = response?.headers?.['x-nextcloud-talk-hash']
			if (!newTalkCacheBusterHash) {
				return
			}

			this.setNextcloudTalkHash(newTalkCacheBusterHash)

			// Inform other tabs about changed hash
			talkBroadcastChannel.postMessage({
				message: 'update-nextcloud-talk-hash',
				hash: newTalkCacheBusterHash,
			})
		},

		/**
		 * Checks if Nextcloud is in maintenance mode
		 *
		 * @param {object} response HTTP response
		 */
		checkMaintenanceMode(response) {
			if (response?.status === 503 && !this.maintenanceWarningToast) {
				this.maintenanceWarningToast = showError(
					t('spreed', 'Nextcloud is in maintenance mode, please reload the page'),
					{ timeout: TOAST_PERMANENT_TIMEOUT }
				)
			}
		},

		/**
		 * Clears a toast message when Nextcloud is out of maintenance mode
		 *
		 */
		clearMaintenanceMode() {
			if (this.maintenanceWarningToast) {
				this.maintenanceWarningToast.hideToast()
				this.maintenanceWarningToast = null
			}
		},
	}
})
