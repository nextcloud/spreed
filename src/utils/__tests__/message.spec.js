/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { ATTENDEE, MESSAGE } from '../../constants.ts'
import { tryLocalizeDeletedMessage, tryLocalizeSystemMessage } from '../message.ts'

// Host's local "admin" and a federated "admin" from another instance
const LOCAL_ADMIN = { type: 'user', id: 'admin', name: 'admin', 'mention-id': 'admin' }
const REMOTE_ADMIN = { type: 'user', id: 'admin', name: 'admin', server: 'nextcloud.local', 'mention-id': 'federated_user/admin@nextcloud.local' }

const localConversation = { token: 'abc', actorId: 'admin', actorType: ATTENDEE.ACTOR_TYPE.USERS }

// Viewer is federated "admin" from this instance (http://localhost/nc-webroot in tests), host has its own "admin".
// Relayed actorType and actorId are in the local view, parameters are in the host's view
const federatedConversation = { ...localConversation, remoteServer: 'https://host.local' }
const HOST_ADMIN = LOCAL_ADMIN
const SELF_ON_HOST = { type: 'user', id: 'admin', name: 'admin', server: 'http://localhost/nc-webroot', 'mention-id': 'federated_user/admin@http://localhost/nc-webroot' }

/**
 * Deleted chat message (the parent of a 'message_deleted' system message)
 *
 * @param {object} author author of the original message
 * @param {string} author.actorType actor type of the author
 * @param {string} author.actorId actor id of the author
 * @param {object} deletedBy actor parameter of the user who deleted the message
 */
function deletedMessage({ actorType, actorId }, deletedBy) {
	return {
		id: 1,
		token: 'abc',
		actorType,
		actorId,
		actorDisplayName: 'admin',
		message: 'Message deleted by author',
		messageParameters: { actor: deletedBy },
		messageType: MESSAGE.TYPE.COMMENT_DELETED,
		systemMessage: '',
	}
}

describe('tryLocalizeDeletedMessage', () => {
	it('says "by you" when the local user deleted their own message', () => {
		const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.USERS, actorId: 'admin' }, LOCAL_ADMIN)
		expect(tryLocalizeDeletedMessage(message, localConversation)).toBe('Message deleted by you')
	})

	it('does not say "by you" when a federated user with the same id deleted their message', () => {
		const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS, actorId: 'admin@nextcloud.local' }, REMOTE_ADMIN)
		expect(tryLocalizeDeletedMessage(message, localConversation)).toBe('Message deleted by author')
	})

	it('says "by {actor}" when a federated user with the same id deleted a local message', () => {
		const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.USERS, actorId: 'admin' }, REMOTE_ADMIN)
		expect(tryLocalizeDeletedMessage(message, localConversation)).toBe('Message deleted by {actor}')
	})

	it('says "by you" when the local user deleted a message of a federated user with the same id', () => {
		const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS, actorId: 'admin@nextcloud.local' }, LOCAL_ADMIN)
		expect(tryLocalizeDeletedMessage(message, localConversation)).toBe('Message deleted by you')
	})

	it('says "by author" when another local user deleted their own message', () => {
		const bob = { type: 'user', id: 'bob', name: 'bob', 'mention-id': 'bob' }
		const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.USERS, actorId: 'bob' }, bob)
		expect(tryLocalizeDeletedMessage(message, localConversation)).toBe('Message deleted by author')
	})

	it('says "by {actor}" when a federated user deleted a message of another federated user with the same id', () => {
		const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS, actorId: 'admin@nextcloud3.local' }, REMOTE_ADMIN)
		expect(tryLocalizeDeletedMessage(message, localConversation)).toBe('Message deleted by {actor}')
	})

	describe('in federated conversation', () => {
		it('says "by {actor}" when host user with the same id deleted own message', () => {
			const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.USERS, actorId: 'admin' }, HOST_ADMIN)
			expect(tryLocalizeDeletedMessage(message, federatedConversation)).toBe('Message deleted by {actor}')
		})

		it('says "by you" when own message was deleted by self', () => {
			const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.USERS, actorId: 'admin' }, SELF_ON_HOST)
			expect(tryLocalizeDeletedMessage(message, federatedConversation)).toBe('Message deleted by you')
		})

		it('says "by author" when host user with the same id deleted their message', () => {
			const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS, actorId: 'admin@host.local' }, HOST_ADMIN)
			expect(tryLocalizeDeletedMessage(message, federatedConversation)).toBe('Message deleted by author')
		})

		it('says "by you" when self deleted a message of host user with the same id', () => {
			const message = deletedMessage({ actorType: ATTENDEE.ACTOR_TYPE.FEDERATED_USERS, actorId: 'admin@host.local' }, SELF_ON_HOST)
			expect(tryLocalizeDeletedMessage(message, federatedConversation)).toBe('Message deleted by you')
		})
	})
})

describe('tryLocalizeSystemMessage', () => {
	/**
	 * Relayed system message where the "user" was promoted by the "actor"
	 *
	 * @param {object} actor actor parameter
	 * @param {object} user user parameter
	 */
	function moderatorPromoted(actor, user) {
		return {
			id: 2,
			token: 'abc',
			actorType: ATTENDEE.ACTOR_TYPE.USERS,
			actorId: 'someone',
			message: '{actor} promoted {user} to moderator',
			messageParameters: { actor, user },
			messageType: MESSAGE.TYPE.SYSTEM,
			systemMessage: MESSAGE.SYSTEM_TYPE.MODERATOR_PROMOTED,
		}
	}

	const bob = { type: 'user', id: 'bob', name: 'bob', 'mention-id': 'bob' }

	it('says "You" when the local user is the actor', () => {
		const message = moderatorPromoted(LOCAL_ADMIN, bob)
		expect(tryLocalizeSystemMessage(message, localConversation)).toMatch(/^You /)
	})

	it('does not say "You" when a federated user with the same id is the actor', () => {
		const message = moderatorPromoted(REMOTE_ADMIN, bob)
		expect(tryLocalizeSystemMessage(message, localConversation)).not.toMatch(/^You /)
	})

	it('does not say "you" when a federated user with the same id is the affected user', () => {
		const message = moderatorPromoted(bob, REMOTE_ADMIN)
		expect(tryLocalizeSystemMessage(message, localConversation)).not.toMatch(/\byou\b/)
	})

	it('says "you" when the local user is the affected user', () => {
		const message = moderatorPromoted(bob, LOCAL_ADMIN)
		expect(tryLocalizeSystemMessage(message, localConversation)).toMatch(/\byou\b/)
	})

	describe('in federated conversation', () => {
		it('says "You" when self is the actor', () => {
			const message = moderatorPromoted(SELF_ON_HOST, bob)
			expect(tryLocalizeSystemMessage(message, federatedConversation)).toMatch(/^You /)
		})

		it('does not say "You" when host user with the same id is the actor', () => {
			const message = moderatorPromoted(HOST_ADMIN, bob)
			expect(tryLocalizeSystemMessage(message, federatedConversation)).not.toMatch(/^You /)
		})

		it('says "you" when self is the affected user', () => {
			const message = moderatorPromoted(bob, SELF_ON_HOST)
			expect(tryLocalizeSystemMessage(message, federatedConversation)).toMatch(/\byou\b/)
		})

		it('does not say "you" when host user with the same id is the affected user', () => {
			const message = moderatorPromoted(bob, HOST_ADMIN)
			expect(tryLocalizeSystemMessage(message, federatedConversation)).not.toMatch(/\byou\b/)
		})
	})
})
