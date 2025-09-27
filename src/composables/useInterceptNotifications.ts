/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Notification, NotificationEvent, NotificationInvite } from '../types/index.ts'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import { useFederationStore } from '../stores/federation.ts'

/**
 * Type guard to check if a notification is a Talk federation invitation
 *
 * @param notification - notification object
 */
function isNotificationInvite(notification: Notification): notification is NotificationInvite {
	return notification.objectType === 'remote_talk_share'
}

/**
 * Composable to intercept notifications and handle them via Talk app
 */
export function useInterceptNotifications() {
	const router = useRouter()
	const store = useStore()
	const federationStore = useFederationStore()

	subscribe('notifications:action:execute', interceptNotificationActions)
	subscribe('notifications:notification:received', interceptNotificationReceived)

	onBeforeUnmount(() => {
		unsubscribe('notifications:action:execute', interceptNotificationActions)
		unsubscribe('notifications:notification:received', interceptNotificationReceived)
	})

	/**
	 * Intercept clicking actions on notifications and open the conversation without a page reload instead
	 *
	 * @param event The event object provided by the notifications app
	 * @param event.notification The notification object
	 * @param event.notification.app The app ID of the app providing the notification
	 * @param event.action The action that was clicked
	 * @param event.action.url The URL the action is aiming at
	 * @param event.action.type The request type used for the action
	 * @param event.cancelAction Option to cancel the action so no page reload is happening
	 */
	async function interceptNotificationActions(event: NotificationEvent) {
		if (event.notification.app !== 'spreed') {
			return
		}

		switch (event.action.type) {
			case 'WEB': {
				const load = event.action.url.split('/call/').pop()
				if (!load) {
					return
				}

				const [token, hash] = load.split('#')
				await router.push({
					name: 'conversation',
					hash: hash ? `#${hash}` : '',
					params: {
						token,
					},
				})

				event.cancelAction = true
				break
			}
			case 'POST': {
				// Federation invitation handling
				if (isNotificationInvite(event.notification)) {
					try {
						event.cancelAction = true
						federationStore.addInvitationFromNotification(event.notification)
						const conversation = await federationStore.acceptShare(event.notification.objectId)
						if (conversation?.token) {
							await store.dispatch('addConversation', conversation)
							await router.push({ name: 'conversation', params: { token: conversation.token } })
						}
					} catch (error) {
						console.error(error)
					}
				}
				break
			}
			case 'DELETE': {
				// Federation invitation handling
				if (isNotificationInvite(event.notification)) {
					try {
						event.cancelAction = true
						federationStore.addInvitationFromNotification(event.notification)
						await federationStore.rejectShare(event.notification.objectId)
					} catch (error) {
						console.error(error)
					}
				}
				break
			}
			default: break
		}
	}

	/**
	 * Intercept …
	 *
	 * @param event The event object provided by the notifications app
	 * @param event.notification The notification object
	 * @param event.notification.app The app ID of the app providing the notification
	 */
	async function interceptNotificationReceived(event: NotificationEvent) {
		if (event.notification.app !== 'spreed') {
			return
		}

		switch (event.notification.objectType) {
			case 'chat': {
				if (event.notification.subjectRichParameters?.reaction) {
					// Ignore reaction notifications in case of one-to-one and always-notify
					return
				}

				await store.dispatch('updateConversationLastMessageFromNotification', {
					notification: event.notification,
				})
				break
			}
			case 'call': {
				await store.dispatch('updateCallStateFromNotification', {
					notification: event.notification,
				})
				break
			}
			// Federation invitation handling
			case 'remote_talk_share': {
				federationStore.addInvitationFromNotification(event.notification as NotificationInvite)
				break
			}
			default: break
		}
	}
}
