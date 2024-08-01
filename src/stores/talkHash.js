/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import { talkBroadcastChannel } from '../services/talkBroadcastChannel.js'

/**
 * @typedef {object} State
 * @property {string} initialNextcloudTalkHash - the 'default' Talk hash to compare with.
 * @property {boolean} isNextcloudTalkHashDirty - whether Talk hash was updated and requires a reload.
 * @property {object} isNextcloudTalkProxyHashDirty - whether Talk hash in federated conversation was updated.
 * @property {object|null} maintenanceWarningToast - a toast object.
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
		isNextcloudTalkProxyHashDirty: {},
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
		 * Mark the current Talk Federation hash as dirty
		 *
		 * @param {string} token federated conversation token
		 */
		setTalkProxyHashDirty(token) {
			console.debug('X-Nextcloud-Talk-Proxy-Hash marked dirty: ', token)
			Vue.set(this.isNextcloudTalkProxyHashDirty, token, true)
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
