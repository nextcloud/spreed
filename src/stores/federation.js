/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'

import { FEDERATION } from '../constants.js'
import { getShares, acceptShare, rejectShare } from '../services/federationService.js'

/**
 * @typedef {object} Share
 * @property {string} accessToken the invitation access token
 * @property {number} id the invitation id
 * @property {number} localRoomId the invitation local room id
 * @property {number} remoteAttendeeId the invitation remote attendee id
 * @property {string} remoteServerUrl the invitation remote server URL
 * @property {string} remoteToken the invitation remote token
 * @property {string} roomName the invitation room name
 * @property {number} state the invitation state
 * @property {string} userId the invitation user id
 * @property {string} inviterCloudId the inviter cloud id
 * @property {string} inviterDisplayName the inviter display name
 */

/**
 * @typedef {object} State
 * @property {{[key: string]: Share}} pendingShares - pending invitations
 * @property {{[key: string]: Share}} acceptedShares - accepted invitations
 */

/**
 * Store for other app integrations (additional actions for messages, participants, e.t.c)
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useFederationStore = defineStore('federation', {
	state: () => ({
		pendingShares: {},
		acceptedShares: {},
	}),

	actions: {
		/**
		 * Fetch pending invitations and keep them in store
		 *
		 */
		async getShares() {
			try {
				const response = await getShares()
				response.data.ocs.data.forEach(item => {
					if (item.state === FEDERATION.STATE.ACCEPTED) {
						Vue.set(this.acceptedShares, item.id, item)
					} else {
						Vue.set(this.pendingShares, item.id, item)
					}
				})
			} catch (error) {
				console.error(error)
			}
		},

		/**
		 * Add an invitation from notification to the store.
		 *
		 * @param {object} notification notification object
		 */
		addInvitationFromNotification(notification) {
			if (this.pendingShares[notification.objectId]) {
				return
			}
			const [remoteServerUrl, remoteToken] = notification.messageRichParameters.roomName.id.split('::')
			const { id, name } = notification.messageRichParameters.user1
			const invitation = {
				accessToken: null,
				id: notification.objectId,
				localRoomId: null,
				remoteAttendeeId: null,
				remoteServerUrl,
				remoteToken,
				roomName: notification.messageRichParameters.roomName.name,
				state: FEDERATION.STATE.PENDING,
				userId: notification.user,
				inviterCloudId: id + '@' + remoteServerUrl,
				inviterDisplayName: name,
			}
			Vue.set(this.pendingShares, invitation.id, invitation)
		},

		/**
		 * Mark an invitation as accepted in store.
		 *
		 * @param {number} id invitation id
		 * @param {object} conversation conversation object
		 */
		markInvitationAccepted(id, conversation) {
			if (!this.pendingShares[id]) {
				return
			}
			Vue.delete(this.pendingShares[id], 'loading')
			Vue.set(this.acceptedShares, id, {
				...this.pendingShares[id],
				localRoomId: conversation.id,
				state: FEDERATION.STATE.ACCEPTED,
			})
			Vue.delete(this.pendingShares, id)
		},

		/**
		 * Accept an invitation by provided id.
		 *
		 * @param {number} id invitation id
		 * @return {object} conversation to join
		 */
		async acceptShare(id) {
			if (!this.pendingShares[id]) {
				return
			}
			try {
				Vue.set(this.pendingShares[id], 'loading', 'accept')
				const response = await acceptShare(id)
				this.markInvitationAccepted(id, response.data.ocs.data)
				return response.data.ocs.data
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while accepting an invitation'))
			}
		},

		/**
		 * Reject an invitation by provided id.
		 *
		 * @param {number} id invitation id
		 */
		async rejectShare(id) {
			if (!this.pendingShares[id]) {
				return
			}
			try {
				Vue.set(this.pendingShares[id], 'loading', 'reject')
				await rejectShare(id)
				Vue.delete(this.pendingShares, id)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while rejecting an invitation'))
			}
		},
	},
})
