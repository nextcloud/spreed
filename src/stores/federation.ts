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
import { getBaseUrl } from '@nextcloud/router'

import { FEDERATION } from '../constants.js'
import { getShares, acceptShare, rejectShare } from '../services/federationService.ts'
import type { Conversation, FederationInvite, NotificationInvite } from '../types'

type State = {
	pendingShares: Record<string, FederationInvite & { loading?: 'accept' | 'reject' }>,
	acceptedShares: Record<string, FederationInvite>,
	pendingSharesCount: number,
}
export const useFederationStore = defineStore('federation', {
	state: (): State => ({
		pendingShares: {},
		acceptedShares: {},
		pendingSharesCount: 0,
	}),

	actions: {
		/**
		 * Fetch pending invitations and keep them in store
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
				this.updatePendingSharesCount(Object.keys(this.pendingShares).length)
			} catch (error) {
				console.error(error)
			}
		},

		/**
		 * Add an invitation from notification to the store.
		 *
		 * @param notification notification object
		 */
		addInvitationFromNotification(notification: NotificationInvite) {
			if (this.pendingShares[notification.objectId]) {
				return
			}
			const [remoteServerUrl, remoteToken] = notification.messageRichParameters.roomName.id.split('::')
			const { id, name } = notification.messageRichParameters.user1
			const invitation: FederationInvite = {
				id: notification.objectId,
				localToken: '',
				localCloudId: notification.user + '@' + getBaseUrl().replace('https://', ''),
				remoteAttendeeId: 0,
				remoteServerUrl,
				remoteToken,
				roomName: notification.messageRichParameters.roomName.name,
				state: FEDERATION.STATE.PENDING,
				userId: notification.user,
				inviterCloudId: id + '@' + remoteServerUrl,
				inviterDisplayName: name,
			}
			Vue.set(this.pendingShares, invitation.id, invitation)
			this.updatePendingSharesCount(Object.keys(this.pendingShares).length)
		},

		/**
		 * Mark an invitation as accepted in store.
		 *
		 * @param id invitation id
		 * @param conversation conversation object
		 */
		markInvitationAccepted(id: number, conversation: Conversation) {
			if (!this.pendingShares[id]) {
				return
			}
			Vue.delete(this.pendingShares[id], 'loading')
			Vue.set(this.acceptedShares, id, {
				...this.pendingShares[id],
				localToken: conversation.token,
				state: FEDERATION.STATE.ACCEPTED,
			})
			Vue.delete(this.pendingShares, id)
		},

		/**
		 * Accept an invitation by provided id.
		 *
		 * @param id invitation id
		 */
		async acceptShare(id: number): Promise<Conversation | undefined> {
			if (!this.pendingShares[id]) {
				return
			}
			try {
				Vue.set(this.pendingShares[id], 'loading', 'accept')
				const response = await acceptShare(id)
				this.markInvitationAccepted(id, response.data.ocs.data)
				this.updatePendingSharesCount(Object.keys(this.pendingShares).length)
				return response.data.ocs.data
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while accepting an invitation'))
			}
		},

		/**
		 * Reject an invitation by provided id.
		 *
		 * @param id invitation id
		 */
		async rejectShare(id: number) {
			if (!this.pendingShares[id]) {
				return
			}
			try {
				Vue.set(this.pendingShares[id], 'loading', 'reject')
				await rejectShare(id)
				this.updatePendingSharesCount(Object.keys(this.pendingShares).length)
				Vue.delete(this.pendingShares, id)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while rejecting an invitation'))
			}
		},

		/**
		 * Update pending shares count.
		 *
		 * @param value amount of pending shares
		 */
		updatePendingSharesCount(value?: string|number) {
			Vue.set(this, 'pendingSharesCount', value ? +value : 0)
		},
	},
})
