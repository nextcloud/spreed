/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { getBaseUrl } from '@nextcloud/router'

import { FEDERATION } from '../constants.ts'
import { setRemoteCapabilitiesIfEmpty } from '../services/CapabilitiesManager.ts'
import { getShares, acceptShare, rejectShare } from '../services/federationService.ts'
import type { Conversation, FederationInvite, NotificationInvite } from '../types/index.ts'

type State = {
	pendingShares: Record<string, FederationInvite & { loading?: 'accept' | 'reject' }>
	acceptedShares: Record<string, FederationInvite>
	pendingSharesCount: number
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
				const acceptedShares: State['acceptedShares'] = {}
				const pendingShares: State['pendingShares'] = {}
				response.data.ocs.data.forEach((item) => {
					if (item.state === FEDERATION.STATE.ACCEPTED) {
						acceptedShares[item.id] = item
					} else {
						pendingShares[item.id] = item
					}
				})
				Vue.set(this, 'acceptedShares', acceptedShares)
				Vue.set(this, 'pendingShares', pendingShares)
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
				id: +notification.objectId,
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
		markInvitationAccepted(id: string | number, conversation: Conversation) {
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
		async acceptShare(id: string | number): Promise<Conversation | undefined> {
			if (!this.pendingShares[id]) {
				return
			}
			try {
				Vue.set(this.pendingShares[id], 'loading', 'accept')
				const response = await acceptShare(id)
				await setRemoteCapabilitiesIfEmpty(response)
				this.markInvitationAccepted(id, response.data.ocs.data)
				this.updatePendingSharesCount(Object.keys(this.pendingShares).length)
				return response.data.ocs.data
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while accepting an invitation'))
				// Dismiss the loading state, refresh the list
				await this.getShares()
				if (this.pendingShares[id]) {
					Vue.delete(this.pendingShares[id], 'loading')
				}
			}
		},

		/**
		 * Reject an invitation by provided id.
		 *
		 * @param id invitation id
		 */
		async rejectShare(id: string | number) {
			if (!this.pendingShares[id]) {
				return
			}
			try {
				Vue.set(this.pendingShares[id], 'loading', 'reject')
				await rejectShare(id)
				Vue.delete(this.pendingShares, id)
				this.updatePendingSharesCount(Object.keys(this.pendingShares).length)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while rejecting an invitation'))
				// Dismiss the loading state, refresh the list
				await this.getShares()
				if (this.pendingShares[id]) {
					Vue.delete(this.pendingShares[id], 'loading')
				}
			}
		},

		/**
		 * Update pending shares count.
		 *
		 * @param value amount of pending shares
		 */
		updatePendingSharesCount(value?: string | number) {
			Vue.set(this, 'pendingSharesCount', value ? +value : 0)
		},
	},
})
