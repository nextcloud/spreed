/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { n, t } from '@nextcloud/l10n'
import cloneDeep from 'lodash/cloneDeep.js'
import { useStore } from './useStore.js'

/**
 * Create combined system message from the passed object
 *
 */
export function useCombinedSystemMessage() {
	const store = useStore()

	/**
	 *
	 * @param {object} message message to check for
	 * @return {boolean}
	 */
	function checkIfSelfIsActor(message) {
		return message.actorId === store.getters.getActorId()
			&& message.actorType === store.getters.getActorType()
	}

	/**
	 *
	 * @param {object} message message to check for
	 * @return {boolean}
	 */
	function checkIfSelfIsOneOfActors(message) {
		return message.messageParameters.actor.id === store.getters.getActorId()
			&& message.messageParameters.actor.type + 's' === store.getters.getActorType()
	}

	/**
	 *
	 * @param {object} message message to check for
	 * @return {boolean}
	 */
	function checkIfSelfIsOneOfUsers(message) {
		return message.messageParameters.user.id === store.getters.getActorId()
			&& message.messageParameters.user.type + 's' === store.getters.getActorType()
	}

	/**
	 *
	 * @param {object} group object representing the group of system messages
	 * @param {number} group.id id of the group
	 * @param {Array} group.messages array of grouped messages
	 * @param {string} group.type combination type
	 * @param {boolean} group.collapsed collapsed state
	 * @return {object}
	 */
	function createCombinedSystemMessage({ id, messages, type, collapsed }) {
		const combinedMessage = cloneDeep(messages[0])
		combinedMessage.id = messages[0].id + '_combined'

		// Handle cases when users reconnected to the call
		if (type === 'call_reconnected') {
			if (checkIfSelfIsOneOfActors(combinedMessage)) {
				combinedMessage.message = t('spreed', 'You reconnected to the call')
			} else {
				combinedMessage.message = t('spreed', '{actor} reconnected to the call')
			}

			return combinedMessage
		}

		// clear messageParameters to be filled later
		const actor = messages[0].messageParameters.actor
		combinedMessage.messageParameters = { actor }
		const actorIsAdministrator = actor.id === 'guest/cli' && actor.type === 'guest'

		// usersCounter should be equal at least 2, as we're using method only for groups
		let usersCounter = 0
		let selfIsUser = false
		let referenceIndex = 0

		// Handle cases when actor added users to conversation (when populate on creation, for example)
		if (type === 'user_added') {
			messages.forEach((message) => {
				if (checkIfSelfIsOneOfUsers(message)) {
					selfIsUser = true
				} else {
					combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
					referenceIndex++
				}
				usersCounter++
			})

			if (checkIfSelfIsActor(combinedMessage)) {
				if (usersCounter === 2) {
					combinedMessage.message = t('spreed', 'You added {user0} and {user1}')
				} else {
					combinedMessage.message = n(
						'spreed',
						'You added {user0}, {user1} and %n more participant',
						'You added {user0}, {user1} and %n more participants',
						usersCounter - 2,
					)
				}
			} else if (selfIsUser) {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator added you and {user0}')
						: t('spreed', '{actor} added you and {user0}')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator added you, {user0} and %n more participant',
								'An administrator added you, {user0} and %n more participants',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} added you, {user0} and %n more participant',
								'{actor} added you, {user0} and %n more participants',
								usersCounter - 2,
							)
				}
			} else {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator added {user0} and {user1}')
						: t('spreed', '{actor} added {user0} and {user1}')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator added {user0}, {user1} and %n more participant',
								'An administrator added {user0}, {user1} and %n more participants',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} added {user0}, {user1} and %n more participant',
								'{actor} added {user0}, {user1} and %n more participants',
								usersCounter - 2,
							)
				}
			}
		}

		// Handle cases when actor removed users from conversation (when remove team/group, for example)
		if (type === 'user_removed') {
			messages.forEach((message) => {
				if (checkIfSelfIsOneOfUsers(message)) {
					selfIsUser = true
				} else {
					combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
					referenceIndex++
				}
				usersCounter++
			})

			if (checkIfSelfIsActor(combinedMessage)) {
				if (usersCounter === 2) {
					combinedMessage.message = t('spreed', 'You removed {user0} and {user1}')
				} else {
					combinedMessage.message = n(
						'spreed',
						'You removed {user0}, {user1} and %n more participant',
						'You removed {user0}, {user1} and %n more participants',
						usersCounter - 2,
					)
				}
			} else if (selfIsUser) {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator removed you and {user0}')
						: t('spreed', '{actor} removed you and {user0}')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator removed you, {user0} and %n more participant',
								'An administrator removed you, {user0} and %n more participants',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} removed you, {user0} and %n more participant',
								'{actor} removed you, {user0} and %n more participants',
								usersCounter - 2,
							)
				}
			} else {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator removed {user0} and {user1}')
						: t('spreed', '{actor} removed {user0} and {user1}')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator removed {user0}, {user1} and %n more participant',
								'An administrator removed {user0}, {user1} and %n more participants',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} removed {user0}, {user1} and %n more participant',
								'{actor} removed {user0}, {user1} and %n more participants',
								usersCounter - 2,
							)
				}
			}
		}

		// Handle cases when users joined or left the call
		if (type === 'call_joined' || type === 'call_left') {
			const storedUniqueUsers = []

			messages.forEach((message) => {
				const actorReference = `${message.messageParameters.actor.id}_${message.messageParameters.actor.type}`
				if (storedUniqueUsers.includes(actorReference)) {
					return
				}

				if (checkIfSelfIsOneOfActors(message)) {
					selfIsUser = true
				} else {
					combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.actor
					referenceIndex++
				}

				storedUniqueUsers.push(actorReference)
				usersCounter++
			})

			if (usersCounter === 1) {
				combinedMessage.message = messages[0].message
				return combinedMessage
			}

			if (type === 'call_joined') {
				if (selfIsUser) {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You and {user0} joined the call')
					} else {
						combinedMessage.message = n(
							'spreed',
							'You, {user0} and %n more participant joined the call',
							'You, {user0} and %n more participants joined the call',
							usersCounter - 2,
						)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', '{user0} and {user1} joined the call')
					} else {
						combinedMessage.message = n(
							'spreed',
							'{user0}, {user1} and %n more participant joined the call',
							'{user0}, {user1} and %n more participants joined the call',
							usersCounter - 2,
						)
					}
				}
			} else if (type === 'call_left') {
				if (selfIsUser) {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', 'You and {user0} left the call')
					} else {
						combinedMessage.message = n(
							'spreed',
							'You, {user0} and %n more participant left the call',
							'You, {user0} and %n more participants left the call',
							usersCounter - 2,
						)
					}
				} else {
					if (usersCounter === 2) {
						combinedMessage.message = t('spreed', '{user0} and {user1} left the call')
					} else {
						combinedMessage.message = n(
							'spreed',
							'{user0}, {user1} and %n more participant left the call',
							'{user0}, {user1} and %n more participants left the call',
							usersCounter - 2,
						)
					}
				}
			}
		}

		// Handle cases when actor promoted several users to moderators
		if (type === 'moderator_promoted') {
			messages.forEach((message) => {
				if (checkIfSelfIsOneOfUsers(message)) {
					selfIsUser = true
				} else {
					combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
					referenceIndex++
				}
				usersCounter++
			})

			if (checkIfSelfIsActor(combinedMessage)) {
				if (usersCounter === 2) {
					combinedMessage.message = t('spreed', 'You promoted {user0} and {user1} to moderators')
				} else {
					combinedMessage.message = n(
						'spreed',
						'You promoted {user0}, {user1} and %n more participant to moderators',
						'You promoted {user0}, {user1} and %n more participants to moderators',
						usersCounter - 2,
					)
				}
			} else if (selfIsUser) {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator promoted you and {user0} to moderators')
						: t('spreed', '{actor} promoted you and {user0} to moderators')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator promoted you, {user0} and %n more participant to moderators',
								'An administrator promoted you, {user0} and %n more participants to moderators',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} promoted you, {user0} and %n more participant to moderators',
								'{actor} promoted you, {user0} and %n more participants to moderators',
								usersCounter - 2,
							)
				}
			} else {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator promoted {user0} and {user1} to moderators')
						: t('spreed', '{actor} promoted {user0} and {user1} to moderators')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator promoted {user0}, {user1} and %n more participant to moderators',
								'An administrator promoted {user0}, {user1} and %n more participants to moderators',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} promoted {user0}, {user1} and %n more participant to moderators',
								'{actor} promoted {user0}, {user1} and %n more participants to moderators',
								usersCounter - 2,
							)
				}
			}
		}

		// Handle cases when actor demoted several users from moderators
		if (type === 'moderator_demoted') {
			messages.forEach((message) => {
				if (checkIfSelfIsOneOfUsers(message)) {
					selfIsUser = true
				} else {
					combinedMessage.messageParameters[`user${referenceIndex}`] = message.messageParameters.user
					referenceIndex++
				}
				usersCounter++
			})

			if (checkIfSelfIsActor(combinedMessage)) {
				if (usersCounter === 2) {
					combinedMessage.message = t('spreed', 'You demoted {user0} and {user1} from moderators')
				} else {
					combinedMessage.message = n(
						'spreed',
						'You demoted {user0}, {user1} and %n more participant from moderators',
						'You demoted {user0}, {user1} and %n more participants from moderators',
						usersCounter - 2,
					)
				}
			} else if (selfIsUser) {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator demoted you and {user0} from moderators')
						: t('spreed', '{actor} demoted you and {user0} from moderators')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator demoted you, {user0} and %n more participant from moderators',
								'An administrator demoted you, {user0} and %n more participants from moderators',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} demoted you, {user0} and %n more participant from moderators',
								'{actor} demoted you, {user0} and %n more participants from moderators',
								usersCounter - 2,
							)
				}
			} else {
				if (usersCounter === 2) {
					combinedMessage.message = actorIsAdministrator
						? t('spreed', 'An administrator demoted {user0} and {user1} from moderators')
						: t('spreed', '{actor} demoted {user0} and {user1} from moderators')
				} else {
					combinedMessage.message = actorIsAdministrator
						? n(
								'spreed',
								'An administrator demoted {user0}, {user1} and %n more participant from moderators',
								'An administrator demoted {user0}, {user1} and %n more participants from moderators',
								usersCounter - 2,
							)
						: n(
								'spreed',
								'{actor} demoted {user0}, {user1} and %n more participant from moderators',
								'{actor} demoted {user0}, {user1} and %n more participants from moderators',
								usersCounter - 2,
							)
				}
			}
		}

		return combinedMessage
	}

	return {
		createCombinedSystemMessage,
	}
}
