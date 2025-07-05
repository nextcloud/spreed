import { setGuestNickname } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'
import { setGuestUserName } from '../services/participantsService.js'
import { useActorStore } from './actor.ts'

export const useGuestNameStore = defineStore('guestName', {
	state: () => ({
		guestNames: {},
	}),

	actions: {
		/**
		 * Gets the participant display name
		 *
		 * @param {string} token the conversation's token
		 * @param {string} actorId the participant actorId
		 * @return {string} the participant name
		 */
		getGuestName(token, actorId) {
			return this.guestNames[token]?.[actorId] ?? t('spreed', 'Guest')
		},

		/**
		 * Gets the participant display name with suffix
		 * if the display name is not default translatable Guest
		 *
		 * @param {string} token the conversation's token
		 * @param {string} actorId the participant actorId
		 * @return {string} the participant name with/without suffix
		 */
		getGuestNameWithGuestSuffix(token, actorId) {
			const displayName = this.getGuestName(token, actorId)
			if (displayName === t('spreed', 'Guest')) {
				return displayName
			}
			return t('spreed', '{guest} (guest)', {
				guest: displayName,
			})
		},

		/**
		 * Adds a guest name to the store
		 *
		 * @param {object} data the wrapping object
		 * @param {string} data.token the token of the conversation
		 * @param {string} data.actorId the guest
		 * @param {string} data.actorDisplayName the display name to set
		 * @param {object} options options
		 * @param {boolean} options.noUpdate Override the display name or set it if it is empty
		 */
		addGuestName({ token, actorId, actorDisplayName }, { noUpdate }) {
			if (!this.guestNames[token]) {
				this.guestNames[token] = {}
			}
			if (!this.guestNames[token][actorId] || actorDisplayName === '') {
				this.guestNames[token][actorId] = t('spreed', 'Guest')
			} else if (noUpdate) {
				return
			}

			if (actorDisplayName) {
				this.guestNames[token][actorId] = actorDisplayName
			}
		},

		/**
		 * Add the submitted guest name to the store
		 *
		 * @param {string} token the token of the conversation
		 * @param {string} name the new guest name
		 */
		async submitGuestUsername(token, name) {
			const actorStore = useActorStore()
			const actorId = actorStore.actorId
			const previousName = this.getGuestName(token, actorId)

			try {
				actorStore.setDisplayName(name)
				this.addGuestName({
					token,
					actorId,
					actorDisplayName: name,
				}, { noUpdate: false })

				await setGuestUserName(token, name)

				setGuestNickname(name || t('spreed', 'Guest'))
			} catch (error) {
				actorStore.setDisplayName(previousName)
				this.addGuestName({
					token,
					actorId,
					actorDisplayName: previousName,
				}, { noUpdate: false })
				console.error(error)
			}
		},
	},
})
